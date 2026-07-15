using System.IO;
using System.Net.Http;
using System.Net.Http.Headers;

namespace RdIntranetAgente;

/// <summary>
/// Move bytes de arquivo de verdade entre agente e servidor -- diferente
/// de SolicitacaoClient (só JSON pequeno) e HeartbeatClient (só o
/// machine_guid). Duas direções: agente manda o conteúdo de um arquivo
/// local pro portal (resposta de 'baixar_arquivo') e agente busca o
/// anexo de um comando 'enviar_arquivo' pra gravar localmente.
/// </summary>
public class TransferenciaClient
{
    private readonly Config _config;

    public TransferenciaClient(Config config)
    {
        _config = config;
    }

    private HttpClient CriarCliente(int timeoutSegundos)
    {
        var handler = new HttpClientHandler
        {
            ServerCertificateCustomValidationCallback = (msg, cert, chain, erros) => true
        };

        var cliente = new HttpClient(handler) { Timeout = TimeSpan.FromSeconds(timeoutSegundos) };
        cliente.DefaultRequestHeaders.Add("X-RD-Agente-Chave", _config.ApiKey);
        return cliente;
    }

    /// <summary>Envia o conteúdo de um arquivo local pro servidor, como resposta de uma solicitação 'baixar_arquivo'.</summary>
    public async Task<bool> EnviarArquivoAsync(string machineGuid, int solicitacaoId, string caminhoLocal, string nomeArquivo)
    {
        try
        {
            using var cliente = CriarCliente(120);
            using var conteudo = new MultipartFormDataContent
            {
                { new StringContent(machineGuid), "machine_guid" },
                { new StringContent(solicitacaoId.ToString()), "id" },
                { new StringContent(nomeArquivo), "nome" }
            };

            using var streamArquivo = File.OpenRead(caminhoLocal);
            using var conteudoArquivo = new StreamContent(streamArquivo);
            conteudoArquivo.Headers.ContentType = new MediaTypeHeaderValue("application/octet-stream");
            conteudo.Add(conteudoArquivo, "arquivo", nomeArquivo);

            var url = _config.ServerUrl.TrimEnd('/') + "/api/ativos/solicitacoes/arquivo";
            var resposta = await cliente.PostAsync(url, conteudo);

            return resposta.IsSuccessStatusCode;
        }
        catch
        {
            return false;
        }
    }

    /// <summary>Baixa o anexo de um comando 'enviar_arquivo' pendente e grava no caminho de destino.</summary>
    public async Task<bool> BaixarAnexoComandoAsync(string machineGuid, int comandoId, string destino)
    {
        try
        {
            using var cliente = CriarCliente(120);
            var url = _config.ServerUrl.TrimEnd('/') + $"/api/ativos/comandos/anexo?machine_guid={Uri.EscapeDataString(machineGuid)}&id={comandoId}";

            using var resposta = await cliente.GetAsync(url);
            if (!resposta.IsSuccessStatusCode)
            {
                return false;
            }

            var pastaDestino = Path.GetDirectoryName(destino);
            if (!string.IsNullOrEmpty(pastaDestino) && !Directory.Exists(pastaDestino))
            {
                Directory.CreateDirectory(pastaDestino);
            }

            await using var streamResposta = await resposta.Content.ReadAsStreamAsync();
            await using var arquivoDestino = File.Create(destino);
            await streamResposta.CopyToAsync(arquivoDestino);

            return true;
        }
        catch
        {
            return false;
        }
    }
}
