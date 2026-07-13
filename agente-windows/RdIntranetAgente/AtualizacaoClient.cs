using System.Net.Http;
using System.Text.Json;
using System.Text.Json.Serialization;

namespace RdIntranetAgente;

/// <summary>
/// Fala com os mesmos endpoints do checkin (autenticados por
/// X-RD-Agente-Chave) pra saber se há uma versão nova do próprio .exe
/// publicada no RD Intranet e baixá-la. A substituição do arquivo em si
/// (o processo não consegue sobrescrever o próprio .exe em execução)
/// fica a cargo do TrayApplicationContext, que gera um script auxiliar
/// pra fazer a troca depois que este processo encerrar.
/// </summary>
public class AtualizacaoClient
{
    private readonly Config _config;

    public AtualizacaoClient(Config config)
    {
        _config = config;
    }

    private HttpClient CriarCliente()
    {
        var handler = new HttpClientHandler
        {
            // Mesmo motivo do CheckinClient: certificado autoassinado por padrão.
            ServerCertificateCustomValidationCallback = (msg, cert, chain, erros) => true
        };

        var cliente = new HttpClient(handler) { Timeout = TimeSpan.FromSeconds(60) };
        cliente.DefaultRequestHeaders.Add("X-RD-Agente-Chave", _config.ApiKey);
        return cliente;
    }

    public async Task<Version?> ObterVersaoDisponivelAsync()
    {
        try
        {
            using var cliente = CriarCliente();
            var url = _config.ServerUrl.TrimEnd('/') + "/api/ativos/agente/versao";
            var resposta = await cliente.GetAsync(url);
            if (!resposta.IsSuccessStatusCode) return null;

            var json = await resposta.Content.ReadAsStringAsync();
            var dados = JsonSerializer.Deserialize<RespostaVersaoAgente>(json);

            if (dados == null || !dados.Disponivel || string.IsNullOrWhiteSpace(dados.Versao))
            {
                return null;
            }

            return Version.TryParse(dados.Versao, out var versao) ? versao : null;
        }
        catch
        {
            return null;
        }
    }

    /// <summary>Baixa o .exe novo pra um arquivo temporário. Recusa arquivos suspeitos de vir truncados/corrompidos (menor que 10KB).</summary>
    public async Task<bool> BaixarNovaVersaoAsync(string destino)
    {
        try
        {
            using var cliente = CriarCliente();
            var url = _config.ServerUrl.TrimEnd('/') + "/api/ativos/agente/download";
            using var resposta = await cliente.GetAsync(url);
            if (!resposta.IsSuccessStatusCode) return false;

            var bytes = await resposta.Content.ReadAsByteArrayAsync();
            if (bytes.Length < 10240) return false;

            await File.WriteAllBytesAsync(destino, bytes);
            return true;
        }
        catch
        {
            return false;
        }
    }
}

public class RespostaVersaoAgente
{
    [JsonPropertyName("disponivel")]
    public bool Disponivel { get; set; }

    [JsonPropertyName("versao")]
    public string? Versao { get; set; }
}
