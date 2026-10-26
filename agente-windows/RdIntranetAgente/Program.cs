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

        // Diagnóstico local do monitor de conexões/processos/inicialização:
        // grava o resultado em JSON e sai, sem falar com servidor nenhum.
        // Uso: RdIntranetAgente.exe --teste-seguranca-rede C:\Temp\saida.json
        var indiceCardMesh = Array.FindIndex(args, a => a.Equals("--teste-tela-config", StringComparison.OrdinalIgnoreCase));
        if (indiceCardMesh >= 0)
        {
            DiagnosticoSegurancaRede.PrintCardMesh(args.Length > indiceCardMesh + 1 ? args[indiceCardMesh + 1] : Path.Combine(Path.GetTempPath(), "rd-card-mesh.png"));
            return;
        }

        var indiceAtividade = Array.FindIndex(args, a => a.Equals("--teste-tela-atividade", StringComparison.OrdinalIgnoreCase));
        if (indiceAtividade >= 0)
        {
            MainForm.PrintsAtividade(args.Length > indiceAtividade + 1 ? args[indiceAtividade + 1] : Path.Combine(Path.GetTempPath(), "rd-tela-atividade"));
            return;
        }

        var indicePrints = Array.FindIndex(args, a => a.Equals("--teste-tela-seguranca", StringComparison.OrdinalIgnoreCase));
        if (indicePrints >= 0)
        {
            DiagnosticoSegurancaRede.PrintsDaTela(args.Length > indicePrints + 1 ? args[indicePrints + 1] : Path.Combine(Path.GetTempPath(), "rd-tela-seguranca"));
            return;
        }

        var indiceTeste = Array.FindIndex(args, a => a.Equals("--teste-seguranca-rede", StringComparison.OrdinalIgnoreCase));
        if (indiceTeste >= 0)
        {
            var saida = args.Length > indiceTeste + 1 ? args[indiceTeste + 1] : Path.Combine(Path.GetTempPath(), "rd-teste-seguranca-rede.json");
            var extras = args.Skip(indiceTeste + 2).ToList();
            var conteudo = extras.FirstOrDefault()?.Equals("--simular-varredura", StringComparison.OrdinalIgnoreCase) == true
                ? System.Text.Json.JsonSerializer.Serialize(DiagnosticoSegurancaRede.SimularVarredura(), new System.Text.Json.JsonSerializerOptions { WriteIndented = true, Encoder = System.Text.Encodings.Web.JavaScriptEncoder.UnsafeRelaxedJsonEscaping })
                : extras.Count > 0 ? DiagnosticoSegurancaRede.AnalisarArquivos(extras) : DiagnosticoSegurancaRede.Executar();
            File.WriteAllText(saida, conteudo, System.Text.Encoding.UTF8);
            return;
        }

        // Trilha de acesso: o que seria enviado ao servidor, sem enviar.
        // Uso (como administrador): RdIntranetAgente.exe --teste-trilha C:\Temp	rilha.json
        var indiceTrilha = Array.FindIndex(args, a => a.Equals("--teste-trilha", StringComparison.OrdinalIgnoreCase));
        if (indiceTrilha >= 0)
        {
            var saida = args.Length > indiceTrilha + 1 ? args[indiceTrilha + 1] : Path.Combine(Path.GetTempPath(), "rd-teste-trilha.json");
            File.WriteAllText(saida, System.Text.Json.JsonSerializer.Serialize(AcessosService.LerParaTeste(), new System.Text.Json.JsonSerializerOptions { WriteIndented = true, Encoder = System.Text.Encodings.Web.JavaScriptEncoder.UnsafeRelaxedJsonEscaping }), System.Text.Encoding.UTF8);
            return;
        }

        // Uma conferência do Ambiente padrão e sai (o resultado vai para o portal e para a tela Atividade).
        // Uso: RdIntranetAgente.exe --teste-ambiente <url do portal> <chave> <machine_guid> maquina|usuario
        var indiceAmbiente = Array.FindIndex(args, a => a.Equals("--teste-ambiente", StringComparison.OrdinalIgnoreCase));
        if (indiceAmbiente >= 0 && args.Length > indiceAmbiente + 4)
        {
            var configTeste = new Config { ServerUrl = args[indiceAmbiente + 1], ApiKey = args[indiceAmbiente + 2] };
            AmbientePadrao.AplicarAsync(configTeste, args[indiceAmbiente + 3], args[indiceAmbiente + 4] == "maquina").GetAwaiter().GetResult();
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
            else if (ServicoDoAgenteRodando())
            {
                // Usuário comum abrindo à mão (ou tarefa de logon), com o serviço instalado:
                // roda sem elevação, sem pedir UAC. Antes saía calado e, se o serviço
                // tivesse falhado nessa sessão, o agente ficava fora (EN-AT-01-D, 22/10/2026).
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
        RoteadorRolagem.Instalar(); // rodinha do mouse vai pro que está sob o ponteiro (ver ControlesModernos.cs)
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
    internal static bool ServicoDoAgenteRodando()
    {
        try
        {
            using var controlador = new System.ServiceProcess.ServiceController(AgenteServico.NomeServico);
            return controlador.Status == System.ServiceProcess.ServiceControllerStatus.Running;
        }
        catch
        {
            return false;
        }
    }

    internal static bool EstaElevado()
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
