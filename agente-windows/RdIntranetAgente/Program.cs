using System.Threading;
using System.Windows.Forms;

namespace RdIntranetAgente;

internal static class Program
{
    [STAThread]
    private static void Main()
    {
        using var mutex = new Mutex(true, "RdIntranetAgente_SingleInstance", out bool criadoAgora);

        if (!criadoAgora)
        {
            MessageBox.Show("O Agente RD Intranet já está em execução (veja o ícone na bandeja, perto do relógio).",
                "RD Intranet", MessageBoxButtons.OK, MessageBoxIcon.Information);
            return;
        }

        ApplicationConfiguration.Initialize();
        Application.Run(new TrayApplicationContext());
    }
}
