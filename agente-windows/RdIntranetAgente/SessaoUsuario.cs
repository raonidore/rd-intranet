using System.Runtime.InteropServices;

namespace RdIntranetAgente;

/// <summary>
/// P/Invoke puro (WTS + tokens) pra lançar um processo dentro da sessão
/// interativa de outro usuário, com o token ELEVADO dele quando
/// disponível (o "linked token" da metade administrativa do split-token
/// do UAC) -- é essa combinação que abre o processo já elevado sem
/// NENHUM prompt de consentimento: quem cria o processo (SYSTEM, via
/// AgenteServico) já tem privilégio de sobra pra atribuir um token
/// elevado diretamente, sem passar pelo fluxo interativo de "runas" (que
/// só existe pro caminho "um processo comum pede pra se elevar sozinho"
/// -- ver Program.cs/RelancarComoAdministrador(), é exatamente esse
/// caminho que ficava preso em máquina desatendida sem ninguém pra
/// clicar "Sim").
///
/// Se o usuário da sessão não for administrador local, não existe token
/// "linked" nenhum pra pegar -- nesse caso cai pro token normal (não
/// elevado) do próprio usuário, então o agente ainda assim abre (só que
/// sem conseguir os "comandos com elevação" remotos, igual já acontecia
/// antes pra essas máquinas).
/// </summary>
internal static class SessaoUsuario
{
    private const uint TOKEN_ASSIGN_PRIMARY = 0x0001;
    private const uint TOKEN_DUPLICATE = 0x0002;
    private const uint TOKEN_QUERY = 0x0008;
    private const uint TOKEN_QUERY_SOURCE = 0x0010;
    private const uint TOKEN_ADJUST_PRIVILEGES = 0x0020;
    private const uint TOKEN_ADJUST_GROUPS = 0x0040;
    private const uint TOKEN_ADJUST_DEFAULT = 0x0080;
    private const uint TOKEN_ADJUST_SESSIONID = 0x0100;
    private const uint TOKEN_ALL_ACCESS_P = TOKEN_ASSIGN_PRIMARY | TOKEN_DUPLICATE | TOKEN_QUERY |
        TOKEN_QUERY_SOURCE | TOKEN_ADJUST_PRIVILEGES | TOKEN_ADJUST_GROUPS | TOKEN_ADJUST_DEFAULT | TOKEN_ADJUST_SESSIONID;

    private const int SecurityImpersonation = 2;
    private const int TokenPrimary = 1;
    private const int TokenLinkedToken = 19;
    private const uint CREATE_UNICODE_ENVIRONMENT = 0x00000400;
    private const uint NORMAL_PRIORITY_CLASS = 0x00000020;
    private const int WTSActive = 0;

    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    private struct STARTUPINFO
    {
        public int cb;
        public string? lpReserved;
        public string? lpDesktop;
        public string? lpTitle;
        public int dwX;
        public int dwY;
        public int dwXSize;
        public int dwYSize;
        public int dwXCountChars;
        public int dwYCountChars;
        public int dwFillAttribute;
        public int dwFlags;
        public short wShowWindow;
        public short cbReserved2;
        public IntPtr lpReserved2;
        public IntPtr hStdInput;
        public IntPtr hStdOutput;
        public IntPtr hStdError;
    }

    [StructLayout(LayoutKind.Sequential)]
    private struct PROCESS_INFORMATION
    {
        public IntPtr hProcess;
        public IntPtr hThread;
        public int dwProcessId;
        public int dwThreadId;
    }

    [StructLayout(LayoutKind.Sequential)]
    private struct WTS_SESSION_INFO
    {
        public int SessionId;
        public IntPtr pWinStationName;
        public int State;
    }

    [DllImport("wtsapi32.dll", SetLastError = true)]
    private static extern bool WTSQueryUserToken(int sessionId, out IntPtr phToken);

    [DllImport("wtsapi32.dll", SetLastError = true)]
    private static extern bool WTSEnumerateSessions(IntPtr hServer, int reserved, int version, out IntPtr ppSessionInfo, out int pCount);

    [DllImport("wtsapi32.dll")]
    private static extern void WTSFreeMemory(IntPtr pMemory);

    [DllImport("advapi32.dll", SetLastError = true)]
    private static extern bool DuplicateTokenEx(IntPtr hExistingToken, uint dwDesiredAccess, IntPtr lpTokenAttributes,
        int impersonationLevel, int tokenType, out IntPtr phNewToken);

    [DllImport("advapi32.dll", SetLastError = true)]
    private static extern bool GetTokenInformation(IntPtr tokenHandle, int tokenInformationClass,
        IntPtr tokenInformation, int tokenInformationLength, out int returnLength);

    [DllImport("userenv.dll", SetLastError = true)]
    private static extern bool CreateEnvironmentBlock(out IntPtr lpEnvironment, IntPtr hToken, bool bInherit);

    [DllImport("userenv.dll", SetLastError = true)]
    private static extern bool DestroyEnvironmentBlock(IntPtr lpEnvironment);

    [DllImport("advapi32.dll", SetLastError = true, CharSet = CharSet.Unicode)]
    private static extern bool CreateProcessAsUser(IntPtr hToken, string? lpApplicationName, string? lpCommandLine,
        IntPtr lpProcessAttributes, IntPtr lpThreadAttributes, bool bInheritHandles, uint dwCreationFlags,
        IntPtr lpEnvironment, string? lpCurrentDirectory, ref STARTUPINFO lpStartupInfo, out PROCESS_INFORMATION lpProcessInformation);

    [DllImport("kernel32.dll", SetLastError = true)]
    private static extern bool CloseHandle(IntPtr hObject);

    /// <summary>IDs das sessões com um desktop interativo ativo agora (usuário de verdade logado, não a sessão 0 de serviços).</summary>
    public static List<int> ObterSessoesAtivas()
    {
        var sessoes = new List<int>();

        if (!WTSEnumerateSessions(IntPtr.Zero, 0, 1, out var pSessionInfo, out var count))
        {
            return sessoes;
        }

        try
        {
            var tamanho = Marshal.SizeOf<WTS_SESSION_INFO>();
            for (var i = 0; i < count; i++)
            {
                var info = Marshal.PtrToStructure<WTS_SESSION_INFO>(pSessionInfo + i * tamanho);
                if (info.State == WTSActive && info.SessionId > 0)
                {
                    sessoes.Add(info.SessionId);
                }
            }
        }
        finally
        {
            WTSFreeMemory(pSessionInfo);
        }

        return sessoes;
    }

    /// <summary>
    /// Lança <paramref name="caminhoExe"/> dentro da sessão interativa
    /// <paramref name="sessionId"/>, tentando primeiro o token elevado
    /// (linked token) do usuário dessa sessão e caindo pro token normal
    /// dele se não existir/falhar. Retorna false só se nem a versão sem
    /// elevação conseguiu abrir (sessão sem usuário válido, etc.).
    /// </summary>
    public static bool LancarProcessoNaSessao(int sessionId, string caminhoExe, string argumentos)
    {
        if (!WTSQueryUserToken(sessionId, out var tokenUsuario))
        {
            return false;
        }

        var tokenLinked = IntPtr.Zero;

        try
        {
            tokenLinked = ObterTokenLinkado(tokenUsuario);

            if (tokenLinked != IntPtr.Zero && TentarCriarProcesso(tokenLinked, caminhoExe, argumentos))
            {
                return true;
            }

            return TentarCriarProcesso(tokenUsuario, caminhoExe, argumentos);
        }
        finally
        {
            if (tokenLinked != IntPtr.Zero) CloseHandle(tokenLinked);
            CloseHandle(tokenUsuario);
        }
    }

    private static IntPtr ObterTokenLinkado(IntPtr tokenUsuario)
    {
        var buffer = Marshal.AllocHGlobal(IntPtr.Size);
        try
        {
            if (GetTokenInformation(tokenUsuario, TokenLinkedToken, buffer, IntPtr.Size, out _))
            {
                return Marshal.ReadIntPtr(buffer);
            }
        }
        catch
        {
            // sem token linked (usuario nao e admin, ou UAC desabilitado) -- cai pro token normal
        }
        finally
        {
            Marshal.FreeHGlobal(buffer);
        }

        return IntPtr.Zero;
    }

    private static bool TentarCriarProcesso(IntPtr tokenOrigem, string caminhoExe, string argumentos)
    {
        if (!DuplicateTokenEx(tokenOrigem, TOKEN_ALL_ACCESS_P, IntPtr.Zero, SecurityImpersonation, TokenPrimary, out var tokenPrimario))
        {
            return false;
        }

        var ambiente = IntPtr.Zero;

        try
        {
            CreateEnvironmentBlock(out ambiente, tokenPrimario, false);

            var startupInfo = new STARTUPINFO { cb = Marshal.SizeOf<STARTUPINFO>(), lpDesktop = "winsta0\\default" };
            var linhaComando = $"\"{caminhoExe}\" {argumentos}";
            var pastaInstalacao = Path.GetDirectoryName(caminhoExe);

            var criado = CreateProcessAsUser(tokenPrimario, null, linhaComando, IntPtr.Zero, IntPtr.Zero,
                false, CREATE_UNICODE_ENVIRONMENT | NORMAL_PRIORITY_CLASS, ambiente, pastaInstalacao,
                ref startupInfo, out var infoProcesso);

            if (criado)
            {
                CloseHandle(infoProcesso.hProcess);
                CloseHandle(infoProcesso.hThread);
            }

            return criado;
        }
        finally
        {
            if (ambiente != IntPtr.Zero) DestroyEnvironmentBlock(ambiente);
            CloseHandle(tokenPrimario);
        }
    }
}
