using System.Diagnostics;
using System.Management;
using System.ServiceProcess;
using System.Text.Json;
using Microsoft.Win32;

namespace RdIntranetAgente;

public sealed record ResultadoAcao(bool Sucesso, string Mensagem);

/// <summary>Item que o RD.Intranet desativou e guardou pra poder reativar.</summary>
public sealed record ItemDesativado(string Id, string Tipo, string Nome, string Origem, string Comando, DateTime Quando);

/// <summary>Arquivo/pasta em quarentena (movido e renomeado pra não executar).</summary>
public sealed class ItemQuarentena
{
    public string Id { get; set; } = "";
    public string Original { get; set; } = "";
    public string Destino { get; set; } = "";
    public bool Pasta { get; set; }
    public string? Sha256 { get; set; }
    public string Motivo { get; set; } = "";
    public DateTime Quando { get; set; }
}

/// <summary>
/// Ações de remediação usadas pela tela Segurança do agente E pelo portal
/// (solicitações): desativar/reativar o que inicia com o Windows, pôr
/// arquivo em quarentena, ligar/desligar o firewall. Tudo reversível:
///  - registro (Run, IFEO, Winlogon, AppInit): o valor vai pra
///    HKLM\SOFTWARE\RDIntranet\InicializacaoDesativada antes de sair;
///  - pasta Inicializar: o arquivo vai pra subpasta "RD.Intranet - desativados";
///  - serviço: guarda o tipo de início anterior;
///  - tarefa: schtasks /DISABLE (o Windows guarda a tarefa);
///  - quarentena: move pra ProgramData\RDIntranetAgent\Quarentena com
///    extensão .quarentena (não executa), registrando origem e SHA256.
/// Exceção: assinatura WMI é apagada (o Windows não tem "desativar") --
/// a linha de comando fica registrada no backup pra recriar à mão.
/// Precisa do agente rodando como administrador (o normal).
/// </summary>
public static class AcoesSeguranca
{
    private const string ChaveBackup = @"SOFTWARE\RDIntranet\InicializacaoDesativada";
    private const string ChaveServicos = @"SOFTWARE\RDIntranet\ServicosDesativados";
    private const string PastaDesativados = "RD.Intranet - desativados";

    public static readonly string PastaQuarentena = Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData), "RDIntranetAgent", "Quarentena");
    private static string ArquivoQuarentena => Path.Combine(PastaQuarentena, "quarentena.json");

    private static readonly HashSet<string> ServicosProtegidos = new(StringComparer.OrdinalIgnoreCase)
    {
        "RpcSs", "RpcEptMapper", "DcomLaunch", "LSM", "SamSs", "Winmgmt", "EventLog", "mpssvc", "BFE", "Dhcp", "Dnscache",
        "LanmanServer", "LanmanWorkstation", "TermService", "WinDefend", "Schedule", "ProfSvc", "gpsvc", "CryptSvc", "nsi",
        "Netman", "NlaSvc", "Power", "PlugPlay", "SENS", "UserManager", "CoreMessagingRegistrar", "RDIntranetAgenteServico", "Mesh Agent"
    };

    // ------------------------------------------------------------------
    // Inicialização
    // ------------------------------------------------------------------

    /// <summary>tipo/id como vêm de ItemInicializacao (ou do portal). ativar=false desativa.</summary>
    public static ResultadoAcao AlternarInicializacao(string tipo, string id, bool ativar)
    {
        try
        {
            if (id.StartsWith("rd|"))
            {
                return ativar ? Reativar(id[3..]) : new ResultadoAcao(true, "Já está desativado.");
            }

            return tipo switch
            {
                "servico" => AlternarServico(id, ativar),
                "tarefa" => AlternarTarefa(id, ativar),
                "registro" or "ifeo" or "winlogon" or "appinit" when !ativar => DesativarRegistro(tipo, id),
                "pasta_inicializar" when !ativar => DesativarArquivoInicializar(id),
                "wmi" when !ativar => RemoverWmi(id),
                _ => new ResultadoAcao(false, ativar ? "Esse item já está ativo." : "Tipo de item desconhecido.")
            };
        }
        catch (UnauthorizedAccessException)
        {
            return new ResultadoAcao(false, "Sem permissão -- o agente precisa estar rodando como administrador.");
        }
        catch (Exception ex)
        {
            return new ResultadoAcao(false, ex.Message);
        }
    }

    private static ResultadoAcao AlternarServico(string nome, bool ativar)
    {
        if (ServicosProtegidos.Contains(nome))
        {
            return new ResultadoAcao(false, $"O serviço {nome} é essencial ao Windows ou ao próprio RD.Intranet.");
        }

        using var chaveServico = Registry.LocalMachine.OpenSubKey($@"SYSTEM\CurrentControlSet\Services\{nome}", writable: true)
            ?? throw new InvalidOperationException($"Serviço {nome} não encontrado.");
        using var backup = Registry.LocalMachine.CreateSubKey(ChaveServicos);

        if (!ativar)
        {
            var inicioAnterior = Convert.ToInt32(chaveServico.GetValue("Start") ?? 3);
            if (inicioAnterior != 4) backup.SetValue(nome, inicioAnterior, RegistryValueKind.DWord);
            chaveServico.SetValue("Start", 4, RegistryValueKind.DWord); // 4 = desativado
            using var controlador = new ServiceController(nome);
            if (controlador.Status != ServiceControllerStatus.Stopped)
            {
                try
                {
                    controlador.Stop();
                    controlador.WaitForStatus(ServiceControllerStatus.Stopped, TimeSpan.FromSeconds(20));
                }
                catch
                {
                    // não parou sozinho: encerra o processo do serviço
                    EncerrarProcessoDoServico(nome);
                }
            }
            Registrar($"Serviço {nome} parado e desativado.");
            return new ResultadoAcao(true, $"Serviço {nome} parado e desativado (não inicia mais com o Windows).");
        }

        var inicio = Convert.ToInt32(backup.GetValue(nome) ?? 2);
        chaveServico.SetValue("Start", inicio == 4 ? 2 : inicio, RegistryValueKind.DWord);
        backup.DeleteValue(nome, false);
        try
        {
            using var controlador = new ServiceController(nome);
            if (inicio == 2) controlador.Start();
        }
        catch { /* religado; se não subir agora, sobe no próximo boot */ }
        Registrar($"Serviço {nome} reativado.");
        return new ResultadoAcao(true, $"Serviço {nome} reativado.");
    }

    private static void EncerrarProcessoDoServico(string nome)
    {
        using var busca = new ManagementObjectSearcher($"SELECT ProcessId FROM Win32_Service WHERE Name = '{nome.Replace("'", "''")}'");
        foreach (ManagementObject s in busca.Get())
        {
            var pid = Convert.ToInt32(s["ProcessId"]);
            if (pid > 4) try { Process.GetProcessById(pid).Kill(true); } catch { }
        }
    }

    private static ResultadoAcao AlternarTarefa(string caminho, bool ativar)
    {
        var (_, erro, codigo) = ExecutarSchtasks($"/Change /TN \"{caminho}\" /{(ativar ? "ENABLE" : "DISABLE")}");
        if (codigo != 0) return new ResultadoAcao(false, $"Não foi possível {(ativar ? "ativar" : "desativar")} a tarefa: {erro.Trim()}");
        if (!ativar) ExecutarSchtasks($"/End /TN \"{caminho}\"");
        Registrar($"Tarefa {caminho} {(ativar ? "ativada" : "desativada")}.");
        return new ResultadoAcao(true, $"Tarefa {caminho} {(ativar ? "ativada" : "desativada")}.");
    }

    private static (string, string, int) ExecutarSchtasks(string argumentos)
    {
        using var p = Process.Start(new ProcessStartInfo("schtasks.exe", argumentos)
        {
            UseShellExecute = false, CreateNoWindow = true, RedirectStandardOutput = true, RedirectStandardError = true
        })!;
        var saida = p.StandardOutput.ReadToEnd();
        var erro = p.StandardError.ReadToEnd();
        p.WaitForExit(20000);
        return (saida, erro, p.ExitCode);
    }

    /// <summary>
    /// Registro: id = "RAIZ|subchave|valor" (Run), programa (IFEO), "Shell"/"Userinit"
    /// (Winlogon) ou a subchave (AppInit). Guarda o valor e remove/neutraliza.
    /// </summary>
    private static ResultadoAcao DesativarRegistro(string tipo, string id)
    {
        string raiz, subchave, valor;
        object? padraoNeutro = null; // Winlogon/AppInit voltam pro valor do Windows em vez de apagar
        switch (tipo)
        {
            case "registro":
                var partes = id.Split('|');
                if (partes.Length != 3) return new ResultadoAcao(false, "Identificador inválido.");
                (raiz, subchave, valor) = (partes[0], partes[1], partes[2]);
                break;
            case "ifeo":
                (raiz, subchave, valor) = ("HKLM", $@"SOFTWARE\Microsoft\Windows NT\CurrentVersion\Image File Execution Options\{id}", "Debugger");
                break;
            case "winlogon":
                (raiz, subchave, valor) = ("HKLM", @"SOFTWARE\Microsoft\Windows NT\CurrentVersion\Winlogon", id);
                padraoNeutro = id == "Shell" ? "explorer.exe" : @"C:\Windows\system32\userinit.exe,";
                break;
            case "appinit":
                (raiz, subchave, valor) = ("HKLM", id, "LoadAppInit_DLLs");
                padraoNeutro = 0;
                break;
            default:
                return new ResultadoAcao(false, "Tipo inválido.");
        }

        using var chave = AbrirRaiz(raiz).OpenSubKey(subchave, writable: true) ?? throw new InvalidOperationException("Chave não encontrada.");
        var original = chave.GetValue(valor, null, RegistryValueOptions.DoNotExpandEnvironmentNames);
        if (original == null) return new ResultadoAcao(false, "Valor não encontrado (talvez já removido).");
        var tipoValor = chave.GetValueKind(valor);

        GuardarBackup(tipo, valor == "Debugger" ? id : valor, $@"{raiz}\{subchave}", Convert.ToString(original) ?? "", new Dictionary<string, object?>
        {
            ["raiz"] = raiz, ["subchave"] = subchave, ["valor"] = valor, ["dado"] = Convert.ToString(original), ["tipo_valor"] = tipoValor.ToString()
        });

        if (padraoNeutro != null) chave.SetValue(valor, padraoNeutro);
        else chave.DeleteValue(valor);

        Registrar($"Inicialização desativada ({tipo}): {raiz}\\{subchave}\\{valor} = {original}");
        return new ResultadoAcao(true, $"Desativado. O valor original ficou guardado e pode ser reativado.");
    }

    private static ResultadoAcao DesativarArquivoInicializar(string arquivo)
    {
        if (!File.Exists(arquivo)) return new ResultadoAcao(false, "Arquivo não encontrado.");
        var pasta = Path.Combine(Path.GetDirectoryName(arquivo)!, PastaDesativados);
        Directory.CreateDirectory(pasta);
        var destino = Path.Combine(pasta, Path.GetFileName(arquivo));
        if (File.Exists(destino)) File.Delete(destino);
        File.Move(arquivo, destino);
        GuardarBackup("pasta_inicializar", Path.GetFileName(arquivo), Path.GetDirectoryName(arquivo)!, arquivo,
            new Dictionary<string, object?> { ["original"] = arquivo, ["desativado"] = destino });
        Registrar($"Inicialização desativada: {arquivo} movido para {pasta}.");
        return new ResultadoAcao(true, "Retirado da pasta Inicializar (guardado para reativar).");
    }

    private static ResultadoAcao RemoverWmi(string id)
    {
        var partes = id.Split('|', 2);
        if (partes.Length != 2 || partes[0] is not ("CommandLineEventConsumer" or "ActiveScriptEventConsumer")) return new ResultadoAcao(false, "Identificador inválido.");
        var escopo = new ManagementScope(@"\\.\root\subscription");
        escopo.Connect();
        var nome = partes[1].Replace("'", "''");

        string comando = "";
        using (var busca = new ManagementObjectSearcher(escopo, new ObjectQuery($"SELECT * FROM {partes[0]} WHERE Name = '{nome}'")))
        {
            foreach (ManagementObject consumidor in busca.Get())
            {
                comando = (consumidor["CommandLineTemplate"] ?? consumidor["ScriptText"] ?? consumidor["ScriptFileName"] ?? "")?.ToString() ?? "";
                var caminhoConsumidor = consumidor.Path.RelativePath;
                // Vínculos filtro -> consumidor primeiro, depois o consumidor.
                using var vinculos = new ManagementObjectSearcher(escopo, new ObjectQuery("SELECT * FROM __FilterToConsumerBinding"));
                foreach (ManagementObject v in vinculos.Get())
                {
                    if ((v["Consumer"]?.ToString() ?? "").Contains(partes[1], StringComparison.OrdinalIgnoreCase)) v.Delete();
                }
                consumidor.Delete();
            }
        }

        GuardarBackup("wmi", partes[1], $@"root\subscription\{partes[0]}", comando, new Dictionary<string, object?> { ["classe"] = partes[0], ["sem_restauracao"] = true });
        Registrar($"Assinatura WMI removida: {partes[0]} \"{partes[1]}\" ({comando}).");
        return new ResultadoAcao(true, "Assinatura WMI removida (o comando ficou registrado no backup).");
    }

    private static ResultadoAcao Reativar(string idBackup)
    {
        using var raiz = Registry.LocalMachine.OpenSubKey(ChaveBackup, writable: true);
        using var item = raiz?.OpenSubKey(idBackup);
        if (raiz == null || item == null) return new ResultadoAcao(false, "Backup não encontrado.");

        var tipo = item.GetValue("Tipo") as string ?? "";
        var dados = JsonSerializer.Deserialize<Dictionary<string, JsonElement>>(item.GetValue("Dados") as string ?? "{}") ?? new();
        string Texto(string k) => dados.TryGetValue(k, out var v) && v.ValueKind == JsonValueKind.String ? v.GetString()! : "";

        if (tipo == "pasta_inicializar")
        {
            File.Move(Texto("desativado"), Texto("original"), overwrite: true);
        }
        else if (tipo == "wmi")
        {
            return new ResultadoAcao(false, "Assinatura WMI não pode ser recriada automaticamente -- o comando original está no detalhe do item.");
        }
        else
        {
            using var chave = AbrirRaiz(Texto("raiz")).CreateSubKey(Texto("subchave"));
            var tipoValor = Enum.TryParse<RegistryValueKind>(Texto("tipo_valor"), out var t) ? t : RegistryValueKind.String;
            object valor = tipoValor == RegistryValueKind.DWord ? int.Parse(Texto("dado")) : Texto("dado");
            chave.SetValue(Texto("valor"), valor, tipoValor is RegistryValueKind.DWord or RegistryValueKind.ExpandString ? tipoValor : RegistryValueKind.String);
        }

        item.Close();
        raiz.DeleteSubKeyTree(idBackup);
        Registrar($"Inicialização reativada ({tipo}): {Texto("original")}{Texto("subchave")}\\{Texto("valor")}");
        return new ResultadoAcao(true, "Reativado.");
    }

    public static List<ItemDesativado> ListarDesativados()
    {
        var lista = new List<ItemDesativado>();
        using var raiz = Registry.LocalMachine.OpenSubKey(ChaveBackup);
        if (raiz == null) return lista;
        foreach (var id in raiz.GetSubKeyNames())
        {
            using var item = raiz.OpenSubKey(id);
            if (item == null) continue;
            lista.Add(new ItemDesativado(id, item.GetValue("Tipo") as string ?? "", item.GetValue("Nome") as string ?? "", item.GetValue("Origem") as string ?? "",
                item.GetValue("Comando") as string ?? "", DateTime.TryParse(item.GetValue("Quando") as string, out var q) ? q : DateTime.MinValue));
        }
        return lista;
    }

    private static void GuardarBackup(string tipo, string nome, string origem, string comando, Dictionary<string, object?> dados)
    {
        var id = $"{DateTime.Now:yyyyMMddHHmmss}-{Guid.NewGuid().ToString("N")[..8]}";
        using var item = Registry.LocalMachine.CreateSubKey($@"{ChaveBackup}\{id}");
        item.SetValue("Tipo", tipo);
        item.SetValue("Nome", nome);
        item.SetValue("Origem", origem);
        item.SetValue("Comando", comando);
        item.SetValue("Quando", DateTime.Now.ToString("s"));
        item.SetValue("Dados", JsonSerializer.Serialize(dados));
    }

    private static RegistryKey AbrirRaiz(string raiz) =>
        raiz == "HKLM" ? Registry.LocalMachine
        : raiz.StartsWith(@"HKU\") ? Registry.Users.OpenSubKey(raiz[4..], writable: true) ?? throw new InvalidOperationException("Perfil do usuário não carregado.")
        : throw new InvalidOperationException("Raiz de registro desconhecida.");

    // ------------------------------------------------------------------
    // Quarentena
    // ------------------------------------------------------------------

    /// <summary>
    /// Encerra o que estiver rodando o arquivo (inclusive "javaw -jar arquivo"),
    /// tira oculto/somente leitura e move pra quarentena com extensão
    /// .quarentena. Aceita pasta (vai inteira).
    /// </summary>
    public static ResultadoAcao Quarentena(string caminho, string motivo)
    {
        try
        {
            caminho = Path.GetFullPath(caminho.Trim().Trim('"'));
            var ehPasta = Directory.Exists(caminho);
            if (!ehPasta && !File.Exists(caminho)) return new ResultadoAcao(false, "Arquivo não encontrado (talvez já removido).");

            var pastaWindows = Environment.GetFolderPath(Environment.SpecialFolder.Windows);
            if (!ehPasta && caminho.StartsWith(Path.Combine(pastaWindows, "System32"), StringComparison.OrdinalIgnoreCase) && AnaliseExecutavel.Analisar(caminho).Microsoft)
            {
                return new ResultadoAcao(false, "Arquivo do próprio Windows -- não vai para a quarentena.");
            }
            if (caminho.Length < 8 || string.Equals(caminho.TrimEnd('\\'), pastaWindows, StringComparison.OrdinalIgnoreCase)
                || caminho.TrimEnd('\\').Count(c => c == '\\') < 2 || string.Equals(caminho, Environment.ProcessPath, StringComparison.OrdinalIgnoreCase))
            {
                return new ResultadoAcao(false, "Caminho protegido.");
            }

            var encerrados = EncerrarQuemUsa(caminho);
            Directory.CreateDirectory(PastaQuarentena);
            var id = $"{DateTime.Now:yyyyMMdd-HHmmss}-{Guid.NewGuid().ToString("N")[..6]}";
            var destino = Path.Combine(PastaQuarentena, $"{id}_{Path.GetFileName(caminho.TrimEnd('\\'))}.quarentena");
            string? hash = ehPasta ? null : AnaliseExecutavel.Sha256(caminho);

            Exception? ultimoErro = null;
            for (var tentativa = 0; tentativa < 4; tentativa++)
            {
                try
                {
                    if (ehPasta)
                    {
                        foreach (var f in Directory.EnumerateFileSystemEntries(caminho, "*", SearchOption.AllDirectories)) TirarAtributos(f);
                        TirarAtributos(caminho);
                        Directory.Move(caminho, destino);
                    }
                    else
                    {
                        TirarAtributos(caminho);
                        File.Move(caminho, destino);
                    }
                    ultimoErro = null;
                    break;
                }
                catch (Exception ex) when (ex is IOException or UnauthorizedAccessException)
                {
                    ultimoErro = ex;
                    encerrados += EncerrarQuemUsa(caminho); // o vigia pode ter relançado
                    Thread.Sleep(1500);
                }
            }
            if (ultimoErro != null) return new ResultadoAcao(false, $"Não consegui mover: {ultimoErro.Message}");

            var lista = ListarQuarentena();
            lista.Add(new ItemQuarentena { Id = id, Original = caminho, Destino = destino, Pasta = ehPasta, Sha256 = hash, Motivo = motivo, Quando = DateTime.Now });
            File.WriteAllText(ArquivoQuarentena, JsonSerializer.Serialize(lista, new JsonSerializerOptions { WriteIndented = true }));

            Registrar($"Quarentena: {caminho} -> {destino}{(hash != null ? $" (SHA256 {hash})" : "")}; {encerrados} processo(s) encerrado(s). Motivo: {motivo}");
            return new ResultadoAcao(true, $"{(ehPasta ? "Pasta" : "Arquivo")} em quarentena{(encerrados > 0 ? $" ({encerrados} processo(s) encerrado(s))" : "")}. Dá para restaurar pela aba Quarentena.");
        }
        catch (Exception ex)
        {
            return new ResultadoAcao(false, ex.Message);
        }
    }

    public static ResultadoAcao RestaurarQuarentena(string id)
    {
        var lista = ListarQuarentena();
        var item = lista.FirstOrDefault(i => i.Id == id);
        if (item == null) return new ResultadoAcao(false, "Item não encontrado na quarentena.");
        if (item.Pasta) Directory.Move(item.Destino, item.Original); else File.Move(item.Destino, item.Original, overwrite: false);
        lista.Remove(item);
        File.WriteAllText(ArquivoQuarentena, JsonSerializer.Serialize(lista, new JsonSerializerOptions { WriteIndented = true }));
        Registrar($"Restaurado da quarentena: {item.Original}");
        return new ResultadoAcao(true, $"Restaurado para {item.Original}.");
    }

    public static ResultadoAcao ExcluirDaQuarentena(string id)
    {
        var lista = ListarQuarentena();
        var item = lista.FirstOrDefault(i => i.Id == id);
        if (item == null) return new ResultadoAcao(false, "Item não encontrado na quarentena.");
        if (item.Pasta) Directory.Delete(item.Destino, true); else File.Delete(item.Destino);
        lista.Remove(item);
        File.WriteAllText(ArquivoQuarentena, JsonSerializer.Serialize(lista, new JsonSerializerOptions { WriteIndented = true }));
        Registrar($"Excluído definitivamente da quarentena: {item.Original}");
        return new ResultadoAcao(true, "Excluído definitivamente.");
    }

    public static List<ItemQuarentena> ListarQuarentena()
    {
        try
        {
            return File.Exists(ArquivoQuarentena) ? JsonSerializer.Deserialize<List<ItemQuarentena>>(File.ReadAllText(ArquivoQuarentena)) ?? new() : new();
        }
        catch
        {
            return new();
        }
    }

    private static void TirarAtributos(string caminho)
    {
        try { File.SetAttributes(caminho, FileAttributes.Normal); } catch { }
    }

    /// <summary>Encerra processos cujo executável é o arquivo (ou fica dentro da pasta) ou que o recebem na linha de comando.</summary>
    private static int EncerrarQuemUsa(string caminho)
    {
        var encerrados = 0;
        try
        {
            using var busca = new ManagementObjectSearcher("SELECT ProcessId, ExecutablePath, CommandLine FROM Win32_Process");
            foreach (ManagementObject p in busca.Get())
            {
                var pid = Convert.ToInt32(p["ProcessId"]);
                if (pid <= 4 || pid == Environment.ProcessId) continue;
                var exe = p["ExecutablePath"] as string ?? "";
                var linha = p["CommandLine"] as string ?? "";
                if (exe.StartsWith(caminho, StringComparison.OrdinalIgnoreCase) || linha.Contains(caminho, StringComparison.OrdinalIgnoreCase))
                {
                    try { Process.GetProcessById(pid).Kill(true); encerrados++; } catch { }
                }
            }
        }
        catch { }
        if (encerrados > 0) Thread.Sleep(800);
        return encerrados;
    }

    // ------------------------------------------------------------------
    // Firewall (perfis)
    // ------------------------------------------------------------------

    /// <summary>
    /// Liga o firewall com entrada e saída LIBERADAS por padrão (nada muda
    /// pro que já funciona -- só passam a valer regras de bloqueio), ou
    /// desliga. Ligar não acontece se houver regra de bloqueio de terceiros
    /// que passaria a valer (pode derrubar sistema) -- avisa quais.
    /// </summary>
    public static ResultadoAcao Firewall(bool ligar, bool forcar = false)
    {
        var script = ligar
            ? $@"
$ErrorActionPreference = 'Stop'
$bloqueios = @(Get-NetFirewallRule -Enabled True -Action Block -ErrorAction SilentlyContinue | Where-Object {{ $_.Group -ne '{FirewallService.Grupo}' -and $_.DisplayName -notlike '{FirewallService.PrefixoRegra}*' }})
if ($bloqueios.Count -gt 0 -and -not ${(forcar ? "true" : "false")}) {{ throw ""Existem $($bloqueios.Count) regra(s) de bloqueio de outros programas que passariam a valer (ex.: $($bloqueios[0].DisplayName)). Confira e confirme para ligar mesmo assim."" }}
Set-NetFirewallProfile -Profile Domain,Private,Public -DefaultInboundAction Allow -DefaultOutboundAction Allow -Enabled True
'ok'"
            : @"
$ErrorActionPreference = 'Stop'
Set-NetFirewallProfile -Profile Domain,Private,Public -Enabled False
'ok'";
        var (saida, erro, codigo) = ExploradorService.ExecutarPowerShell(script, !Program.EstaElevado());
        if (codigo != 0 || !saida.Contains("ok")) return new ResultadoAcao(false, FirewallService.MensagemDeErro(erro, codigo));
        Registrar(ligar ? "Firewall do Windows ligado (entrada e saída liberadas por padrão)." : "Firewall do Windows desligado.");
        return new ResultadoAcao(true, ligar ? "Firewall ligado. Tudo continua liberado por padrão; só os bloqueios passam a valer." : "Firewall desligado.");
    }

    private static void Registrar(string texto) => LogAtividade.Registrar(NivelAtividade.Seguranca, "SEGURANÇA", texto);
}
