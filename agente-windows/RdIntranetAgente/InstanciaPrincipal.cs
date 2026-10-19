using System.Security.AccessControl;
using System.Security.Principal;
using System.Text.Json;
using RdIntranetAgente.Models;

namespace RdIntranetAgente;

/// <summary>
/// Uma instância "principal" por máquina. A bandeja abre uma cópia do agente
/// em CADA sessão de usuário; num servidor de terminal (TS-DOREMED, 19/10/2026:
/// 17 sessões) cada cópia fazia o trabalho da máquina inteira -- coleta
/// completa (WMI, licenciamento do Windows), heartbeat, varredura de processos
/// a cada 30 s, shadow copies, desempenho -- e o agente sozinho, mais o WMI e
/// o sppsvc que ele acordava, comia metade da CPU. Também disputavam a fila de
/// comandos do portal (comando elevado caindo na sessão de um usuário comum).
///
/// Agora só a principal faz o trabalho de máquina. As demais ficam com o ícone,
/// o painel, chamados/pedir ajuda e as proteções do PRÓPRIO perfil (iscas e
/// mudança em massa nas pastas do usuário). Em PC com um usuário só nada muda:
/// a única cópia é a principal.
///
/// Eleição por mutex global (vale entre sessões). Se a sessão principal sai
/// (logoff/queda), o mutex é liberado e outra assume na próxima tentativa
/// (a cada minuto). Instância elevada tem preferência: se a principal não é
/// administrador e existe uma elevada esperando, a principal cede a vez.
/// </summary>
public static class InstanciaPrincipal
{
    private const string NomeMutex = @"Global\RDIntranetAgentePrincipal";
    private const string NomeElevadaEsperando = @"Global\RDIntranetAgenteElevadaEsperando";

    private static Mutex? _principal;
    private static Mutex? _esperando;

    public static bool EhPrincipal { get; private set; }

    /// <summary>Precisa ser chamado sempre na MESMA thread (a de UI): mutex é da thread que o pegou.</summary>
    public static bool TentarAssumir()
    {
        if (EhPrincipal) return true;

        var elevado = Program.EstaElevado();

        // Não elevada não pega a vez de uma elevada que está esperando.
        if (!elevado && Mutex.TryOpenExisting(NomeElevadaEsperando, out var outra))
        {
            outra.Dispose();
            return false;
        }

        try
        {
            _principal ??= CriarOuAbrir(NomeMutex);
            try
            {
                EhPrincipal = _principal.WaitOne(0);
            }
            catch (AbandonedMutexException)
            {
                EhPrincipal = true; // a principal anterior morreu sem liberar
            }
        }
        catch
        {
            // Sem conseguir usar o mutex, volta ao comportamento antigo (cada cópia faz tudo).
            EhPrincipal = true;
        }

        if (EhPrincipal)
        {
            _esperando?.Dispose();
            _esperando = null;
        }
        else if (elevado && _esperando == null)
        {
            try { _esperando = CriarOuAbrir(NomeElevadaEsperando); } catch { }
        }

        return EhPrincipal;
    }

    /// <summary>Principal não elevada com uma elevada esperando: deve ceder (chamar Liberar).</summary>
    public static bool DeveCeder()
    {
        if (!EhPrincipal || Program.EstaElevado()) return false;
        if (!Mutex.TryOpenExisting(NomeElevadaEsperando, out var elevada)) return false;
        elevada.Dispose();
        return true;
    }

    public static void Liberar()
    {
        if (EhPrincipal && _principal != null)
        {
            try { _principal.ReleaseMutex(); } catch { }
        }
        EhPrincipal = false;
    }

    /// <summary>
    /// Chamado pelo serviço (SYSTEM, sobe no boot): cria o mutex da principal e
    /// segura a referência pra ele existir sempre. Usuário comum não tem o
    /// privilégio de CRIAR objeto em Global\ (SeCreateGlobalPrivilege), só de
    /// abrir um que já existe -- sem isso, num TS só sessões de administrador
    /// participariam da eleição. O "esperando" NÃO é criado aqui: a existência
    /// dele é o sinal de que há uma sessão elevada na fila.
    /// </summary>
    public static void GarantirObjetosGlobais()
    {
        try { _principal ??= CriarOuAbrir(NomeMutex); } catch { }
    }

    private static Mutex CriarOuAbrir(string nome)
    {
        if (Mutex.TryOpenExisting(nome, out var existente)) return existente;

        // Qualquer sessão (usuário comum incluso) precisa conseguir abrir o mesmo mutex.
        var seguranca = new MutexSecurity();
        seguranca.AddAccessRule(new MutexAccessRule(
            new SecurityIdentifier(WellKnownSidType.WorldSid, null),
            MutexRights.Synchronize | MutexRights.Modify,
            AccessControlType.Allow));
        return MutexAcl.Create(false, nome, out _, seguranca);
    }

    // ------------------------------------------------------------------
    // Configuração de segurança para as sessões secundárias (elas não fazem
    // checkin; a principal deixa a última recebida do portal aqui).
    // ------------------------------------------------------------------

    private static string ArquivoModulo => Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData), "RDIntranetAgent", "modulo_seguranca.json");

    public static void GravarModulo(ModuloSeguranca? modulo)
    {
        if (modulo == null) return;
        try
        {
            Directory.CreateDirectory(Path.GetDirectoryName(ArquivoModulo)!);
            File.WriteAllText(ArquivoModulo, JsonSerializer.Serialize(modulo));
        }
        catch
        {
            // best-effort: a principal pode não ter permissão de gravar em ProgramData
        }
    }

    /// <summary>Configuração para uma sessão secundária: só o que é do perfil do usuário (shadow copies são da máquina).</summary>
    public static ModuloSeguranca? LerModuloParaSecundaria()
    {
        try
        {
            if (!File.Exists(ArquivoModulo)) return null;
            var modulo = JsonSerializer.Deserialize<ModuloSeguranca>(File.ReadAllText(ArquivoModulo));
            if (modulo != null) modulo.ShadowCopy = false;
            return modulo;
        }
        catch
        {
            return null;
        }
    }
}
