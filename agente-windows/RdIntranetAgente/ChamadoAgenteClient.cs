using System.Net;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.Json.Serialization;

namespace RdIntranetAgente;

/// <summary>
/// Cliente da API /api/agente/chamados/* (módulo Chamados do agente).
/// Toda chamada leva a chave de instalação e o machine_guid; o login do
/// RD Intranet (opcional) vira um token que fica salvo criptografado com
/// DPAPI do usuário do Windows -- outra pessoa que use o mesmo PC com
/// outro login do Windows não herda a sessão, e o arquivo copiado pra
/// outra máquina não serve pra nada. A senha nunca é guardada.
/// </summary>
public sealed class ChamadoAgenteClient
{
    private static readonly string ArquivoSessao = Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "RdIntranetAgente", "sessao-chamados.bin");

    private static readonly JsonSerializerOptions Json = new() { PropertyNameCaseInsensitive = true };

    private readonly Config _config;
    private string _token;

    public ChamadoAgenteClient(Config config)
    {
        _config = config;
        _token = CarregarToken();
    }

    public bool Logado => _token != "";

    public async Task<RespostaApi<FormularioChamado>> FormularioAsync() =>
        await EnviarAsync<FormularioChamado>(HttpMethod.Get, "formulario", null);

    public async Task<RespostaApi<ListaAtivos>> BuscarAtivosAsync(string termo) =>
        await EnviarAsync<ListaAtivos>(HttpMethod.Get, "ativos?q=" + Uri.EscapeDataString(termo), null);

    public async Task<RespostaApi<RespostaLogin>> LoginAsync(string login, string senha)
    {
        var resposta = await EnviarAsync<RespostaLogin>(HttpMethod.Post, "login", new { login, senha, usuario_windows = Environment.UserName });
        if (resposta.Ok && !string.IsNullOrEmpty(resposta.Dados?.Token))
        {
            _token = resposta.Dados!.Token!;
            SalvarToken(_token);
        }
        return resposta;
    }

    public async Task LogoutAsync()
    {
        try
        {
            await EnviarAsync<RespostaSimples>(HttpMethod.Post, "logout", new { });
        }
        finally
        {
            Esquecer();
        }
    }

    public async Task<RespostaApi<RespostaAbertura>> AbrirAsync(object dados) =>
        await EnviarAsync<RespostaAbertura>(HttpMethod.Post, "abrir", dados);

    public async Task<RespostaApi<ListaChamados>> MeusAsync() =>
        await EnviarAsync<ListaChamados>(HttpMethod.Get, "meus", null);

    public async Task<RespostaApi<DetalheResposta>> VerAsync(int id) =>
        await EnviarAsync<DetalheResposta>(HttpMethod.Get, "ver?id=" + id, null);

    public async Task<RespostaApi<RespostaSimples>> ResponderAsync(int id, string texto) =>
        await EnviarAsync<RespostaSimples>(HttpMethod.Post, "responder", new { id, texto });

    private async Task<RespostaApi<T>> EnviarAsync<T>(HttpMethod metodo, string caminho, object? corpo) where T : RespostaSimples
    {
        if (!_config.EstaConfigurado)
        {
            return RespostaApi<T>.Falha("O agente ainda não está configurado (Configurações).");
        }

        string guid;
        try
        {
            guid = CollectorService.ObterMachineGuid();
        }
        catch
        {
            return RespostaApi<T>.Falha("Não consegui identificar esta máquina.");
        }

        var handler = new HttpClientHandler
        {
            // Mesmo motivo do CheckinClient: certificado autoassinado por padrão.
            ServerCertificateCustomValidationCallback = (m, c, ch, e) => true
        };
        using var cliente = new HttpClient(handler) { Timeout = TimeSpan.FromSeconds(20) };
        using var requisicao = new HttpRequestMessage(metodo, _config.ServerUrl.TrimEnd('/') + "/api/agente/chamados/" + caminho);
        requisicao.Headers.Add("X-RD-Agente-Chave", _config.ApiKey);
        requisicao.Headers.Add("X-RD-Agente-Maquina", guid);
        if (_token != "")
        {
            requisicao.Headers.Add("X-RD-Agente-Sessao", _token);
        }
        if (corpo != null)
        {
            requisicao.Content = new StringContent(JsonSerializer.Serialize(corpo), Encoding.UTF8);
            requisicao.Content.Headers.ContentType = new MediaTypeHeaderValue("application/json") { CharSet = "utf-8" };
        }

        try
        {
            using var resposta = await cliente.SendAsync(requisicao);
            var texto = await resposta.Content.ReadAsStringAsync();

            T? dados = null;
            try
            {
                dados = JsonSerializer.Deserialize<T>(texto, Json);
            }
            catch (Exception ex)
            {
                // resposta não-JSON (erro do servidor/proxy) ou fora do formato esperado
                LogAtividade.Registrar(NivelAtividade.Erro, "CHAMADOS", $"Resposta inesperada de {caminho}: {ex.Message}");
            }

            if (dados?.SessaoExpirada == true)
            {
                Esquecer();
            }

            if (dados == null)
            {
                return RespostaApi<T>.Falha($"O servidor respondeu de forma inesperada (HTTP {(int)resposta.StatusCode}).");
            }

            return new RespostaApi<T>(dados.Success && resposta.IsSuccessStatusCode, dados.Message ?? "", dados);
        }
        catch (TaskCanceledException)
        {
            return RespostaApi<T>.Falha("O servidor demorou demais para responder.");
        }
        catch (HttpRequestException ex) when (ex.StatusCode is null or HttpStatusCode.ServiceUnavailable)
        {
            return RespostaApi<T>.Falha("Não consegui falar com o servidor do RD Intranet.");
        }
        catch (Exception ex)
        {
            return RespostaApi<T>.Falha(ex.Message);
        }
    }

    private void Esquecer()
    {
        _token = "";
        try
        {
            File.Delete(ArquivoSessao);
        }
        catch
        {
            // sem arquivo, nada a apagar
        }
    }

    private static string CarregarToken()
    {
        try
        {
            if (!File.Exists(ArquivoSessao)) return "";
            var bytes = ProtectedData.Unprotect(File.ReadAllBytes(ArquivoSessao), null, DataProtectionScope.CurrentUser);
            return Encoding.UTF8.GetString(bytes);
        }
        catch
        {
            return "";
        }
    }

    private static void SalvarToken(string token)
    {
        try
        {
            Directory.CreateDirectory(Path.GetDirectoryName(ArquivoSessao)!);
            File.WriteAllBytes(ArquivoSessao, ProtectedData.Protect(Encoding.UTF8.GetBytes(token), null, DataProtectionScope.CurrentUser));
        }
        catch
        {
            // não conseguiu salvar: continua logado só até fechar o agente
        }
    }
}

public sealed record RespostaApi<T>(bool Ok, string Mensagem, T? Dados)
{
    public static RespostaApi<T> Falha(string mensagem) => new(false, mensagem, default);
}

public class RespostaSimples
{
    [JsonPropertyName("success")] public bool Success { get; set; }
    [JsonPropertyName("message")] public string? Message { get; set; }
    [JsonPropertyName("sessao_expirada")] public bool SessaoExpirada { get; set; }
}

public sealed class ItemSimples
{
    [JsonPropertyName("id")] public JsonElement IdBruto { get; set; }
    [JsonPropertyName("nome")] public string Nome { get; set; } = "";

    /// <summary>Unidades/setores/categorias têm id numérico; prioridade tem id texto ("media").</summary>
    [JsonIgnore]
    public string Id => IdBruto.ValueKind == JsonValueKind.Number ? IdBruto.GetInt32().ToString() : IdBruto.GetString() ?? "";

    public override string ToString() => Nome;
}

public sealed class CategoriaChamado
{
    [JsonPropertyName("id")] public int Id { get; set; }
    [JsonPropertyName("nome")] public string Nome { get; set; } = "";
    [JsonPropertyName("exige_subcategoria")] public bool ExigeSubcategoria { get; set; }
    public override string ToString() => Nome;
}

public sealed class SubcategoriaChamado
{
    [JsonPropertyName("id")] public int Id { get; set; }
    [JsonPropertyName("nome")] public string Nome { get; set; } = "";
    public override string ToString() => Nome;
}

public sealed class AtivoResumo
{
    [JsonPropertyName("id")] public int Id { get; set; }
    [JsonPropertyName("codigo")] public string Codigo { get; set; } = "";
    [JsonPropertyName("nome")] public string Nome { get; set; } = "";
    [JsonPropertyName("tipo")] public string Tipo { get; set; } = "";
    [JsonPropertyName("unidade_id")] public int UnidadeId { get; set; }
    public override string ToString() => string.IsNullOrEmpty(Tipo) ? $"{Codigo} - {Nome}" : $"{Codigo} - {Nome} ({Tipo})";
}

public sealed class UsuarioAgente
{
    [JsonPropertyName("nome")] public string Nome { get; set; } = "";
    [JsonPropertyName("login")] public string Login { get; set; } = "";
    [JsonPropertyName("email")] public string Email { get; set; } = "";
}

public sealed class FormularioChamado : RespostaSimples
{
    [JsonPropertyName("usuario")] public UsuarioAgente? Usuario { get; set; }
    [JsonPropertyName("categorias")] public List<CategoriaChamado> Categorias { get; set; } = new();
    [JsonPropertyName("subcategorias")] public Dictionary<string, List<SubcategoriaChamado>> Subcategorias { get; set; } = new();
    [JsonPropertyName("setores")] public List<ItemSimples> Setores { get; set; } = new();
    [JsonPropertyName("unidades")] public List<ItemSimples> Unidades { get; set; } = new();
    [JsonPropertyName("prioridades")] public List<ItemSimples> Prioridades { get; set; } = new();
    [JsonPropertyName("este_ativo")] public AtivoResumo? EsteAtivo { get; set; }
}

public sealed class ListaAtivos : RespostaSimples
{
    [JsonPropertyName("ativos")] public List<AtivoResumo> Ativos { get; set; } = new();
}

public sealed class RespostaLogin : RespostaSimples
{
    [JsonPropertyName("token")] public string? Token { get; set; }
    [JsonPropertyName("usuario")] public UsuarioAgente? Usuario { get; set; }
}

public sealed class RespostaAbertura : RespostaSimples
{
    [JsonPropertyName("id")] public int Id { get; set; }
    [JsonPropertyName("numero_controle")] public string NumeroControle { get; set; } = "";
}

public class ChamadoResumo
{
    [JsonPropertyName("id")] public int Id { get; set; }
    [JsonPropertyName("numero")] public string Numero { get; set; } = "";
    [JsonPropertyName("titulo")] public string Titulo { get; set; } = "";
    [JsonPropertyName("categoria")] public string Categoria { get; set; } = "";
    [JsonPropertyName("status")] public string Status { get; set; } = "";
    [JsonPropertyName("status_chave")] public string StatusChave { get; set; } = "";
    [JsonPropertyName("prioridade")] public string Prioridade { get; set; } = "";
    [JsonPropertyName("atendente")] public string Atendente { get; set; } = "";
    [JsonPropertyName("aberto_em")] public string AbertoEm { get; set; } = "";
    [JsonPropertyName("atualizado_em")] public string AtualizadoEm { get; set; } = "";
}

public sealed class ListaChamados : RespostaSimples
{
    [JsonPropertyName("chamados")] public List<ChamadoResumo> Chamados { get; set; } = new();
}

public sealed class MensagemChamado
{
    [JsonPropertyName("quando")] public string Quando { get; set; } = "";
    [JsonPropertyName("autor")] public string Autor { get; set; } = "";
    [JsonPropertyName("do_suporte")] public bool DoSuporte { get; set; }
    [JsonPropertyName("texto")] public string Texto { get; set; } = "";
}

public sealed class ChamadoDetalhe : ChamadoResumo
{
    [JsonPropertyName("descricao")] public string Descricao { get; set; } = "";
    [JsonPropertyName("setor")] public string Setor { get; set; } = "";
    [JsonPropertyName("ativo")] public string Ativo { get; set; } = "";
    [JsonPropertyName("pode_responder")] public bool PodeResponder { get; set; }
    [JsonPropertyName("mensagens")] public List<MensagemChamado> Mensagens { get; set; } = new();
}

public sealed class DetalheResposta : RespostaSimples
{
    [JsonPropertyName("chamado")] public ChamadoDetalhe? Chamado { get; set; }
}
