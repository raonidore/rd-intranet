using System.ComponentModel;
using System.Diagnostics;
using System.Linq;
using System.Runtime.InteropServices;
using System.Security.Principal;
using System.ServiceProcess;
using System.Threading;
using System.Windows.Forms;
using Microsoft.Win32;

namespace RdIntranetAgente;

internal static class Program
{
    [STAThread]
    private static void Main(string[] args)
    {
        // Disparado pelo Service Control Manager (ver AgenteServico) --
        // nunca por alguém abrindo o .exe na mão. Roda como SYSTEM, nunca
        // passa por elevação/UAC nem abre janela nenhuma: o trabalho dele
        // é só lançar ESTE MESMO .exe (sem --servico) dentro da sessão de
        // quem loga na máquina, já elevado. ServiceBase.Run bloqueia até
        // o SCM mandar parar.
        if (args.Contains("--servico", StringComparer.OrdinalIgnoreCase))
        {
            ServiceBase.Run(new AgenteServico());
            return;
        }

        // Lançado pelo AgenteServico (SessaoUsuario.LancarProcessoNaSessao)
        // -- já chega com o token certo (elevado, se o usuário for admin),
        // então NÃO deve tentar se relançar com "runas" de novo. Só muda
        // o comportamento do aviso de "já está rodando" logo abaixo (não
        // faz sentido incomodar ninguém com um MessageBox por causa de um
        // lançamento automático que apenas encontrou o agente já aberto).
        var modoAutomatico = args.Contains("--auto", StringComparer.OrdinalIgnoreCase);

        if (!EstaElevado())
        {
            if (modoAutomatico)
            {
                // Veio do AgenteServico, mas só conseguiu o token NORMAL do
                // usuário (SessaoUsuario não achou um "linked token" pra
                // essa sessão -- ou seja, esse usuário não é administrador
                // local). Não adianta chamar RelancarComoAdministrador()
                // aqui: isso mostraria de novo o mesmo prompt de UAC que o
                // serviço existe justamente pra evitar (e nessa conta
                // específica, provavelmente pediria uma senha de admin que
                // ninguém tem como digitar). Segue rodando sem elevação --
                // coleta básica funciona igual, só "comandos com elevação"
                // remotos ficam indisponíveis nessa máquina (mesma limitação
                // que já existia pra usuário não-admin antes desse recurso).
                AgenteServico.RegistrarEvento(
                    "Agente iniciado SEM elevação (usuário logado não é administrador local) -- " +
                    "coleta normal funciona, mas comandos remotos com elevação não vão funcionar nesta máquina.",
                    EventLogEntryType.Information);
            }
            else
            {
                RelancarComoAdministrador();
                return;
            }
        }

        GarantirLinkedConnections();
        ConfiancaCertificado.GarantirConfianca();

        using var mutex = new Mutex(true, "RdIntranetAgente_SingleInstance", out bool criadoAgora);

        if (!criadoAgora)
        {
            if (!modoAutomatico)
            {
                MessageBox.Show("O Agente RD Intranet já está em execução (veja o ícone na bandeja, perto do relógio).",
                    "RD Intranet", MessageBoxButtons.OK, MessageBoxIcon.Information);
            }
            return;
        }

        ApplicationConfiguration.Initialize();
        LiberarAvisoDeBandeja();

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

    [DllImport("user32.dll", CharSet = CharSet.Unicode)]
    private static extern uint RegisterWindowMessage(string lpString);

    [DllImport("user32.dll")]
    private static extern bool ChangeWindowMessageFilter(uint message, uint dwFlag);

    /// <summary>
    /// O Explorer avisa "TaskbarCreated" a todos quando a bandeja é
    /// recriada, e o NotifyIcon recoloca o ícone ao receber. Como o agente
    /// roda elevado e o Explorer não, o Windows (UIPI) barra essa
    /// mensagem por padrão -- aqui ela é liberada pro processo inteiro.
    /// </summary>
    private static void LiberarAvisoDeBandeja()
    {
        try
        {
            const uint MSGFLT_ADD = 1;
            ChangeWindowMessageFilter(RegisterWindowMessage("TaskbarCreated"), MSGFLT_ADD);
        }
        catch
        {
            // sem a liberação, o timer da bandeja (TrayApplicationContext) ainda recoloca o ícone
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
