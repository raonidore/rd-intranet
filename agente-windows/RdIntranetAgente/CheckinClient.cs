using System.Net.Http;
using System.Net.Http.Headers;
using System.Text;
using System.Text.Json;
using System.Text.Json.Serialization;
using RdIntranetAgente.Models;

namespace RdIntranetAgente;

public class ResultadoCheckin
{
    public bool Sucesso { get; set; }
    public string Mensagem { get; set; } = "";
    public long BytesEnviados { get; set; }
    public long BytesRecebidos { get; set; }
}

public class CheckinClient
{
    private readonly Config _config;

    public CheckinClient(Config config)
    {
        _config = config;
    }

    public async Task<ResultadoCheckin> EnviarAsync(CheckinPayload payload)
    {
        var json = JsonSerializer.Serialize(payload, new JsonSerializerOptions
        {
            DefaultIgnoreCondition = JsonIgnoreCondition.WhenWritingNull
        });
        var bytesEnvio = Encoding.UTF8.GetBytes(json);

        var handler = new HttpClientHandler
        {
            // O RD Intranet usa certificado autoassinado por padrão -- sem
            // isso o HttpClient recusa a conexão com erro de certificado
            // não confiável.
            ServerCertificateCustomValidationCallback = (msg, cert, chain, erros) => true
        };

        using var cliente = new HttpClient(handler) { Timeout = TimeSpan.FromSeconds(30) };
        cliente.DefaultRequestHeaders.Add("X-RD-Agente-Chave", _config.ApiKey);

        var conteudo = new ByteArrayContent(bytesEnvio);
        conteudo.Headers.ContentType = new MediaTypeHeaderValue("application/json") { CharSet = "utf-8" };

        var url = _config.ServerUrl.TrimEnd('/') + "/api/ativos/checkin";

        try
        {
            var resposta = await cliente.PostAsync(url, conteudo);
            var textoResposta = await resposta.Content.ReadAsStringAsync();
            var bytesRecebidos = Encoding.UTF8.GetByteCount(textoResposta);

            string mensagem;
            try
            {
                using var doc = JsonDocument.Parse(textoResposta);
                mensagem = doc.RootElement.TryGetProperty("message", out var m) ? (m.GetString() ?? "") : textoResposta;
            }
            catch
            {
                mensagem = textoResposta;
            }

            return new ResultadoCheckin
            {
                Sucesso = resposta.IsSuccessStatusCode,
                Mensagem = mensagem,
                BytesEnviados = bytesEnvio.LongLength,
                BytesRecebidos = bytesRecebidos
            };
        }
        catch (Exception ex)
        {
            return new ResultadoCheckin
            {
                Sucesso = false,
                Mensagem = $"Erro de comunicação: {ex.Message}",
                BytesEnviados = bytesEnvio.LongLength,
                BytesRecebidos = 0
            };
        }
    }
}
