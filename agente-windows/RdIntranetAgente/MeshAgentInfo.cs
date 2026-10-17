using System.Diagnostics;
using System.ServiceProcess;
using Microsoft.Win32;

namespace RdIntranetAgente;

/// <summary>
/// O que o instalador do MeshCentral mostra em "Connection Details", lido
/// direto da máquina: o MeshAgent.msh (grupo, servidor), o registro
/// (HKLM\SOFTWARE\Open Source\Mesh Agent), o serviço "Mesh Agent" e a
/// versão do executável -- mais o que o instalador não mostra: se está
/// conectado ao servidor agora e se é o MeshAgent do MeshCentral da RD
/// (pela assinatura, ver AnaliseExecutavel.DefinirAssinantesConfiaveis).
/// Card "Acesso remoto" em Configurações.
/// </summary>
public sealed class MeshAgentInfo
{
    public const string Pasta = @"C:\Program Files\Mesh Agent";

    public bool Instalado { get; private init; }
    /// <summary>Executando | Parado | Iniciando... | Não instalado</summary>
    public string Status { get; private init; } = "Não instalado";
    public bool Executando { get; private init; }
    public bool? Conectado { get; private init; }
    public string Versao { get; private init; } = "";
    public string UrlServidor { get; private init; } = "";
    public string IdServidor { get; private init; } = "";
    public string NomeGrupo { get; private init; } = "";
    public string IdGrupo { get; private init; } = "";
    public string NomeMaquina { get; private init; } = Environment.MachineName;
    /// <summary>"MeshCentral da RD (acesso remoto)" quando a assinatura é do nosso servidor; senão o assinante como o Windows vê.</summary>
    public string Assinatura { get; private init; } = "";
    public bool DaRd { get; private init; }

    public static MeshAgentInfo Ler()
    {
        var exe = Path.Combine(Pasta, "MeshAgent.exe");
        string? nomeServico = null;
        ServiceControllerStatus? estado = null;
        try
        {
            foreach (var s in ServiceController.GetServices())
            {
                using (s)
                {
                    if ((s.ServiceName + " " + s.DisplayName).Contains("Mesh Agent", StringComparison.OrdinalIgnoreCase))
                    {
                        nomeServico = s.ServiceName;
                        estado = s.Status;
                        break;
                    }
                }
            }
        }
        catch
        {
            // Service Control Manager indisponível -- segue só com os arquivos.
        }

        if (nomeServico == null && !File.Exists(exe))
        {
            return new MeshAgentInfo();
        }

        var msh = LerMsh(Path.Combine(Pasta, "MeshAgent.msh"));
        using var registro = Registry.LocalMachine.OpenSubKey(@"SOFTWARE\Open Source\Mesh Agent");
        string Valor(string chaveMsh, string chaveRegistro) =>
            msh.TryGetValue(chaveMsh, out var v) && v != "" ? v : registro?.GetValue(chaveRegistro)?.ToString() ?? "";

        var url = Valor("MeshServer", "MeshServerUrl");
        var executando = estado == ServiceControllerStatus.Running;
        var analise = File.Exists(exe) ? AnaliseExecutavel.Analisar(exe) : null;
        var daRd = analise?.Assinatura == "valida" && (analise.Assinante ?? "").Contains("MeshCentral da RD", StringComparison.OrdinalIgnoreCase);

        return new MeshAgentInfo
        {
            Instalado = true,
            Executando = executando,
            Status = estado switch
            {
                ServiceControllerStatus.Running => "Executando",
                ServiceControllerStatus.Stopped => "Parado",
                null => "Serviço não encontrado",
                _ => estado.ToString()!
            },
            Conectado = executando ? ConectadoAoServidor(url) : false,
            Versao = File.Exists(exe) ? FileVersionInfo.GetVersionInfo(exe).FileVersion ?? "" : registro?.GetValue("CommitDate")?.ToString() ?? "",
            UrlServidor = url,
            IdServidor = Valor("ServerID", "MeshServerId"),
            NomeGrupo = msh.TryGetValue("MeshName", out var grupo) ? grupo : "",
            IdGrupo = (msh.TryGetValue("MeshID", out var idGrupo) ? idGrupo : "").Replace("0x", "", StringComparison.OrdinalIgnoreCase),
            Assinatura = analise == null ? "" : daRd ? "MeshCentral da RD" : analise.Assinante ?? "sem assinatura",
            DaRd = daRd,
        };
    }

    /// <summary>Linhas "Chave=valor" do .msh (o "translation=" enorme é ignorado).</summary>
    private static Dictionary<string, string> LerMsh(string caminho)
    {
        var valores = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);
        try
        {
            foreach (var linha in File.ReadLines(caminho))
            {
                var igual = linha.IndexOf('=');
                if (igual <= 0 || linha.StartsWith("translation", StringComparison.OrdinalIgnoreCase)) continue;
                valores[linha[..igual].Trim()] = linha[(igual + 1)..].Trim();
            }
        }
        catch
        {
            // .msh ausente (algumas versões só guardam no MeshAgent.db) -- fica com o registro.
        }
        return valores;
    }

    /// <summary>Alguma conexão TCP estabelecida de um processo MeshAgent para a porta do servidor.</summary>
    private static bool? ConectadoAoServidor(string url)
    {
        try
        {
            var porta = Uri.TryCreate(url, UriKind.Absolute, out var uri) ? uri.Port : 443;
            var pids = Process.GetProcessesByName("MeshAgent").Select(p => { using (p) return p.Id; }).ToHashSet();
            if (pids.Count == 0) return false;
            return TabelaTcp.Ler().Any(c => pids.Contains(c.Pid) && c.Estado == "ESTABLISHED" && c.PortaRemota == porta);
        }
        catch
        {
            return null;
        }
    }

    /// <summary>Reinicia o serviço (resolve tela remota travada -- ver o caso do "MeshAgent -kvm1" preso).</summary>
    public static void ReiniciarServico()
    {
        foreach (var s in ServiceController.GetServices())
        {
            using (s)
            {
                if (!(s.ServiceName + " " + s.DisplayName).Contains("Mesh Agent", StringComparison.OrdinalIgnoreCase)) continue;
                if (s.Status != ServiceControllerStatus.Stopped)
                {
                    s.Stop();
                    s.WaitForStatus(ServiceControllerStatus.Stopped, TimeSpan.FromSeconds(20));
                }
                // Sessão de tela remota presa ("MeshAgent -kvm1") sobrevive ao Stop do serviço.
                foreach (var resto in Process.GetProcessesByName("MeshAgent"))
                {
                    using (resto) try { resto.Kill(); } catch { }
                }
                s.Start();
                s.WaitForStatus(ServiceControllerStatus.Running, TimeSpan.FromSeconds(20));
                return;
            }
        }
        throw new InvalidOperationException("Serviço do MeshAgent não encontrado.");
    }
}
