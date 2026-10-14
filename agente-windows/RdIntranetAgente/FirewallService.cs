using System.Net;
using System.Text.Json;
using System.Text.RegularExpressions;

namespace RdIntranetAgente;

/// <summary>
/// Firewall do Windows: leitura do estado (perfis, padrões, regras criadas
/// pelo RD.Intranet) e as ações pedidas pelo portal -- bloquear programa
/// ou IP, desfazer, desativar serviço, encerrar processo.
///
/// Toda ação é montada AQUI a partir de valores validados (nunca roda
/// texto livre vindo do servidor) e executada elevada pelo mesmo caminho
/// dos comandos remotos (ExploradorService). Regras ficam no grupo
/// "RD.Intranet" com nome "RD.Intranet - Bloqueio ...", fáceis de achar e
/// de remover. Caso que originou (ARIIS, Maurílio, 14/10/2026): firewall
/// DESLIGADO nos 3 perfis -- regra sozinha não valia; foi ligado com
/// entrada e saída liberadas por padrão (nada muda pro resto) só depois de
/// conferir que nenhuma regra de bloqueio existente passaria a valer.
/// </summary>
public static class FirewallService
{
    public const string Grupo = "RD.Intranet";
    public const string PrefixoRegra = "RD.Intranet - Bloqueio";

    /// <summary>Serviços que nunca podem ser desativados por aqui (desligar = máquina inacessível ou sem defesa).</summary>
    private static readonly HashSet<string> ServicosProtegidos = new(StringComparer.OrdinalIgnoreCase)
    {
        "RpcSs", "RpcEptMapper", "DcomLaunch", "LSM", "SamSs", "Winmgmt", "EventLog", "mpssvc", "BFE", "Dhcp", "Dnscache",
        "LanmanServer", "LanmanWorkstation", "TermService", "WinDefend", "Schedule", "ProfSvc", "gpsvc", "CryptSvc", "nsi",
        "Netman", "NlaSvc", "Power", "PlugPlay", "AudioSrv", "W32Time", "WinRM", "SENS", "UserManager", "CoreMessagingRegistrar",
        "RDIntranetAgenteServico", "Mesh Agent"
    };

    /// <summary>Estado atual (só leitura, rápido): perfis, padrões e regras do RD.Intranet.</summary>
    public static Dictionary<string, object?> Estado()
    {
        var estado = new Dictionary<string, object?>();
        try
        {
            var tipo = Type.GetTypeFromProgID("HNetCfg.FwPolicy2");
            if (tipo == null) throw new InvalidOperationException("HNetCfg.FwPolicy2 indisponível");
            dynamic politica = Activator.CreateInstance(tipo)!;

            var perfis = new List<object>();
            foreach (var (codigo, nome) in new[] { (1, "dominio"), (2, "privado"), (4, "publico") })
            {
                perfis.Add(new
                {
                    perfil = nome,
                    ligado = (bool)politica.FirewallEnabled[codigo],
                    entrada_padrao = (int)politica.DefaultInboundAction[codigo] == 0 ? "bloquear" : "permitir",
                    saida_padrao = (int)politica.DefaultOutboundAction[codigo] == 0 ? "bloquear" : "permitir"
                });
            }
            estado["perfis"] = perfis;
            var mascara = (int)politica.CurrentProfileTypes; // soma de bits: 1 domínio, 2 privado, 4 público
            estado["perfil_ativo"] = string.Join(", ", new[] { (1, "dominio"), (2, "privado"), (4, "publico") }.Where(p => (mascara & p.Item1) != 0).Select(p => p.Item2));

            var regrasRd = new List<object>();
            var total = 0;
            var bloqueiosAtivos = 0;
            foreach (dynamic regra in politica.Rules)
            {
                total++;
                string nome = regra.Name ?? "";
                bool habilitada = regra.Enabled;
                int acao = regra.Action;
                if (habilitada && acao == 0) bloqueiosAtivos++;
                if (!nome.StartsWith("RD.Intranet", StringComparison.OrdinalIgnoreCase)) continue;

                regrasRd.Add(new
                {
                    nome,
                    habilitada,
                    direcao = (int)regra.Direction == 1 ? "entrada" : "saida",
                    acao = acao == 0 ? "bloquear" : "permitir",
                    programa = (string?)regra.ApplicationName,
                    ips = (string?)regra.RemoteAddresses is "*" ? null : (string?)regra.RemoteAddresses,
                    descricao = (string?)regra.Description
                });
            }
            estado["regras_total"] = total;
            estado["bloqueios_ativos"] = bloqueiosAtivos;
            estado["regras_rd"] = regrasRd;
        }
        catch (Exception ex)
        {
            estado["erro"] = ex.Message;
        }

        return estado;
    }

    /// <summary>
    /// Bloqueio pedido pelo portal. parametro (JSON): {"tipo":"programa"|"ip",
    /// "valor":"C:\...exe"|"1.2.3.4"|"1.2.3.0/24", "motivo":"...", "por":"..."}.
    /// </summary>
    public static object Bloquear(string? parametro, string? usuarioElevacao, string? senhaElevacao)
    {
        var (tipo, valor, motivo, por) = LerPedido(parametro);
        string filtro;
        string rotulo;

        if (tipo == "programa")
        {
            if (!Path.IsPathRooted(valor) || !File.Exists(valor) || valor.IndexOfAny(new[] { '"', '\'', '`', '$', ';', '|', '&', '\r', '\n' }) >= 0)
            {
                return Falha("Programa não encontrado nesta máquina (o caminho precisa existir).");
            }
            var pastaWindows = Environment.GetFolderPath(Environment.SpecialFolder.Windows);
            if (valor.StartsWith(pastaWindows, StringComparison.OrdinalIgnoreCase) && AnaliseExecutavel.Analisar(valor).Microsoft)
            {
                return Falha("Programa do próprio Windows (assinado pela Microsoft) -- bloquear derrubaria funções do sistema. Bloqueie o IP ou encerre o processo.");
            }
            if (string.Equals(valor, Environment.ProcessPath, StringComparison.OrdinalIgnoreCase))
            {
                return Falha("Não dá pra bloquear o próprio agente do RD.Intranet.");
            }
            filtro = $"-Program '{valor}'";
            rotulo = Path.GetFileName(valor);
        }
        else if (tipo == "ip")
        {
            if (!IpOuRedeValido(valor, out var motivoIp))
            {
                return Falha(motivoIp);
            }
            filtro = $"-RemoteAddress '{valor}'";
            rotulo = valor;
        }
        else
        {
            return Falha("Tipo de bloqueio inválido.");
        }

        var nome = $"{PrefixoRegra} {(tipo == "ip" ? "IP" : "programa")}: {rotulo}";
        var descricao = Limpar($"Criada pelo RD.Intranet em {DateTime.Now:dd/MM/yyyy HH:mm}{(por != "" ? " por " + por : "")}. {motivo}");
        var direcoes = tipo == "ip" ? new[] { "Outbound" } : new[] { "Outbound", "Inbound" };

        var script = $@"
$ErrorActionPreference = 'Stop'
$nome = '{Escapar(nome)}'
$ligou = $false
$perfis = Get-NetFirewallProfile
if (@($perfis | Where-Object {{ -not $_.Enabled }}).Count -gt 0) {{
  $bloqueios = @(Get-NetFirewallRule -Enabled True -Action Block -ErrorAction SilentlyContinue | Where-Object {{ $_.Group -ne '{Grupo}' -and $_.DisplayName -notlike '{PrefixoRegra}*' }})
  if ($bloqueios.Count -gt 0) {{ throw ""O firewall do Windows está desligado e existem $($bloqueios.Count) regra(s) de bloqueio de terceiros que passariam a valer ao ligá-lo (ex.: $($bloqueios[0].DisplayName)). Ligue o firewall manualmente, conferindo essas regras, e repita o bloqueio."" }}
  Set-NetFirewallProfile -Profile Domain,Private,Public -DefaultInboundAction Allow -DefaultOutboundAction Allow -Enabled True
  $ligou = $true
}}
Get-NetFirewallRule -DisplayName ($nome + '*') -ErrorAction SilentlyContinue | Remove-NetFirewallRule
foreach ($d in @({string.Join(",", direcoes.Select(d => $"'{d}'"))})) {{
  $sufixo = if ($d -eq 'Outbound') {{ ' (saída)' }} else {{ ' (entrada)' }}
  New-NetFirewallRule -DisplayName ($nome + $sufixo) -Group '{Grupo}' -Description '{Escapar(descricao)}' -Direction $d {filtro} -Action Block -Profile Any | Out-Null
}}
@{{ regra = $nome; firewall_ligado_agora = $ligou }} | ConvertTo-Json -Compress
";
        return Executar(script, usuarioElevacao, senhaElevacao, $"Bloqueio aplicado: {nome}.");
    }

    /// <summary>parametro: nome da regra (precisa começar com o prefixo do RD.Intranet).</summary>
    public static object Desbloquear(string? parametro, string? usuarioElevacao, string? senhaElevacao)
    {
        var nome = (parametro ?? "").Trim();
        if (!nome.StartsWith(PrefixoRegra, StringComparison.OrdinalIgnoreCase) || nome.Length > 300 || nome.Contains('\'') || nome.Contains('\n'))
        {
            return Falha("Só regras criadas pelo RD.Intranet podem ser removidas por aqui.");
        }

        var semDirecao = Regex.Replace(nome, @"\s\((saída|entrada)\)$", "");
        var script = $@"
$ErrorActionPreference = 'Stop'
$regras = @(Get-NetFirewallRule -DisplayName '{Escapar(semDirecao)}*' -ErrorAction SilentlyContinue)
if ($regras.Count -eq 0) {{ throw 'Regra não encontrada (talvez já removida).' }}
$regras | Remove-NetFirewallRule
@{{ removidas = $regras.Count }} | ConvertTo-Json -Compress
";
        return Executar(script, usuarioElevacao, senhaElevacao, $"Bloqueio removido: {semDirecao}.");
    }

    /// <summary>parametro: nome do serviço do Windows. Para e deixa em "Desativado".</summary>
    public static object DesativarServico(string? parametro, string? usuarioElevacao, string? senhaElevacao)
    {
        var nome = (parametro ?? "").Trim();
        if (!Regex.IsMatch(nome, @"^[A-Za-z0-9_.\- ]{1,80}$"))
        {
            return Falha("Nome de serviço inválido.");
        }
        if (ServicosProtegidos.Contains(nome))
        {
            return Falha($"O serviço {nome} é essencial ao Windows ou ao próprio RD.Intranet e não pode ser desativado por aqui.");
        }

        var script = $@"
$ErrorActionPreference = 'Stop'
$s = Get-Service -Name '{nome}'
Set-Service -Name '{nome}' -StartupType Disabled
if ($s.Status -ne 'Stopped') {{ Stop-Service -Name '{nome}' -Force -ErrorAction SilentlyContinue }}
Start-Sleep -Seconds 2
$s = Get-Service -Name '{nome}'
@{{ servico = $s.Name; estado = [string]$s.Status; inicio = [string]$s.StartType }} | ConvertTo-Json -Compress
";
        return Executar(script, usuarioElevacao, senhaElevacao, $"Serviço {nome} desativado.");
    }

    /// <summary>parametro (JSON): {"pid":123, "caminho":"C:\...exe"} -- o caminho confere que o PID ainda é o mesmo programa.</summary>
    public static object EncerrarProcesso(string? parametro, string? usuarioElevacao, string? senhaElevacao)
    {
        int pid;
        string caminho;
        try
        {
            using var json = JsonDocument.Parse(parametro ?? "{}");
            pid = json.RootElement.GetProperty("pid").GetInt32();
            caminho = json.RootElement.TryGetProperty("caminho", out var c) ? c.GetString() ?? "" : "";
        }
        catch
        {
            return Falha("Pedido inválido.");
        }

        if (pid <= 4 || pid == Environment.ProcessId || caminho.Contains('\''))
        {
            return Falha("Processo protegido ou inválido.");
        }

        var script = $@"
$ErrorActionPreference = 'Stop'
$p = Get-Process -Id {pid} -ErrorAction SilentlyContinue
if (-not $p) {{ throw 'O processo já não está rodando.' }}
$caminho = '{Escapar(caminho)}'
if ($caminho -ne '' -and $p.Path -and $p.Path -ne $caminho) {{ throw ""O PID {pid} agora é outro programa ($($p.Path)) -- atualize a tela antes."" }}
Stop-Process -Id {pid} -Force
@{{ encerrado = {pid}; programa = $p.Path }} | ConvertTo-Json -Compress
";
        return Executar(script, usuarioElevacao, senhaElevacao, $"Processo {pid} encerrado.");
    }

    // ------------------------------------------------------------------

    private static object Executar(string script, string? usuarioElevacao, string? senhaElevacao, string mensagemSucesso)
    {
        var (saida, erro, codigo) = ExploradorService.ExecutarPowerShell(script, true, usuarioElevacao, senhaElevacao);
        if (codigo != 0 || string.IsNullOrWhiteSpace(saida))
        {
            return Falha(MensagemDeErro(erro, codigo));
        }

        object? detalhes = null;
        try { detalhes = JsonSerializer.Deserialize<Dictionary<string, object?>>(saida.Trim().Split('\n').Last()); } catch { }
        return new { sucesso = true, mensagem = mensagemSucesso, detalhes, firewall = Estado() };
    }

    private static object Falha(string mensagem) => new { sucesso = false, mensagem };

    /// <summary>stderr do PowerShell vem em CLIXML (com ruído de "Preparing modules"): pega só as linhas de erro.</summary>
    private static string MensagemDeErro(string? erro, int codigo)
    {
        erro ??= "";
        var linhas = Regex.Matches(erro, @"<S S=""Error"">(.*?)</S>", RegexOptions.Singleline)
            .Select(m => WebUtility.HtmlDecode(m.Groups[1].Value).Replace("_x000D_", "").Replace("_x000A_", " ").Trim())
            .Where(l => l.Length > 0)
            .ToList();
        var texto = linhas.Count > 0 ? string.Join(" ", linhas) : Regex.Replace(Regex.Replace(erro, "<[^>]+>", " "), @"#< CLIXML|\s+", " ").Trim();
        // A primeira linha do erro do PowerShell já é a mensagem; o resto é "No linha:... + CategoryInfo".
        texto = Regex.Split(texto, @"\s(No linha|At line|At C:|\+ CategoryInfo|\+ FullyQualifiedErrorId)")[0].Trim();
        return texto.Length > 0 ? texto[..Math.Min(500, texto.Length)] : $"O comando falhou (código {codigo}).";
    }

    private static (string tipo, string valor, string motivo, string por) LerPedido(string? parametro)
    {
        try
        {
            using var json = JsonDocument.Parse(parametro ?? "{}");
            var r = json.RootElement;
            string Texto(string nome) => r.TryGetProperty(nome, out var v) && v.ValueKind == JsonValueKind.String ? v.GetString()!.Trim() : "";
            return (Texto("tipo"), Texto("valor"), Limpar(Texto("motivo")), Limpar(Texto("por")));
        }
        catch
        {
            return ("", "", "", "");
        }
    }

    private static bool IpOuRedeValido(string valor, out string motivo)
    {
        motivo = "IP inválido (use 1.2.3.4 ou 1.2.3.0/24).";
        var partes = valor.Split('/');
        if (partes.Length > 2 || !IPAddress.TryParse(partes[0], out var ip)) return false;
        if (partes.Length == 2 && (!int.TryParse(partes[1], out var prefixo) || prefixo < (ip.AddressFamily == System.Net.Sockets.AddressFamily.InterNetwork ? 8 : 32) || prefixo > (ip.AddressFamily == System.Net.Sockets.AddressFamily.InterNetwork ? 32 : 128))) return false;
        if (IPAddress.IsLoopback(ip) || ip.Equals(IPAddress.Any) || ip.Equals(IPAddress.IPv6Any))
        {
            motivo = "Esse endereço não pode ser bloqueado.";
            return false;
        }
        return true;
    }

    private static string Limpar(string texto)
    {
        var limpo = Regex.Replace(texto, @"[\r\n`$""]", " ").Trim();
        return limpo.Length > 400 ? limpo[..400] : limpo;
    }

    private static string Escapar(string texto) => texto.Replace("'", "''");
}
