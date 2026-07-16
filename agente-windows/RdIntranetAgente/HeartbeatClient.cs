using System.Collections.Generic;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Text;
using System.Text.Json;
using System.Text.Json.Serialization;

namespace RdIntranetAgente;

public class ResultadoHeartbeat
{
    public bool Sucesso { get; set; }
    public bool ForcarCheckin { get; set; }
    public List<SolicitacaoItem> Solicitacoes { get; set; } = new();

    /// <summary>Só vem preenchida quando o servidor tem uma chave mais nova marcada pra rollout automático -- ver TrayApplicationContext.</summary>
    public string? ChaveApiAtual { get; set; }
}

/// <summary>Pedido de leitura pendente (explorador de arquivos/processos) entregue no heartbeat.</summary>
public class SolicitacaoItem
{
    [JsonPropertyName("id")]
    public int Id { get; set; }

    [JsonPropertyName("tipo")]
    public string Tipo { get; set; } = "";

    [JsonPropertyName("parametro")]
    public string? Parametro { get; set; }

    [JsonPropertyName("elevado")]
    public bool Elevado { get; set; }

    /// <summary>Só vem preenchido quando Elevado=true e o servidor tem uma credencial de elevação configurada.</summary>
    [JsonPropertyName("usuario_elevacao")]
    public string? UsuarioElevacao { get; set; }

    [JsonPropertyName("senha_elevacao")]
    public string? SenhaElevacao { get; set; }
}

/// <summary>
/// Ping bem leve de "estou ligado", chamado a cada poucos segundos
/// (Config.HeartbeatSegundos) -- deliberadamente mais simples que
/// CheckinClient: manda só o machine_guid (já calculado uma vez no
/// início do processo, ver CollectorService.ObterMachineGuid()), sem
/// rodar coleta nenhuma via WMI. A resposta também carrega
/// "forcar_checkin", usado pra rodar uma coleta completa fora do ciclo
/// normal quando alguém pede pelo portal.
/// </summary>
public class HeartbeatClient
{
    private readonly Config _config;

    public HeartbeatClient(Config config)
    {
        _config = config;
    }

    public async Task<ResultadoHeartbeat> EnviarAsync(string machineGuid)
    {
        var json = JsonSerializer.Serialize(new { machine_guid = machineGuid });
        var bytesEnvio = Encoding.UTF8.GetBytes(json);

        var handler = new HttpClientHandler
        {
            ServerCertificateCustomValidationCallback = (msg, cert, chain, erros) => true
        };

        using var cliente = new HttpClient(handler) { Timeout = TimeSpan.FromSeconds(10) };
        cliente.DefaultRequestHeaders.Add("X-RD-Agente-Chave", _config.ApiKey);

        var conteudo = new ByteArrayContent(bytesEnvio);
        conteudo.Headers.ContentType = new MediaTypeHeaderValue("application/json") { CharSet = "utf-8" };

        var url = _config.ServerUrl.TrimEnd('/') + "/api/ativos/heartbeat";

        try
        {
            var resposta = await cliente.PostAsync(url, conteudo);
            if (!resposta.IsSuccessStatusCode)
            {
                return new ResultadoHeartbeat { Sucesso = false };
            }

            var textoResposta = await resposta.Content.ReadAsStringAsync();
            var corpo = JsonSerializer.Deserialize<RespostaHeartbeat>(textoResposta);

            return new ResultadoHeartbeat
            {
                Sucesso = corpo?.Success ?? false,
                ForcarCheckin = corpo?.ForcarCheckin ?? false,
                Solicitacoes = corpo?.Solicitacoes ?? new List<SolicitacaoItem>(),
                ChaveApiAtual = corpo?.ChaveApiAtual
            };
        }
        catch
        {
            // sem sorte agora -- tenta de novo no proximo tick, sem incomodar o usuario
            return new ResultadoHeartbeat { Sucesso = false };
        }
    }

    private class RespostaHeartbeat
    {
        [JsonPropertyName("success")]
        public bool Success { get; set; }

        [JsonPropertyName("forcar_checkin")]
        public bool ForcarCheckin { get; set; }

        [JsonPropertyName("solicitacoes")]
        public List<SolicitacaoItem>? Solicitacoes { get; set; }

        [JsonPropertyName("chave_api_atual")]
        public string? ChaveApiAtual { get; set; }
    }
}
