using System.Diagnostics;
using System.ServiceProcess;

namespace RdIntranetAgente;

/// <summary>
/// Serviço do Windows (LocalSystem, instalado/removido pelo item de menu
/// "Instalar serviço do Windows" da bandeja -- ver TrayApplicationContext).
/// Roda como SYSTEM desde o boot, sem depender de login nenhum, e nunca
/// passa pelo fluxo de consentimento do UAC (serviços estão fora do
/// modelo de elevação interativa). Único trabalho dele: quando alguém
/// loga numa sessão interativa, lançar o MESMO .exe (modo bandeja normal,
/// sem --servico) DENTRO daquela sessão, usando SessaoUsuario pra pegar o
/// token administrativo do usuário quando ele for admin local -- o
/// resultado é o agente abrindo já elevado, sem nenhum prompt.
///
/// Resolve o caso que a tarefa agendada (RegistrarInicioAutomatico, ainda
/// o caminho padrão) não cobre: quando o usuário logado NÃO é
/// administrador local, "RunLevel HighestAvailable" da tarefa não tem
/// como elevar (não existe token admin pra essa conta), então o agente
/// caía no runas manual de Program.cs e ficava esperando alguém clicar
/// "Sim" -- em máquina desatendida, nunca abria. O serviço é OPCIONAL:
/// instalar não desliga a tarefa agendada, os dois convivem (o Mutex em
/// Program.cs evita abrir duas instâncias na mesma sessão).
/// </summary>
public class AgenteServico : ServiceBase
{
    public const string NomeServico = "RDIntranetAgenteServico";
    public const string NomeExibicao = "RD Intranet - Agente (Serviço)";

    private readonly HashSet<int> _sessoesJaTentadas = new();

    public AgenteServico()
    {
        ServiceName = NomeServico;
        CanHandleSessionChangeEvent = true;
        CanStop = true;
        CanShutdown = true;
    }

    protected override void OnStart(string[] args)
    {
        // SYSTEM sempre tem acesso de escrita em LocalMachine\Root/
        // TrustedPublisher, mesmo em máquina onde NENHUM usuário logado é
        // administrador -- então é aqui, e não só em Program.cs, que essa
        // confiança fica garantida em TODA máquina que roda o serviço.
        ConfiancaCertificado.GarantirConfianca();

        // Cobre o caso do serviço (re)iniciar com alguém já logado --
        // sem isso, só a PRÓXIMA troca de sessão dispararia o lançamento.
        try
        {
            foreach (var sessionId in SessaoUsuario.ObterSessoesAtivas())
            {
                LancarNaSessao(sessionId);
            }
        }
        catch (Exception ex)
        {
            RegistrarEvento($"Falha ao lançar o agente nas sessões já ativas: {ex.Message}", EventLogEntryType.Warning);
        }
    }

    protected override void OnStop()
    {
    }

    protected override void OnSessionChange(SessionChangeDescription changeDescription)
    {
        base.OnSessionChange(changeDescription);

        if (changeDescription.Reason is SessionChangeReason.SessionLogon
            or SessionChangeReason.ConsoleConnect
            or SessionChangeReason.RemoteConnect
            or SessionChangeReason.SessionUnlock)
        {
            LancarNaSessao(changeDescription.SessionId);
        }
    }

    private void LancarNaSessao(int sessionId)
    {
        if (sessionId <= 0) return; // sessao 0 = servicos, nunca tem desktop de usuario

        lock (_sessoesJaTentadas)
        {
            if (!_sessoesJaTentadas.Add(sessionId))
            {
                return; // ja tentamos essa sessao nesta execucao do servico
            }
        }

        // No login, o serviço é avisado antes de o Explorer montar a
        // barra de tarefas -- abrir o agente nesse instante fazia o ícone
        // nascer sem bandeja. Espera o Explorer da sessão (até 2 min) em
        // segundo plano, sem travar o serviço.
        _ = Task.Run(() =>
        {
            var limite = DateTime.Now.AddMinutes(2);
            while (DateTime.Now < limite && !ExplorerRodandoNaSessao(sessionId))
            {
                Thread.Sleep(2000);
            }
            Thread.Sleep(3000); // Explorer de pé ainda leva uns segundos pra criar a bandeja
            AbrirAgenteNaSessao(sessionId);
        });
    }

    private static bool ExplorerRodandoNaSessao(int sessionId)
    {
        try
        {
            return Process.GetProcessesByName("explorer").Any(p => p.SessionId == sessionId);
        }
        catch
        {
            return false;
        }
    }

    private static void AbrirAgenteNaSessao(int sessionId)
    {
        try
        {
            var caminhoExe = Environment.ProcessPath;
            if (string.IsNullOrEmpty(caminhoExe))
            {
                RegistrarEvento("Não consegui determinar o caminho do próprio .exe pra lançar na sessão do usuário.", EventLogEntryType.Warning);
                return;
            }

            if (!SessaoUsuario.LancarProcessoNaSessao(sessionId, caminhoExe, "--auto"))
            {
                RegistrarEvento($"Não consegui abrir o agente na sessão {sessionId} (WTSQueryUserToken/CreateProcessAsUser falharam).", EventLogEntryType.Warning);
            }
        }
        catch (Exception ex)
        {
            RegistrarEvento($"Falha ao abrir o agente na sessão {sessionId}: {ex.Message}", EventLogEntryType.Warning);
        }
    }

    internal static void RegistrarEvento(string mensagem, EventLogEntryType tipo)
    {
        try
        {
            const string origem = "RdIntranetAgente";
            if (!EventLog.SourceExists(origem))
            {
                EventLog.CreateEventSource(origem, "Application");
            }

            EventLog.WriteEntry(origem, mensagem, tipo);
        }
        catch
        {
            // sem log de eventos, nao ha mais nada a fazer -- servico segue rodando mesmo assim
        }
    }
}
