using System.Text.Json;
using System.Text.RegularExpressions;
using Microsoft.Win32;

namespace RdIntranetAgente;

public sealed record ProgramaConfiavel(string Id, string Caminho, string Descricao, string Portas, string Por, DateTime Quando);

/// <summary>
/// Programas marcados como CONFIÁVEIS nesta máquina (ex.: ArDrivers_ACL do
/// laboratório, que recebe os equipamentos nas portas 8234/9009). Confiar:
///  - cria regras de PERMISSÃO no firewall do Windows (entrada e saída do
///    programa; se houver portas, a entrada fica liberada também por porta),
///    no grupo "RD.Intranet" e com a descrição -- o programa continua
///    funcionando mesmo que alguém deixe a entrada padrão em "bloquear";
///  - tira o programa da análise de risco (não vira "atenção"/"suspeito",
///    e a tela mostra "confiável: descrição").
/// Guardado em HKLM\SOFTWARE\RDIntranet\Confiaveis (sobrevive a atualização).
/// </summary>
public static class ConfiaveisService
{
    private const string Chave = @"SOFTWARE\RDIntranet\Confiaveis";
    public const string PrefixoRegra = "RD.Intranet - Permitido";

    private static readonly object Trava = new();
    private static Dictionary<string, ProgramaConfiavel>? _cache;
    private static DateTime _lidoEm;

    public static List<ProgramaConfiavel> Listar()
    {
        lock (Trava)
        {
            if (_cache == null || DateTime.Now - _lidoEm > TimeSpan.FromSeconds(60))
            {
                _cache = new(StringComparer.OrdinalIgnoreCase);
                using var raiz = Registry.LocalMachine.OpenSubKey(Chave);
                foreach (var id in raiz?.GetSubKeyNames() ?? Array.Empty<string>())
                {
                    using var item = raiz!.OpenSubKey(id);
                    var caminho = item?.GetValue("Caminho") as string;
                    if (string.IsNullOrEmpty(caminho)) continue;
                    _cache[caminho] = new ProgramaConfiavel(id, caminho, item!.GetValue("Descricao") as string ?? "", item.GetValue("Portas") as string ?? "",
                        item.GetValue("Por") as string ?? "", DateTime.TryParse(item.GetValue("Quando") as string, out var q) ? q : DateTime.MinValue);
                }
                _lidoEm = DateTime.Now;
            }
            return _cache.Values.OrderBy(c => c.Caminho).ToList();
        }
    }

    /// <summary>Id dos itens que vêm do portal (não dá pra "deixar de confiar" pela máquina).</summary>
    public const string IdGlobal = "global";

    // Programas confiáveis em TODAS as máquinas do cliente: vêm no heartbeat (agente 1.0.66+).
    private static List<(Regex regex, ProgramaConfiavel item)> _globais = new();
    private static string _assinaturaGlobais = "";

    public static void DefinirGlobais(List<ConfiavelGlobal> lista)
    {
        var assinatura = string.Join("\n", lista.Select(c => c.Padrao + "|" + c.Descricao));
        if (assinatura == _assinaturaGlobais) return;

        var novos = new List<(Regex, ProgramaConfiavel)>();
        foreach (var c in lista.Where(c => !string.IsNullOrWhiteSpace(c.Padrao)))
        {
            var padrao = Normalizar(c.Padrao);
            var regex = new Regex("^" + Regex.Escape(padrao).Replace("\\*", ".*") + "$", RegexOptions.IgnoreCase | RegexOptions.CultureInvariant);
            novos.Add((regex, new ProgramaConfiavel(IdGlobal, padrao, c.Descricao, "", "Portal (todas as máquinas)", DateTime.MinValue)));
        }
        lock (Trava)
        {
            _globais = novos;
            _assinaturaGlobais = assinatura;
        }
        LogAtividade.Registrar(NivelAtividade.Seguranca, "SEGURANÇA", $"Programas confiáveis do cliente atualizados pelo portal: {novos.Count} item(ns).");
    }

    public static IReadOnlyList<ProgramaConfiavel> Globais()
    {
        lock (Trava) { return _globais.Select(g => g.item).ToList(); }
    }

    /// <summary>Confiável pelo caminho do executável (null se não for): primeiro o "Confiar" desta máquina, depois a lista do cliente.</summary>
    public static ProgramaConfiavel? Buscar(string? caminho)
    {
        if (string.IsNullOrEmpty(caminho)) return null;
        Listar();
        lock (Trava)
        {
            if (_cache!.TryGetValue(caminho, out var c)) return c;
            var normalizado = Normalizar(caminho);
            foreach (var (regex, item) in _globais)
            {
                if (regex.IsMatch(normalizado)) return item;
            }
            return null;
        }
    }

    /// <summary>Mesmo critério do portal: aspas fora, "\\?\" fora, "\.\" e barras repetidas colapsados.</summary>
    private static string Normalizar(string caminho)
    {
        caminho = caminho.Trim().Trim('"', '\'');
        if (caminho.StartsWith(@"\\?\")) caminho = caminho[4..];
        caminho = caminho.Replace('/', '\\');
        caminho = Regex.Replace(caminho, @"\\\.(?=\\)", "");
        return Regex.Replace(caminho, @"\\{2,}", @"\");
    }

    /// <summary>portas: "8234, 9009" (TCP, entrada) -- opcional.</summary>
    public static ResultadoAcao Confiar(string caminho, string descricao, string portas, string por)
    {
        caminho = caminho.Trim().Trim('"');
        descricao = Regex.Replace(descricao ?? "", @"[\r\n'`$""]", " ").Trim();
        if (descricao.Length > 300) descricao = descricao[..300];
        if (!File.Exists(caminho) || !Path.IsPathRooted(caminho) || caminho.IndexOfAny(new[] { '\'', '`', '$', '"', '\r', '\n' }) >= 0)
        {
            return new ResultadoAcao(false, $"Arquivo não encontrado nesta máquina: {caminho}");
        }
        if (descricao.Length < 3) return new ResultadoAcao(false, "Escreva para que serve o programa (fica na regra e na lista de confiáveis).");

        var listaPortas = (portas ?? "").Split(new[] { ',', ';', ' ' }, StringSplitOptions.RemoveEmptyEntries)
            .Select(p => int.TryParse(p, out var n) ? n : 0).Where(n => n is > 0 and < 65536).Distinct().Take(20).ToList();
        var portasTexto = string.Join(",", listaPortas);
        var nome = $"{PrefixoRegra}: {Path.GetFileName(caminho)}";
        var desc = $"{descricao} -- confiável pelo RD.Intranet em {DateTime.Now:dd/MM/yyyy HH:mm}{(string.IsNullOrEmpty(por) ? "" : " por " + Regex.Replace(por, @"[\r\n'`$""]", " "))}";

        var script = $@"
$ErrorActionPreference = 'Stop'
$nome = '{nome.Replace("'", "''")}'
Get-NetFirewallRule -DisplayName ($nome + '*') -ErrorAction SilentlyContinue | Remove-NetFirewallRule
New-NetFirewallRule -DisplayName ($nome + ' (entrada)') -Group '{FirewallService.Grupo}' -Description '{desc.Replace("'", "''")}' -Direction Inbound -Program '{caminho}' -Action Allow -Profile Any | Out-Null
New-NetFirewallRule -DisplayName ($nome + ' (saída)') -Group '{FirewallService.Grupo}' -Description '{desc.Replace("'", "''")}' -Direction Outbound -Program '{caminho}' -Action Allow -Profile Any | Out-Null
{(listaPortas.Count > 0 ? $"New-NetFirewallRule -DisplayName ($nome + ' (portas {portasTexto})') -Group '{FirewallService.Grupo}' -Description '{desc.Replace("'", "''")}' -Direction Inbound -Protocol TCP -LocalPort {portasTexto} -Action Allow -Profile Any | Out-Null" : "")}
'ok'";
        var ehPrograma = Path.GetExtension(caminho).ToLowerInvariant() is ".exe" or ".com" or ".scr";
        if (ehPrograma)
        {
            var (saida, erro, codigo) = ExploradorService.ExecutarPowerShell(script, !Program.EstaElevado());
            if (codigo != 0 || !saida.Contains("ok")) return new ResultadoAcao(false, FirewallService.MensagemDeErro(erro, codigo));
        }

        var id = Convert.ToHexString(System.Security.Cryptography.SHA1.HashData(System.Text.Encoding.UTF8.GetBytes(caminho.ToLowerInvariant())))[..16];
        using (var item = Registry.LocalMachine.CreateSubKey($@"{Chave}\{id}"))
        {
            item.SetValue("Caminho", caminho);
            item.SetValue("Descricao", descricao);
            item.SetValue("Portas", portasTexto);
            item.SetValue("Por", por ?? "");
            item.SetValue("Quando", DateTime.Now.ToString("s"));
            item.SetValue("Sha256", AnaliseExecutavel.Sha256(caminho) ?? "");
        }
        lock (Trava) { _cache = null; }
        LogAtividade.Registrar(NivelAtividade.Seguranca, "SEGURANÇA", $"Programa marcado como confiável: {caminho} ({descricao}){(portasTexto != "" ? $"; portas liberadas: {portasTexto}" : "")}.");
        if (!ehPrograma) return new ResultadoAcao(true, $"{Path.GetFileName(caminho)} agora é confiável: sai da análise de risco (script não precisa de regra no firewall).");
        return new ResultadoAcao(true, $"{Path.GetFileName(caminho)} agora é confiável: liberado no firewall (entrada e saída{(portasTexto != "" ? $", portas {portasTexto}" : "")}) e fora da análise de risco.");
    }

    public static ResultadoAcao Desconfiar(string caminho)
    {
        var item = Buscar(caminho);
        if (item == null) return new ResultadoAcao(false, "Esse programa não está na lista de confiáveis.");
        var nome = $"{PrefixoRegra}: {Path.GetFileName(caminho)}";
        var (_, erro, codigo) = ExploradorService.ExecutarPowerShell(
            $"Get-NetFirewallRule -DisplayName '{nome.Replace("'", "''")}*' -ErrorAction SilentlyContinue | Remove-NetFirewallRule; 'ok'", !Program.EstaElevado());
        if (codigo != 0) return new ResultadoAcao(false, FirewallService.MensagemDeErro(erro, codigo));
        using (var raiz = Registry.LocalMachine.OpenSubKey(Chave, writable: true)) raiz?.DeleteSubKeyTree(item.Id, false);
        lock (Trava) { _cache = null; }
        LogAtividade.Registrar(NivelAtividade.Seguranca, "SEGURANÇA", $"Programa deixou de ser confiável: {caminho}.");
        return new ResultadoAcao(true, $"{Path.GetFileName(caminho)} saiu da lista de confiáveis (regras de permissão removidas).");
    }

    /// <summary>Portas em que o programa está esperando conexão agora (pra preencher o "Confiar...").</summary>
    public static string PortasEmUso(string caminho)
    {
        try
        {
            var conexoes = TabelaTcp.Ler();
            var processos = CatalogoProcessos.Obter(conexoes.Select(c => c.Pid).Distinct());
            var pids = processos.Values.Where(p => string.Equals(p.Caminho, caminho, StringComparison.OrdinalIgnoreCase)).Select(p => p.Pid).ToHashSet();
            // Portas locais de servidor: as que escutam e as que recebem conexão de fora (porta local < 49152 = não efêmera).
            return string.Join(", ", conexoes.Where(c => pids.Contains(c.Pid) && (c.Estado == "LISTEN" || (c.Estado == "ESTABLISHED" && c.PortaLocal < 49152)) && !TabelaTcp.EhLocal(c.Local))
                .Select(c => c.PortaLocal).Distinct().OrderBy(p => p).Take(10));
        }
        catch
        {
            return "";
        }
    }

    /// <summary>Pro retrato do portal.</summary>
    public static object ParaEnvio() => Listar().Select(c => new { caminho = c.Caminho, descricao = c.Descricao, portas = c.Portas, por = c.Por, quando = c.Quando.ToString("s") }).ToList();
}
