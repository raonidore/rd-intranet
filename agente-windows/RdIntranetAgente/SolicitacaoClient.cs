using System.Net.Http;
using System.Net.Http.Headers;
using System.Text;
using System.Text.Json;

namespace RdIntranetAgente;

/// <summary>
/// Devolve o resultado de uma SolicitacaoItem (explorador de arquivos/
/// processos) recebida no heartbeat -- endpoint separado do próprio
/// heartbeat pra não atrasar o ping seguinte enquanto lista uma pasta
/// grande ou muitos processos.
/// </summary>
public class SolicitacaoClient
{
    private readonly Config _config;

    public SolicitacaoClient(Config config)
    {
        _config = config;
    }

    private HttpClient CriarCliente(TimeSpan? timeout = null)
    {
        var handler = new HttpClientHandler
        {
            ServerCertificateCustomValidationCallback = (msg, cert, chain, erros) => true
        };

        var cliente = new HttpClient(handler) { Timeout = timeout ?? TimeSpan.FromSeconds(30) };
        cliente.DefaultRequestHeaders.Add("X-RD-Agente-Chave", _config.ApiKey);
        return cliente;
    }

    /// <summary>resultado aceita qualquer coisa serializável -- lista (listar_arquivos/processos) ou objeto único (executar_cmd/powershell).</summary>
    public async Task<bool> ResponderAsync(string machineGuid, int id, object resultado, TimeSpan? timeout = null)
    {
        return await EnviarAsync(new { machine_guid = machineGuid, id, resultado }, timeout);
    }

    public async Task<bool> ResponderErroAsync(string machineGuid, int id, string erro)
    {
        return await EnviarAsync(new { machine_guid = machineGuid, id, erro }, null);
    }

    private async Task<bool> EnviarAsync(object corpo, TimeSpan? timeout)
    {
        try
        {
            var json = JsonSerializer.Serialize(corpo);
            var conteudo = new ByteArrayContent(Encoding.UTF8.GetBytes(json));
            conteudo.Headers.ContentType = new MediaTypeHeaderValue("application/json") { CharSet = "utf-8" };

            using var cliente = CriarCliente(timeout);
            var url = _config.ServerUrl.TrimEnd('/') + "/api/ativos/solicitacoes/resultado";
            var resposta = await cliente.PostAsync(url, conteudo);

            return resposta.IsSuccessStatusCode;
        }
        catch
        {
            return false;
        }
    }
}
