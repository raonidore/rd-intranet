using System.Management;
using Microsoft.Win32;

namespace RdIntranetAgente;

/// <summary>Uma coisa que o Windows inicia sozinho (serviço, tarefa, chave Run...), já analisada.</summary>
public sealed class ItemInicializacao
{
    /// <summary>servico | tarefa | registro | pasta_inicializar | winlogon | ifeo | wmi | appinit</summary>
    public string Tipo { get; init; } = "";
    public string Nome { get; init; } = "";
    /// <summary>Onde está configurado (chave do registro, pasta da tarefa, arquivo .lnk).</summary>
    public string Origem { get; init; } = "";
    public string Comando { get; init; } = "";
    public string? Conta { get; init; }
    public string? Estado { get; init; }
    public string? Inicio { get; init; }
    public InfoExecutavel Exe { get; init; } = new();
    public Avaliacao Avaliacao { get; init; } = new();

    public string Chave => $"{Tipo}|{Nome}|{Comando}".ToLowerInvariant();

    public Dictionary<string, object?> ParaEnvio()
    {
        var doWindows = Exe.Microsoft && Avaliacao.Pontos == 0;
        var item = new Dictionary<string, object?>
        {
            ["tipo"] = Tipo,
            ["nome"] = Nome,
            ["caminho"] = Exe.Caminho,
            ["estado"] = Estado,
            ["inicio"] = Inicio,
            ["ms"] = doWindows
        };
        if (doWindows) return item; // item da Microsoft sem nada estranho: só o essencial (o inventário fica leve)

        item["origem"] = Origem;
        item["comando"] = Comando.Length > 300 ? Comando[..300] + "…" : Comando;
        item["conta"] = Conta;
        item["existe"] = Exe.Existe;
        item["assinatura"] = Exe.Assinatura;
        item["assinante"] = Exe.Assinante;
        item["oculto"] = Exe.Oculto;
        item["arquivo_criado_em"] = Exe.CriadoEm?.ToString("o");
        item["pontos"] = Avaliacao.Pontos;
        item["nivel"] = Avaliacao.Nivel;
        item["motivos"] = Avaliacao.Motivos;
        if (Avaliacao.Pontos >= 35 && Exe.Existe) item["sha256"] = AnaliseExecutavel.Sha256(Exe.Caminho);
        return item;
    }
}

/// <summary>
/// Inventário do que o Windows inicia sozinho -- os lugares onde malware
/// se esconde pra sobreviver a reinício. No ARIIS (Maurílio, 14/10/2026)
/// foi um serviço "SQLite" (SQLite.exe oculto em Program Files (x86)\Java)
/// que ninguém via: o nome era inocente e a pasta, de um programa legítimo.
/// </summary>
public static class InicializacaoService
{
    private const string ChaveRun = @"Software\Microsoft\Windows\CurrentVersion\Run";

    public static List<ItemInicializacao> Coletar()
    {
        var itens = new List<ItemInicializacao>();
        void Tentar(Action coleta)
        {
            try { coleta(); } catch { /* uma fonte com problema não derruba as outras */ }
        }

        Tentar(() => Servicos(itens));
        Tentar(() => Tarefas(itens));
        Tentar(() => Registro(itens));
        Tentar(() => PastasInicializar(itens));
        Tentar(() => Winlogon(itens));
        Tentar(() => Ifeo(itens));
        Tentar(() => Wmi(itens));
        Tentar(() => AppInit(itens));

        return itens
            .OrderByDescending(i => i.Avaliacao.Pontos)
            .ThenBy(i => i.Tipo)
            .ThenBy(i => i.Nome, StringComparer.OrdinalIgnoreCase)
            .ToList();
    }

    private static ItemInicializacao Item(string tipo, string nome, string origem, string comando, string? conta = null,
        string? estado = null, string? inicio = null, int pontosExtra = 0, string? motivoExtra = null, string? caminhoForcado = null)
    {
        var caminho = caminhoForcado ?? AnaliseExecutavel.ExecutavelDaLinha(comando);
        var exe = AnaliseExecutavel.Analisar(caminho);
        var comoSystem = conta != null && (conta.Equals("LocalSystem", StringComparison.OrdinalIgnoreCase) || conta.Equals("SYSTEM", StringComparison.OrdinalIgnoreCase) || conta.Equals("NT AUTHORITY\\SYSTEM", StringComparison.OrdinalIgnoreCase) || conta == "S-1-5-18");
        var avaliacao = AnaliseExecutavel.Avaliar(exe, comando, comoSystem);
        if (!exe.Existe && !string.IsNullOrEmpty(caminho) && estado is not "Disabled" and not "Desativada")
        {
            avaliacao.Somar(5, "aponta para um arquivo que não existe");
        }
        if (pontosExtra > 0 && motivoExtra != null) avaliacao.Somar(pontosExtra, motivoExtra);

        return new ItemInicializacao
        {
            Tipo = tipo,
            Nome = nome,
            Origem = origem,
            Comando = comando,
            Conta = conta,
            Estado = estado,
            Inicio = inicio,
            Exe = exe,
            Avaliacao = avaliacao
        };
    }

    private static void Servicos(List<ItemInicializacao> itens)
    {
        using var busca = new ManagementObjectSearcher("SELECT Name, DisplayName, PathName, StartMode, State, StartName FROM Win32_Service");
        foreach (ManagementObject s in busca.Get())
        {
            using (s)
            {
                var nome = s["Name"] as string ?? "";
                var comando = s["PathName"] as string ?? "";
                string? dll = null;

                // Serviço dentro do svchost: o que importa é a DLL (ServiceDll), não o svchost.
                if (comando.Contains("svchost.exe", StringComparison.OrdinalIgnoreCase))
                {
                    using var parametros = Registry.LocalMachine.OpenSubKey($@"SYSTEM\CurrentControlSet\Services\{nome}\Parameters")
                        ?? Registry.LocalMachine.OpenSubKey($@"SYSTEM\CurrentControlSet\Services\{nome}");
                    dll = parametros?.GetValue("ServiceDll") as string;
                }

                var exibicao = s["DisplayName"] as string ?? nome;
                itens.Add(Item("servico", exibicao == nome ? nome : $"{exibicao} ({nome})", $@"HKLM\SYSTEM\CurrentControlSet\Services\{nome}",
                    dll != null ? $"{comando} → {dll}" : comando, s["StartName"] as string, s["State"] as string, s["StartMode"] as string,
                    caminhoForcado: dll != null ? Environment.ExpandEnvironmentVariables(dll) : null));
            }
        }
    }

    private static void Tarefas(List<ItemInicializacao> itens)
    {
        var tipo = Type.GetTypeFromProgID("Schedule.Service");
        if (tipo == null) return;
        dynamic agendador = Activator.CreateInstance(tipo)!;
        agendador.Connect();

        void Pasta(dynamic pasta, int profundidade)
        {
            if (profundidade > 8) return;
            foreach (dynamic tarefa in pasta.GetTasks(1)) // 1 = inclui ocultas
            {
                try
                {
                    string caminhoTarefa = tarefa.Path;
                    bool habilitada = tarefa.Enabled;
                    string? conta = null;
                    try { conta = (string?)tarefa.Definition.Principal.UserId; } catch { }
                    foreach (dynamic acao in tarefa.Definition.Actions)
                    {
                        if ((int)acao.Type != 0) continue; // só "executar programa"
                        string programa = acao.Path ?? "";
                        string argumentos = acao.Arguments ?? "";
                        var comando = programa.Contains(' ') && !programa.StartsWith('"') ? $"\"{programa}\" {argumentos}" : $"{programa} {argumentos}";
                        itens.Add(Item("tarefa", caminhoTarefa, caminhoTarefa, comando.Trim(), conta, habilitada ? "Habilitada" : "Desativada"));
                    }
                }
                catch
                {
                    // tarefa corrompida/sem acesso: pula
                }
            }
            foreach (dynamic sub in pasta.GetFolders(0)) Pasta(sub, profundidade + 1);
        }

        Pasta(agendador.GetFolder("\\"), 0);
    }

    private static void Registro(List<ItemInicializacao> itens)
    {
        void Ler(RegistryKey raiz, string rotuloRaiz, string subchave, string? conta)
        {
            using var chave = raiz.OpenSubKey(subchave);
            if (chave == null) return;
            foreach (var valor in chave.GetValueNames())
            {
                if (chave.GetValue(valor) is not string comando || string.IsNullOrWhiteSpace(comando)) continue;
                itens.Add(Item("registro", valor == "" ? "(padrão)" : valor, $@"{rotuloRaiz}\{subchave}", comando, conta));
            }
        }

        foreach (var sub in new[] { ChaveRun, ChaveRun + "Once", @"Software\Microsoft\Windows\CurrentVersion\Policies\Explorer\Run",
                                    @"Software\WOW6432Node\Microsoft\Windows\CurrentVersion\Run", @"Software\WOW6432Node\Microsoft\Windows\CurrentVersion\RunOnce" })
        {
            Ler(Registry.LocalMachine, "HKLM", sub, "todos os usuários");
        }

        // Cada usuário com o perfil carregado (logado ou com serviço rodando como ele).
        foreach (var sid in Registry.Users.GetSubKeyNames().Where(s => s.StartsWith("S-1-5-21-") && !s.EndsWith("_Classes")))
        {
            using var usuario = Registry.Users.OpenSubKey(sid);
            if (usuario == null) continue;
            var conta = NomeDaConta(sid);
            Ler(usuario, $@"HKU\{conta}", ChaveRun, conta);
            Ler(usuario, $@"HKU\{conta}", ChaveRun + "Once", conta);
        }
    }

    private static void PastasInicializar(List<ItemInicializacao> itens)
    {
        var pastas = new List<(string pasta, string conta)> { (Environment.GetFolderPath(Environment.SpecialFolder.CommonStartup), "todos os usuários") };
        var usuarios = Path.Combine(Path.GetPathRoot(Environment.SystemDirectory)!, "Users");
        if (Directory.Exists(usuarios))
        {
            foreach (var perfil in Directory.GetDirectories(usuarios))
            {
                pastas.Add((Path.Combine(perfil, @"AppData\Roaming\Microsoft\Windows\Start Menu\Programs\Startup"), Path.GetFileName(perfil)));
            }
        }

        foreach (var (pasta, conta) in pastas.Where(p => Directory.Exists(p.pasta)))
        {
            foreach (var arquivo in Directory.GetFiles(pasta).Where(f => !f.EndsWith("desktop.ini", StringComparison.OrdinalIgnoreCase)))
            {
                var comando = arquivo;
                if (arquivo.EndsWith(".lnk", StringComparison.OrdinalIgnoreCase) && Atalho(arquivo) is { } alvo)
                {
                    comando = alvo;
                }
                itens.Add(Item("pasta_inicializar", Path.GetFileName(arquivo), pasta, comando, conta));
            }
        }
    }

    private static void Winlogon(List<ItemInicializacao> itens)
    {
        using var chave = Registry.LocalMachine.OpenSubKey(@"SOFTWARE\Microsoft\Windows NT\CurrentVersion\Winlogon");
        if (chave == null) return;

        var shell = (chave.GetValue("Shell") as string ?? "").Trim();
        if (shell != "" && !shell.Equals("explorer.exe", StringComparison.OrdinalIgnoreCase))
        {
            itens.Add(Item("winlogon", "Shell", @"HKLM\...\Winlogon\Shell", shell, pontosExtra: 40, motivoExtra: "Winlogon abre outro programa no lugar do explorer.exe"));
        }

        var userinit = (chave.GetValue("Userinit") as string ?? "").Trim().TrimEnd(',');
        if (userinit != "" && !userinit.EndsWith(@"\system32\userinit.exe", StringComparison.OrdinalIgnoreCase))
        {
            itens.Add(Item("winlogon", "Userinit", @"HKLM\...\Winlogon\Userinit", userinit, pontosExtra: 40, motivoExtra: "Winlogon roda algo além do userinit.exe no login"));
        }
    }

    private static void Ifeo(List<ItemInicializacao> itens)
    {
        const string caminho = @"SOFTWARE\Microsoft\Windows NT\CurrentVersion\Image File Execution Options";
        using var raiz = Registry.LocalMachine.OpenSubKey(caminho);
        if (raiz == null) return;
        foreach (var programa in raiz.GetSubKeyNames())
        {
            using var sub = raiz.OpenSubKey(programa);
            if (sub?.GetValue("Debugger") is string depurador && depurador.Trim() != "")
            {
                itens.Add(Item("ifeo", programa, $@"HKLM\{caminho}\{programa}", depurador, pontosExtra: 40,
                    motivoExtra: $"sequestra a abertura de {programa} (Debugger do IFEO)"));
            }
        }
    }

    private static void Wmi(List<ItemInicializacao> itens)
    {
        var escopo = new ManagementScope(@"\\.\root\subscription");
        escopo.Connect();
        using (var busca = new ManagementObjectSearcher(escopo, new ObjectQuery("SELECT Name, CommandLineTemplate, ExecutablePath FROM CommandLineEventConsumer")))
        {
            foreach (ManagementObject c in busca.Get())
            {
                using (c)
                {
                    var comando = (c["CommandLineTemplate"] as string) ?? (c["ExecutablePath"] as string) ?? "";
                    itens.Add(Item("wmi", c["Name"] as string ?? "(sem nome)", @"root\subscription\CommandLineEventConsumer", comando,
                        pontosExtra: 50, motivoExtra: "assinatura WMI que executa comando (persistência comum de malware)"));
                }
            }
        }
        using (var busca = new ManagementObjectSearcher(escopo, new ObjectQuery("SELECT Name, ScriptingEngine, ScriptFileName FROM ActiveScriptEventConsumer")))
        {
            foreach (ManagementObject c in busca.Get())
            {
                using (c)
                {
                    itens.Add(Item("wmi", c["Name"] as string ?? "(sem nome)", @"root\subscription\ActiveScriptEventConsumer",
                        $"{c["ScriptingEngine"]} {c["ScriptFileName"]}".Trim(), pontosExtra: 50, motivoExtra: "assinatura WMI que executa script"));
                }
            }
        }
    }

    private static void AppInit(List<ItemInicializacao> itens)
    {
        foreach (var sub in new[] { @"SOFTWARE\Microsoft\Windows NT\CurrentVersion\Windows", @"SOFTWARE\WOW6432Node\Microsoft\Windows NT\CurrentVersion\Windows" })
        {
            using var chave = Registry.LocalMachine.OpenSubKey(sub);
            var dlls = (chave?.GetValue("AppInit_DLLs") as string ?? "").Trim();
            if (dlls != "" && Convert.ToInt32(chave?.GetValue("LoadAppInit_DLLs") ?? 0) == 1)
            {
                itens.Add(Item("appinit", "AppInit_DLLs", $@"HKLM\{sub}", dlls, pontosExtra: 40, motivoExtra: "DLL injetada em todo programa que abre (AppInit_DLLs)"));
            }
        }
    }

    private static string? Atalho(string arquivoLnk)
    {
        try
        {
            var tipo = Type.GetTypeFromProgID("WScript.Shell");
            if (tipo == null) return null;
            dynamic shell = Activator.CreateInstance(tipo)!;
            dynamic atalho = shell.CreateShortcut(arquivoLnk);
            string alvo = atalho.TargetPath ?? "";
            string argumentos = atalho.Arguments ?? "";
            return alvo == "" ? null : (alvo.Contains(' ') ? $"\"{alvo}\" {argumentos}" : $"{alvo} {argumentos}").Trim();
        }
        catch
        {
            return null;
        }
    }

    private static string NomeDaConta(string sid)
    {
        try
        {
            return new System.Security.Principal.SecurityIdentifier(sid).Translate(typeof(System.Security.Principal.NTAccount)).Value;
        }
        catch
        {
            return sid;
        }
    }
}
