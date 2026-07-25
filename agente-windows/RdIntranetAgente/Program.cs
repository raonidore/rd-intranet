using System.ComponentModel;
using System.Diagnostics;
using System.Security.Principal;
using System.Threading;
using System.Windows.Forms;
using Microsoft.Win32;

namespace RdIntranetAgente;

internal static class Program
{
    [STAThread]
    private static void Main()
    {
        if (!EstaElevado())
        {
            RelancarComoAdministrador();
            return;
        }

        GarantirLinkedConnections();

        using var mutex = new Mutex(true, "RdIntranetAgente_SingleInstance", out bool criadoAgora);

        if (!criadoAgora)
        {
            MessageBox.Show("O Agente RD Intranet já está em execução (veja o ícone na bandeja, perto do relógio).",
                "RD Intranet", MessageBoxButtons.OK, MessageBoxIcon.Information);
            return;
        }

        ApplicationConfiguration.Initialize();

        using (var splash = new SplashForm())
        {
            Application.Run(splash);
        }

        Application.Run(new TrayApplicationContext());
    }

    /// <summary>
    /// Precisa rodar elevado pra qualquer comando remoto "com elevação"
    /// funcionar sem exigir credencial extra cadastrada por máquina --
    /// sem isso, o agente herda o nível de integridade de quem o iniciou
    /// (Médio, mesmo numa conta administradora), e o Agendador de Tarefas
    /// recusa criar tarefa com /rl highest nesse caso ("Access is denied"),
    /// mesmo com usuário/senha certos de outra conta admin. Confirmado ao
    /// vivo numa VM de teste -- só destravou rodando o próprio agente como
    /// administrador.
    /// </summary>
    private static bool EstaElevado()
    {
        using var identidade = WindowsIdentity.GetCurrent();
        var principal = new WindowsPrincipal(identidade);
        return principal.IsInRole(WindowsBuiltInRole.Administrator);
    }

    /// <summary>
    /// Efeito colateral do agente SEMPRE rodar elevado (ver EstaElevado()
    /// acima): com UAC ativado, o Windows usa "token dividido" -- um
    /// processo elevado e um processo normal do MESMO usuário enxergam
    /// conjuntos DIFERENTES de unidades de rede mapeadas. Uma unidade
    /// mapeada pelo usuário no Explorer (sessão normal) fica invisível
    /// pro nosso agente (sempre elevado) -- confirmado ao vivo: sumiu da
    /// aba "Volumes lógicos" depois que o agente passou a rodar sempre
    /// elevado, mesmo com a unidade continuando conectada de verdade.
    /// Documentado pela própria Microsoft (KB3035277); a correção
    /// oficial é essa chave, que "linka" os dois níveis do token pra
    /// fins de rede. Só passa a valer pra mapeamentos NOVOS (ou depois
    /// de logoff/login) -- mapeamentos já feitos antes da chave existir
    /// continuam invisíveis até o usuário remapear ou relogar.
    /// </summary>
    private static void GarantirLinkedConnections()
    {
        try
        {
            using var chave = Registry.LocalMachine.OpenSubKey(
                @"SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\System", writable: true)
                ?? Registry.LocalMachine.CreateSubKey(
                    @"SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\System");

            var atual = chave?.GetValue("EnableLinkedConnections");
            if (atual == null || Convert.ToInt32(atual) != 1)
            {
                chave?.SetValue("EnableLinkedConnections", 1, RegistryValueKind.DWord);
            }
        }
        catch
        {
            // melhor esforco -- se falhar (permissao, chave bloqueada por
            // GPO, etc.) o agente segue normalmente, so sem esse ajuste
        }
    }

    private static void RelancarComoAdministrador()
    {
        var caminhoExe = Process.GetCurrentProcess().MainModule?.FileName;
        if (string.IsNullOrEmpty(caminhoExe))
        {
            return;
        }

        try
        {
            Process.Start(new ProcessStartInfo(caminhoExe)
            {
                UseShellExecute = true,
                Verb = "runas"
            });
        }
        catch (Win32Exception)
        {
            // Usuario cancelou o prompt do UAC -- nao insiste, so encerra
            // sem rodar sem elevacao nenhuma (rodar sem elevacao reintroduz
            // exatamente o problema que essa checagem existe pra evitar).
        }
    }
}
