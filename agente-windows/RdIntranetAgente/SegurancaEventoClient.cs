using System.Collections.Generic;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Text;
using System.Text.Json;

namespace RdIntranetAgente;

/// <summary>Evento pronto pra enviar a POST /api/ativos/seguranca/evento.</summary>
public sealed class EventoSeguranca
{
    public string Tipo { get; init; } = "";
    public string Severidade { get; init; } = "WARNING";
    public string Resumo { get; init; } = "";
    public Dictionary<string, object?> Detalhes { get; init; } = new();
    public DateTimeOffset OcorridoEm { get; init; } = DateTimeOffset.Now;
    public bool Enviado { get; set; }
}

/// <summary>
/// Envia detecções do módulo anti-ransomware na hora, fora do ciclo de
/// checkin (em ataque ativo, esperar 15 minutos pelo próximo checkin
/// anularia o propósito). O servidor decide a resposta (alerta,
/// isolamento com confirmação, isolamento automático) -- o agente só
/// reporta.
/// </summary>
public class SegurancaEventoClient
{
    private readonly Config _config;

    public SegurancaEventoClient(Config config)
    {
        _config = config;
    }

    public async Task<bool> EnviarAsync(string machineGuid, EventoSeguranca evento)
    {
        if (!_config.EstaConfigurado || string.IsNullOrEmpty(machineGuid))
        {
            return false;
        }

        var json = JsonSerializer.Serialize(new
        {
            machine_guid = machineGuid,
            tipo = evento.Tipo,
            severidade = evento.Severidade,
            acao_automatica = "nenhuma",
            ocorrido_em = evento.OcorridoEm.ToString("o"),
            detalhes = evento.Detalhes
        });

        var handler = new HttpClientHandler
        {
            ServerCertificateCustomValidationCallback = (msg, cert, chain, erros) => true
        };

        using var cliente = new HttpClient(handler) { Timeout = TimeSpan.FromSeconds(15) };
        cliente.DefaultRequestHeaders.Add("X-RD-Agente-Chave", _config.ApiKey);

        var conteudo = new ByteArrayContent(Encoding.UTF8.GetBytes(json));
        conteudo.Headers.ContentType = new MediaTypeHeaderValue("application/json") { CharSet = "utf-8" };

        try
        {
            var resposta = await cliente.PostAsync(_config.ServerUrl.TrimEnd('/') + "/api/ativos/seguranca/evento", conteudo);
            return resposta.IsSuccessStatusCode;
        }
        catch
        {
            return false;
        }
    }
}
