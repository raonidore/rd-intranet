using System.Collections.Generic;
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
    public List<ComandoItem> Comandos { get; set; } = new();
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

            string mensagem = textoResposta;
            var comandos = new List<ComandoItem>();
            try
            {
                var corpo = JsonSerializer.Deserialize<RespostaCheckin>(textoResposta);
                if (corpo != null)
                {
                    mensagem = corpo.Message ?? textoResposta;
                    comandos = corpo.Comandos;
                }
            }
            catch
            {
                // resposta nao veio no formato esperado -- mostra o texto cru mesmo
            }

            // Se o corpo veio vazio (ex.: erro 500 do servidor sem saida,
            // proxy cortando a resposta) "mensagem" tambem fica vazia --
            // troca por um texto que pelo menos aponta o status HTTP, pra
            // dar pra diagnosticar em vez de só falhar calado.
            if (string.IsNullOrWhiteSpace(mensagem))
            {
                mensagem = $"Servidor respondeu sem mensagem (HTTP {(int)resposta.StatusCode}).";
            }

            return new ResultadoCheckin
            {
                Sucesso = resposta.IsSuccessStatusCode,
                Mensagem = mensagem,
                BytesEnviados = bytesEnvio.LongLength,
                BytesRecebidos = bytesRecebidos,
                Comandos = comandos
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
