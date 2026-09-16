using System.Net.Http;
using System.Text.Json;
using System.Text.Json.Serialization;

namespace RdIntranetAgente;

/// <summary>
/// Busca unidades/setores/localizações cadastrados no RD Intranet
/// (GET /api/ativos/cadastros) pra preencher os combos da tela de
/// configuração antes do primeiro checkin -- ver ConfigForm. Serve
/// também de teste prático de "a URL e a chave de API estão certas?":
/// hoje isso só era descoberto depois, de forma confusa, no primeiro
/// checkin/heartbeat que falhava silenciosamente em segundo plano.
/// </summary>
public class CadastrosClient
{
    public async Task<RespostaCadastros?> BuscarAsync(string serverUrl, string apiKey)
    {
        if (string.IsNullOrWhiteSpace(serverUrl) || string.IsNullOrWhiteSpace(apiKey))
        {
            return null;
        }

        try
        {
            var handler = new HttpClientHandler
            {
                // Mesmo motivo do CheckinClient: certificado autoassinado por padrão.
                ServerCertificateCustomValidationCallback = (msg, cert, chain, erros) => true
            };

            using var cliente = new HttpClient(handler) { Timeout = TimeSpan.FromSeconds(20) };
            cliente.DefaultRequestHeaders.Add("X-RD-Agente-Chave", apiKey);

            var url = serverUrl.TrimEnd('/') + "/api/ativos/cadastros";
            var resposta = await cliente.GetAsync(url);
            if (!resposta.IsSuccessStatusCode) return null;

            var json = await resposta.Content.ReadAsStringAsync();
            var dados = JsonSerializer.Deserialize<RespostaCadastros>(json);

            return (dados != null && dados.Success) ? dados : null;
        }
        catch
        {
            return null;
        }
    }
}

public class RespostaCadastros
{
    [JsonPropertyName("success")]
    public bool Success { get; set; }

    [JsonPropertyName("unidades")]
    public List<CadastroItem> Unidades { get; set; } = new();

    [JsonPropertyName("setores")]
    public List<CadastroItem> Setores { get; set; } = new();

    [JsonPropertyName("localizacoes")]
    public List<CadastroItem> Localizacoes { get; set; } = new();
}

public class CadastroItem
{
    [JsonPropertyName("id")]
    public int Id { get; set; }

    [JsonPropertyName("nome")]
    public string Nome { get; set; } = "";

    public override string ToString() => Nome;
}
