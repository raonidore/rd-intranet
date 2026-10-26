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

    // Sessões com abertura em andamento (espera do Explorer). Antes era "já tentei essa
    // sessão" pra sempre: a tela de login cria a sessão antes de alguém entrar, a tentativa
    // falhava sem usuário e quem logava depois nessa sessão ficava sem agente (EN-AT-01-D, 22/10/2026).
    private readonly HashSet<int> _sessoesEmAndamento = new();

    // A cada minuto: toda sessão com usuário e Explorer tem que ter o agente rodando.
    private System.Threading.Timer? _timerVigiaSessoes;

    // Máquina ligada sem ninguém logado: sem ícone, ninguém mandava heartbeat e o portal
    // mostrava "desligada" (EN-AT-01-D, 22/10/2026). O serviço avisa que está viva.
    private System.Threading.Timer? _timerHeartbeat;
    private int _enviandoHeartbeat;
    private DateTime _ultimoHeartbeat = DateTime.MinValue;
    private Config? _configHeartbeat;
    private string? _guidHeartbeat;
    private DateTime _configLidaEm = DateTime.MinValue;
    private DateTime _sessaoConferidaEm = DateTime.MinValue;
    private bool _iconeEmAlgumaSessao;

    // Com serviço instalado quem atualiza o agente é o serviço (ver AtualizacaoServico):
    // 3 min depois de subir e depois a cada hora.
    private System.Threading.Timer? _timerAtualizacao;

    // Trilha de acesso roda aqui, como SYSTEM: funciona com usuário comum logado
    // (o ícone dele não tem elevação pra ler o log de segurança) e sem ninguém
    // logado -- o logoff do fim do dia não se perde mais à noite.
    private AcessosService? _acessos;
    private System.Threading.Timer? _timerLaps;
    private System.Threading.Timer? _timerConfig;
    // Ambiente padrão do portal na parte que vale para todos os usuários (Run do HKLM, Área de Trabalho pública).
    private System.Threading.Timer? _timerAmbiente;

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
        // Mutex da instância principal criado aqui (SYSTEM): usuário comum só consegue abrir, não criar, objeto em Global\.
        InstanciaPrincipal.GarantirObjetosGlobais();
        // Pasta do agente só de administrador + exclusões do Defender pro MeshAgent (ver ProtecaoInstalacao).
        ProtecaoInstalacao.GarantirEmSegundoPlano();
        // Reversão automática das alterações de rede do portal: aqui funciona mesmo sem ninguém logado (reboot no meio do prazo).
        RedeControleService.IniciarVigia();

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

        _timerVigiaSessoes = new System.Threading.Timer(_ => VigiarSessoes(), null, TimeSpan.FromMinutes(1), TimeSpan.FromMinutes(1));
        // Mesmo ritmo do ícone (HeartbeatSegundos, padrão 1 s): o portal só considera ligada com sinal nos últimos 5 s.
        _timerHeartbeat = new System.Threading.Timer(_ => _ = HeartbeatSemSessaoAsync(), null, TimeSpan.FromSeconds(10), TimeSpan.FromSeconds(1));

        _acessos = new AcessosService(Config.CarregarParaServico, IdentificadorDaMaquina);
        // LAPS troca a senha do Administrador em algum momento depois da política chegar -- confere de hora em hora.
        _timerLaps = new System.Threading.Timer(_ => ProtecaoInstalacao.AtivarAdministradorComLaps(), null, TimeSpan.FromMinutes(5), TimeSpan.FromHours(1));
        // Configuração da máquina ao lado do .exe -- sessão de quem nunca configurou (pessoa nova no Entra) já abre conectada.
        _timerConfig = new System.Threading.Timer(_ => Config.SincronizarCopiaDaMaquina(), null, TimeSpan.Zero, TimeSpan.FromHours(1));

        _timerAmbiente = new System.Threading.Timer(
            _ => _ = AmbientePadrao.AplicarAsync(Config.CarregarParaServico(), IdentificadorDaMaquina(), contextoMaquina: true),
            null, TimeSpan.FromMinutes(1), TimeSpan.FromMinutes(30));

        _timerAtualizacao = new System.Threading.Timer(
            _ => _ = AtualizacaoServico.VerificarEAtualizarAsync("verificação periódica"),
            null, TimeSpan.FromMinutes(3), TimeSpan.FromHours(1));
    }

    protected override void OnStop()
    {
        _timerAtualizacao?.Dispose();
        _timerVigiaSessoes?.Dispose();
        _timerHeartbeat?.Dispose();
        _timerLaps?.Dispose();
        _timerConfig?.Dispose();
        _timerAmbiente?.Dispose();
        _acessos?.Dispose();
    }

    /// <summary>Comando 130 = "verifique atualização agora" (mandado pela bandeja em "Atualizar agora" e no "Forçar coleta" do portal).</summary>
    protected override void OnCustomCommand(int command)
    {
        base.OnCustomCommand(command);
        if (command == AtualizacaoServico.ComandoVerificarAgora)
        {
            _ = AtualizacaoServico.VerificarEAtualizarAsync("pedido da bandeja");
        }
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

    /// <summary>Rede de segurança: sessão com usuário logado e sem o agente (falhou ao abrir, alguém fechou, travou) ganha o agente de novo.</summary>
    private void VigiarSessoes()
    {
        try
        {
            foreach (var sessionId in SessaoUsuario.ObterSessoesAtivas())
            {
                if (ExplorerRodandoNaSessao(sessionId) && !AgenteRodandoNaSessao(sessionId))
                {
                    LancarNaSessao(sessionId);
                }
            }
        }
        catch
        {
            // tenta de novo no próximo minuto
        }
    }

    private void LancarNaSessao(int sessionId)
    {
        if (sessionId <= 0) return; // sessao 0 = servicos, nunca tem desktop de usuario

        lock (_sessoesEmAndamento)
        {
            if (!_sessoesEmAndamento.Add(sessionId))
            {
                return; // já esperando o Explorer dessa sessão
            }
        }

        // No login, o serviço é avisado antes de o Explorer montar a
        // barra de tarefas -- abrir o agente nesse instante fazia o ícone
        // nascer sem bandeja. Espera o Explorer da sessão (até 2 min) em
        // segundo plano, sem travar o serviço. Sem Explorer (tela de login,
        // ninguém entrou ainda) não tenta: a vigia de cada minuto abre quando alguém logar.
        _ = Task.Run(() =>
        {
            try
            {
                var limite = DateTime.Now.AddMinutes(2);
                while (DateTime.Now < limite && !ExplorerRodandoNaSessao(sessionId))
                {
                    Thread.Sleep(2000);
                }
                if (!ExplorerRodandoNaSessao(sessionId) || AgenteRodandoNaSessao(sessionId)) return;
                Thread.Sleep(3000); // Explorer de pé ainda leva uns segundos pra criar a bandeja
                AbrirAgenteNaSessao(sessionId);
            }
            finally
            {
                lock (_sessoesEmAndamento) _sessoesEmAndamento.Remove(sessionId);
            }
        });
    }

    /// <summary>
    /// Só quando nenhuma sessão tem o ícone do agente (ele é quem manda heartbeat e atende os pedidos).
    /// A resposta é ignorada: pedidos do portal ficam na fila até alguém logar.
    /// </summary>
    private async Task HeartbeatSemSessaoAsync()
    {
        if (Interlocked.Exchange(ref _enviandoHeartbeat, 1) == 1) return;
        try
        {
            var agora = DateTime.UtcNow;
            // Config (varre os perfis) e identificador: relidos a cada minuto; sessões com o ícone: a cada 5 s.
            if (_configHeartbeat == null || agora - _configLidaEm > TimeSpan.FromMinutes(1))
            {
                _configHeartbeat = Config.CarregarParaServico();
                _guidHeartbeat = IdentificadorDaMaquina();
                _configLidaEm = agora;
            }
            var config = _configHeartbeat;
            if (agora - _ultimoHeartbeat < TimeSpan.FromSeconds(Math.Max(1, config.HeartbeatSegundos) - 0.2)) return;
            if (agora - _sessaoConferidaEm > TimeSpan.FromSeconds(5))
            {
                _iconeEmAlgumaSessao = AgenteEmAlgumaSessao();
                _sessaoConferidaEm = agora;
            }
            if (_iconeEmAlgumaSessao) return;
            _ultimoHeartbeat = agora;
            var guid = _guidHeartbeat;
            if (!config.EstaConfigurado || string.IsNullOrEmpty(guid)) return;
            await new HeartbeatClient(config).EnviarAsync(guid);
        }
        catch
        {
            // tenta de novo em 30 s
        }
        finally
        {
            Interlocked.Exchange(ref _enviandoHeartbeat, 0);
        }
    }

    private static bool AgenteEmAlgumaSessao()
    {
        try
        {
            return Process.GetProcessesByName(Path.GetFileNameWithoutExtension(Environment.ProcessPath ?? "RdIntranetAgente"))
                .Any(p => p.SessionId > 0);
        }
        catch
        {
            return true;
        }
    }

    private static bool AgenteRodandoNaSessao(int sessionId)
    {
        try
        {
            return Process.GetProcessesByName(Path.GetFileNameWithoutExtension(Environment.ProcessPath ?? "RdIntranetAgente"))
                .Any(p => p.SessionId == sessionId);
        }
        catch
        {
            return true; // na dúvida não abre outra cópia
        }
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

    /// <summary>
    /// Mesmo identificador que o ícone usa: o SYSTEM não tem o config.json do
    /// usuário (onde ficam o identificador manual e o cache), então lê o
    /// config dos perfis antes de recalcular -- senão VM sem serial de BIOS
    /// viraria "ativo não encontrado".
    /// </summary>
    internal static string? IdentificadorDaMaquina()
    {
        try
        {
            var config = Config.CarregarParaServico();
            if (!string.IsNullOrWhiteSpace(config.MachineGuidOverride)) return config.MachineGuidOverride.Trim();
            if (!string.IsNullOrWhiteSpace(config.MachineGuidCache)) return config.MachineGuidCache.Trim();
            return CollectorService.ObterMachineGuid();
        }
        catch
        {
            return null;
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
