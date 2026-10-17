using System.Diagnostics;

namespace RdIntranetAgente;

/// <summary>
/// Endurece o que é do agente e do acesso remoto da RD, uma vez a cada
/// abertura (precisa do agente elevado):
///
///  1. Pasta de instalação (C:\RDTecnologia): criada na raiz do C:, herda
///     do Windows "Modificar" para Usuários autenticados -- qualquer usuário
///     trocaria o RdIntranetAgente.exe, que roda como administrador/SYSTEM
///     (achado na EN-TI-01-D, 17/10/2026). Fica só Administradores e SYSTEM
///     gravando; Usuários com leitura e execução.
///  2. Pasta do instalador do MeshAgent (%ProgramData%\RDIntranetAgent\MeshInstall),
///     com a mesma permissão -- antes ficava no AppData do usuário.
///  3. Exclusões do Defender: C:\Program Files\Mesh Agent\ (só administrador
///     grava) e o MeshAgent.exe, e a pasta do instalador. O Defender marca o
///     MeshAgent como "Behavior:Win32/Persistence.A!ml" (se instala como
///     serviço, inclusive no Modo de Segurança, com certificado próprio do
///     MeshCentral) e apagava o instalador e o MeshAgent já instalado.
///
/// Exclusão só em pasta que usuário comum não grava -- senão viraria
/// esconderijo pra malware. Tudo best-effort: Defender gerenciado por
/// política, outro antivírus ou falha de permissão só ficam no log.
/// </summary>
public static class ProtecaoInstalacao
{
    public const string PastaMeshAgent = @"C:\Program Files\Mesh Agent\";
    private const string PastaInstalacaoPadrao = @"C:\RDTecnologia";

    public static string PastaInstaladorMesh => Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData), "RDIntranetAgent", "MeshInstall");

    private static int _feito;

    public static void GarantirEmSegundoPlano() => Task.Run(Garantir);

    public static void Garantir()
    {
        if (!Program.EstaElevado() || Interlocked.Exchange(ref _feito, 1) == 1) return;

        var pastaAgente = Path.GetDirectoryName(Environment.ProcessPath ?? "") ?? "";
        // Só a pasta padrão do instalador: rodando de outro lugar (Downloads, pendrive) não mexe.
        if (string.Equals(pastaAgente.TrimEnd('\\'), PastaInstalacaoPadrao, StringComparison.OrdinalIgnoreCase))
        {
            TravarPasta(pastaAgente, "pasta do agente");
        }

        // Conserta a pasta padrão mesmo quando o agente está rodando de outro
        // lugar: é o caso de quem, com o exe travado pela 1.0.74, passou a
        // rodar o agente de Downloads (o serviço continua apontando pra cá).
        else if (Directory.Exists(PastaInstalacaoPadrao))
        {
            TravarPasta(PastaInstalacaoPadrao, "pasta do agente");
        }

        RemoverExclusoesIndevidas();

        Directory.CreateDirectory(PastaInstaladorMesh);
        TravarPasta(PastaInstaladorMesh, "pasta do instalador do MeshAgent");

        var (codigo, saida) = Rodar("powershell.exe",
            "-NoProfile -WindowStyle Hidden -Command \"" +
            $"Add-MpPreference -ExclusionPath '{PastaMeshAgent}','{PastaInstaladorMesh}\\' -ErrorAction Stop; " +
            $"Add-MpPreference -ExclusionProcess '{PastaMeshAgent}MeshAgent.exe' -ErrorAction Stop\"");
        if (codigo == 0)
        {
            LogAtividade.Registrar(NivelAtividade.Seguranca, "ACESSO REMOTO", "Exclusões do Defender garantidas para o MeshAgent da RD (o Defender o confunde com persistência de malware).");
        }
        else if (!saida.Contains("not recognized", StringComparison.OrdinalIgnoreCase))
        {
            LogAtividade.Registrar(NivelAtividade.Aviso, "ACESSO REMOTO", "Não consegui ajustar as exclusões do Defender (política ou outro antivírus): " + saida.Trim());
        }
    }

    /// <summary>Exclusão temporária do processo do instalador (o Defender bloqueia o comportamento dele, não só o arquivo).</summary>
    public static void ExcluirProcessoTemporario(string exe, bool adicionar) =>
        Rodar("powershell.exe", $"-NoProfile -WindowStyle Hidden -Command \"{(adicionar ? "Add" : "Remove")}-MpPreference -ExclusionProcess '{exe}' -ErrorAction SilentlyContinue\"");

    /// <summary>
    /// Administradores e SYSTEM controle total, Usuários ler/executar, sem
    /// herdar do pai (pelos SIDs: funciona em qualquer idioma).
    ///
    /// Em duas etapas de propósito. A 1.0.74 fazia tudo num comando só com
    /// /T: em cada ARQUIVO o /inheritance:r tirava as permissões herdadas e o
    /// /grant:r com (OI)(CI) -- que só vale pra pasta -- falhava, e o /C
    /// seguia em frente. O RdIntranetAgente.exe ficava com a lista de
    /// permissões VAZIA: nem o SYSTEM lia, o serviço não subia mais e
    /// parecia que "algo apagava o agente" (MDA-PC-0010, 17/10/2026).
    /// Agora: ACL explícita só na pasta; o conteúdo volta a herdar dela
    /// (/reset), o que também conserta quem já tinha ficado travado.
    /// </summary>
    private static void TravarPasta(string pasta, string descricao)
    {
        var raiz = pasta.TrimEnd('\\');

        // Dono = Administradores: garante que dá pra reescrever a ACL de arquivo que ficou sem nenhuma permissão.
        Rodar("icacls.exe", $"\"{raiz}\" /setowner *S-1-5-32-544 /T /C /Q");

        var (codigo, saida) = Rodar("icacls.exe",
            $"\"{raiz}\" /inheritance:r /grant:r *S-1-5-32-544:(OI)(CI)F *S-1-5-18:(OI)(CI)F *S-1-5-32-545:(OI)(CI)RX /C /Q");
        if (codigo != 0)
        {
            LogAtividade.Registrar(NivelAtividade.Aviso, "SEGURANÇA", $"Não consegui restringir a permissão da {descricao}: {saida.Trim()}");
            return;
        }

        if (Directory.EnumerateFileSystemEntries(raiz).Any())
        {
            var (codigoFilhos, saidaFilhos) = Rodar("icacls.exe", $"\"{raiz}\\*\" /reset /T /C /Q");
            if (codigoFilhos != 0)
            {
                LogAtividade.Registrar(NivelAtividade.Aviso, "SEGURANÇA", $"Não consegui normalizar a permissão dos arquivos da {descricao}: {saidaFilhos.Trim()}");
            }
        }
    }

    /// <summary>
    /// Versões até a 1.0.74 excluíam do Defender a pasta %TEMP%\RDIntranetAgenteUpdate
    /// do usuário e a pasta de onde o agente rodava (inclusive Downloads) a
    /// cada atualização pela bandeja -- pasta que usuário comum grava não pode
    /// ficar fora do antivírus. Remove essas exclusões; as da pasta padrão
    /// (já travada) e do MeshAgent ficam.
    /// </summary>
    private static void RemoverExclusoesIndevidas()
    {
        var (codigo, saida) = Rodar("powershell.exe",
            "-NoProfile -WindowStyle Hidden -Command \"" +
            "$r = @(); foreach ($p in @((Get-MpPreference).ExclusionPath)) { " +
            "if ($p -and ($p -match '\\\\RDIntranetAgenteUpdate\\\\?$' -or ($p -match '^[A-Za-z]:\\\\Users\\\\' -and (Test-Path (Join-Path $p 'RdIntranetAgente.exe'))))) { " +
            "Remove-MpPreference -ExclusionPath $p -ErrorAction SilentlyContinue; $r += $p } }; $r -join '; '\"");
        if (codigo == 0 && !string.IsNullOrWhiteSpace(saida))
        {
            LogAtividade.Registrar(NivelAtividade.Seguranca, "SEGURANÇA", "Exclusões do Defender em pasta de usuário removidas: " + saida.Trim());
        }
    }

    private static (int codigo, string saida) Rodar(string exe, string argumentos)
    {
        try
        {
            using var p = Process.Start(new ProcessStartInfo(exe, argumentos)
            {
                UseShellExecute = false,
                CreateNoWindow = true,
                RedirectStandardOutput = true,
                RedirectStandardError = true,
            })!;
            var saida = p.StandardOutput.ReadToEnd() + p.StandardError.ReadToEnd();
            p.WaitForExit(30000);
            return (p.ExitCode, saida);
        }
        catch (Exception ex)
        {
            return (-1, ex.Message);
        }
    }
}
