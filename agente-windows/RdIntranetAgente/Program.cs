using System.ComponentModel;
using System.Diagnostics;
using System.Security.Principal;
using System.Threading;
using System.Windows.Forms;

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
