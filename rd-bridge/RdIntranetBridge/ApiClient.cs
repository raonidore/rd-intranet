using System.Net.Http.Json;
using System.Text.Json;

namespace RdIntranetBridge;

/// <summary>
/// Fala com o RD.Intranet na nuvem por conexão de saída -- mesmo padrão
/// já validado no agente Windows (checkin/heartbeat, cabeçalho de chave,
/// certificado autoassinado aceito sem checagem porque a conexão é
/// direto pro servidor configurado, não passa por rede não confiável no
/// meio). Nunca abre porta nenhuma; só chama pra fora.
/// </summary>
public class ApiClient
{
    private readonly BridgeConfig _config;
    private static readonly JsonSerializerOptions JsonOpts = new() { PropertyNameCaseInsensitive = true };

    public ApiClient(BridgeConfig config)
    {
        _config = config;
    }

    private HttpClient CriarCliente()
    {
        var handler = new HttpClientHandler
        {
            // Servidor RD.Intranet costuma ter certificado autoassinado por
            // padrão (mesmo motivo documentado no agente Windows) -- a
            // conexão em si já é validada pelo token, não pelo certificado.
            ServerCertificateCustomValidationCallback = (msg, cert, chain, erros) => true
        };

        var cliente = new HttpClient(handler) { Timeout = TimeSpan.FromSeconds(30) };
        cliente.DefaultRequestHeaders.Add("X-RD-Bridge-Chave", _config.Token);
        return cliente;
    }

    public async Task<HeartbeatResposta?> HeartbeatAsync(string versao)
    {
        try
        {
            using var cliente = CriarCliente();
            var url = _config.ServerUrl.TrimEnd('/') + "/api/rd-bridge/heartbeat";
            var res = await cliente.PostAsJsonAsync(url, new { versao });

            if (!res.IsSuccessStatusCode)
            {
                return null;
            }

            return await res.Content.ReadFromJsonAsync<HeartbeatResposta>(JsonOpts);
        }
        catch
        {
            return null; // falha de rede pontual -- o Worker tenta de novo no próximo ciclo
        }
    }

    public async Task<bool> EnviarResultadoColetaAsync(int ativoId, Dictionary<string, string> dados)
    {
        try
        {
            using var cliente = CriarCliente();
            var url = _config.ServerUrl.TrimEnd('/') + "/api/rd-bridge/coleta/resultado";
            var res = await cliente.PostAsJsonAsync(url, new { ativo_id = ativoId, dados });

            if (!res.IsSuccessStatusCode)
            {
                return false;
            }

            var corpo = await res.Content.ReadFromJsonAsync<ColetaResultadoResposta>(JsonOpts);
            return corpo?.Success ?? false;
        }
        catch
        {
            return false;
        }
    }
}
