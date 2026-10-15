using System.Runtime.InteropServices;
using System.Security.Cryptography;
using System.Security.Cryptography.X509Certificates;
using System.Text.RegularExpressions;

namespace RdIntranetAgente;

/// <summary>Resultado da análise de um arquivo executável (cacheado por caminho + data de modificação).</summary>
public sealed class InfoExecutavel
{
    public string Caminho { get; init; } = "";
    public bool Existe { get; init; }
    public bool Oculto { get; init; }
    /// <summary>valida | catalogo | invalida | ausente | desconhecida</summary>
    public string Assinatura { get; init; } = "desconhecida";
    public string? Assinante { get; init; }
    public bool Microsoft { get; init; }
    public DateTime? CriadoEm { get; init; }
    public DateTime? ModificadoEm { get; init; }
    public long Tamanho { get; init; }
    /// <summary>pe | zip | script | outro -- pelo conteúdo, não pela extensão.</summary>
    public string TipoReal { get; init; } = "outro";
    public List<string> Motivos { get; } = new();
    public int Pontos { get; set; }

    public bool Assinado => Assinatura is "valida" or "catalogo" or "pacote";

    /// <summary>Tem alguma assinatura, mesmo que expirada ou de certificado que o Windows não reconhece (MeshAgent, certificado próprio).</summary>
    public bool TemAssinatura => Assinado || Assinatura is "expirada" or "nao_confiavel";
}

/// <summary>Avaliação final de um item (processo ou entrada de inicialização): arquivo + contexto.</summary>
public sealed class Avaliacao
{
    public int Pontos { get; set; }
    public List<string> Motivos { get; } = new();

    /// <summary>ok | atencao | suspeito | critico</summary>
    public string Nivel => Pontos >= 60 ? "critico" : Pontos >= 35 ? "suspeito" : Pontos >= 15 ? "atencao" : "ok";

    public void Somar(int pontos, string motivo)
    {
        if (Motivos.Contains(motivo)) return;
        Pontos += pontos;
        Motivos.Add(motivo);
    }
}

/// <summary>
/// Análise crítica de executáveis -- base do monitor de conexões, do
/// inventário de inicialização e da lista de processos suspeitos.
///
/// Nasceu do caso do ARIIS (Maurílio, 14/10/2026): um serviço "SQLite"
/// (SQLite.exe oculto) que abria "Runtime Broker.exe" (com espaço, oculto,
/// sem assinatura -- imitando o RuntimeBroker.exe do Windows), que rodava
/// um .bat em C:\Windows\Temp relançando um javaw.exe escondido em
/// jre1.8.0_381\lib\jre1.8.0_162 executando um .jar renomeado pra .exe.
/// Nenhum sinal sozinho prova nada (muito ERP pequeno não assina o .exe);
/// a soma dos pontos é que separa "atenção" de "suspeito"/"crítico".
/// </summary>
public static class AnaliseExecutavel
{
    private static readonly object Trava = new();
    private static readonly Dictionary<string, (DateTime modificado, long tamanho, InfoExecutavel info)> Cache = new(StringComparer.OrdinalIgnoreCase);

    private static readonly string PastaWindows = Environment.GetFolderPath(Environment.SpecialFolder.Windows).TrimEnd('\\') + "\\";

    /// <summary>Nomes de processos do Windows que malware gosta de imitar (comparados sem espaço/hífen/maiúscula).</summary>
    private static readonly string[] NomesDoWindows =
    {
        "svchost.exe", "runtimebroker.exe", "lsass.exe", "csrss.exe", "winlogon.exe", "wininit.exe", "services.exe",
        "smss.exe", "explorer.exe", "dllhost.exe", "taskhostw.exe", "taskhost.exe", "conhost.exe", "spoolsv.exe",
        "searchindexer.exe", "searchhost.exe", "sihost.exe", "ctfmon.exe", "dwm.exe", "fontdrvhost.exe", "lsm.exe",
        "audiodg.exe", "wmiprvse.exe", "msmpeng.exe", "securityhealthservice.exe", "rundll32.exe", "regsvr32.exe",
        "werfault.exe", "wuauclt.exe", "usocoreworker.exe", "msdtc.exe", "taskmgr.exe", "systemsettings.exe"
    };

    private static readonly (Regex padrao, int pontos, string motivo)[] PadroesLinhaComando =
    {
        (new Regex(@"(powershell|pwsh)(\.exe)?[""']?\s(.*\s)?-(e|ec|en|enc|enco|encod|encode|encoded|encodedcommand)\s", RegexOptions.IgnoreCase), 30, "PowerShell com comando codificado (-EncodedCommand)"),
        (new Regex(@"(powershell|pwsh)(\.exe)?[""']?\s(.*\s)?-w(indowstyle)?\s+h(idden)?\b", RegexOptions.IgnoreCase), 20, "PowerShell com janela oculta (-WindowStyle Hidden)"),
        (new Regex(@"downloadstring|downloadfile|invoke-webrequest|\biwr\s|\birm\s|net\.webclient|frombase64string", RegexOptions.IgnoreCase), 30, "baixa ou decodifica conteúdo pela linha de comando"),
        (new Regex(@"certutil(\.exe)?\s.*-urlcache|bitsadmin(\.exe)?\s.*/transfer", RegexOptions.IgnoreCase), 35, "usa certutil/bitsadmin para baixar arquivo"),
        (new Regex(@"mshta(\.exe)?\s+[""']?(https?:|javascript:|vbscript:)", RegexOptions.IgnoreCase), 45, "mshta executando script remoto"),
        (new Regex(@"regsvr32(\.exe)?\s.*/i:\s*https?:", RegexOptions.IgnoreCase), 45, "regsvr32 carregando script remoto"),
        (new Regex(@"rundll32(\.exe)?\s.*javascript:", RegexOptions.IgnoreCase), 45, "rundll32 executando javascript"),
        (new Regex(@"\\(windows\\)?temp\\[^""]*\.(bat|cmd|vbs|js|ps1)\b", RegexOptions.IgnoreCase), 25, "executa script de pasta temporária"),
        (new Regex(@"-jar\s+""?[^""]+\.exe\b", RegexOptions.IgnoreCase), 40, "Java executando arquivo .exe como pacote .jar")
    };

    public static InfoExecutavel Analisar(string? caminho)
    {
        caminho = Normalizar(caminho);
        if (string.IsNullOrEmpty(caminho))
        {
            return new InfoExecutavel { Caminho = "", Existe = false };
        }

        FileInfo arquivo;
        try
        {
            arquivo = new FileInfo(caminho);
        }
        catch
        {
            return new InfoExecutavel { Caminho = caminho, Existe = false };
        }

        if (!arquivo.Exists)
        {
            return new InfoExecutavel { Caminho = caminho, Existe = false };
        }

        lock (Trava)
        {
            if (Cache.TryGetValue(caminho, out var c) && c.modificado == arquivo.LastWriteTimeUtc && c.tamanho == arquivo.Length)
            {
                return c.info;
            }
        }

        // App da Microsoft Store/MSIX: o Windows só instala em WindowsApps com o pacote assinado.
        // "Alias de execução" (AppData\Local\Microsoft\WindowsApps\ms-teams.exe):
        // atalho de 0 byte do próprio Windows para um app da loja -- não tem
        // assinatura própria, e conferir dava "inválida" (Teams no MDA-PC-0006).
        var alias = arquivo.Attributes.HasFlag(FileAttributes.ReparsePoint)
                    && caminho.Contains(@"\AppData\Local\Microsoft\WindowsApps\", StringComparison.OrdinalIgnoreCase);
        var (assinatura, assinante) = caminho.Contains(@"\Program Files\WindowsApps\", StringComparison.OrdinalIgnoreCase) || alias
            ? ("pacote", PublicadorDoPacote(caminho))
            : VerificarAssinatura(caminho);
        var info = new InfoExecutavel
        {
            Caminho = caminho,
            Existe = true,
            Oculto = arquivo.Attributes.HasFlag(FileAttributes.Hidden),
            Assinatura = assinatura,
            Assinante = assinante,
            Microsoft = assinante != null && assinante.Contains("Microsoft", StringComparison.OrdinalIgnoreCase) && assinatura is "valida" or "catalogo" or "pacote",
            CriadoEm = arquivo.CreationTime,
            ModificadoEm = arquivo.LastWriteTime,
            Tamanho = arquivo.Length,
            TipoReal = TipoPeloConteudo(caminho)
        };
        PontuarArquivo(info);

        lock (Trava)
        {
            if (Cache.Count > 5000) Cache.Clear();
            Cache[caminho] = (arquivo.LastWriteTimeUtc, arquivo.Length, info);
        }

        return info;
    }

    /// <summary>
    /// Arquivo + contexto (como roda, por quem foi aberto, linha de
    /// comando). $comoSystem = serviço/tarefa como LocalSystem.
    /// </summary>
    public static Avaliacao Avaliar(InfoExecutavel exe, string? linhaComando = null, bool comoSystem = false, string? nomeExibido = null)
    {
        var avaliacao = new Avaliacao();
        // Marcado como confiável pela TI (tela Segurança / portal): fica fora da análise de risco.
        if (exe.Existe && ConfiaveisService.Buscar(exe.Caminho) is { } confiavel)
        {
            avaliacao.Motivos.Add($"confiável (TI): {confiavel.Descricao}");
            return avaliacao;
        }
        if (exe.Existe)
        {
            avaliacao.Pontos = exe.Pontos;
            avaliacao.Motivos.AddRange(exe.Motivos);
        }

        // Nome mostrado (processo/serviço) imitando o Windows mesmo que o arquivo tenha outro nome.
        if (!string.IsNullOrEmpty(nomeExibido) && ImitaWindows(nomeExibido, exe.Caminho, exe.Microsoft) is { } imitado)
        {
            avaliacao.Somar(50, $"nome \"{nomeExibido}\" imita o {imitado} do Windows");
        }

        if (comoSystem && exe.Existe && !exe.TemAssinatura)
        {
            avaliacao.Somar(10, "roda como SYSTEM sem assinatura digital");
        }

        if (!string.IsNullOrEmpty(linhaComando)
            && ProgramaReal(exe.Caminho, linhaComando) is var alvoConfiavel && alvoConfiavel != exe.Caminho
            && ConfiaveisService.Buscar(alvoConfiavel) is { } scriptConfiavel)
        {
            avaliacao.Motivos.Add($"confiável (TI): {scriptConfiavel.Descricao}");
            return avaliacao;
        }

        if (!string.IsNullOrEmpty(linhaComando))
        {
            foreach (var (padrao, pontos, motivo) in PadroesLinhaComando)
            {
                if (padrao.IsMatch(" " + linhaComando + " ")) avaliacao.Somar(pontos, motivo);
            }

            // Interpretador (java, wscript, powershell...) rodando um arquivo: o arquivo também é analisado.
            var alvo = AlvoDeInterpretador(exe.Caminho, linhaComando);
            if (alvo != null)
            {
                var info = Analisar(alvo);
                if (info.Existe && info.Pontos > 0)
                {
                    foreach (var m in info.Motivos) avaliacao.Somar(info.Pontos / Math.Max(1, info.Motivos.Count), $"arquivo executado ({Path.GetFileName(alvo)}): {m}");
                }
            }
        }

        // Java/Python/Node "portátil" escondido dentro da pasta de OUTRO programa.
        if (Regex.IsMatch(exe.Caminho, @"\\lib\\jre[^\\]*\\bin\\javaw?\.exe$", RegexOptions.IgnoreCase))
        {
            avaliacao.Somar(40, "Java escondido dentro da pasta de outra instalação do Java");
        }

        if (exe.Microsoft && exe.Caminho.StartsWith(PastaWindows, StringComparison.OrdinalIgnoreCase) && avaliacao.Motivos.Count == 0)
        {
            avaliacao.Pontos = 0;
        }

        // "Criado recentemente" só pesa junto com outro sinal.
        if (avaliacao.Pontos > 0 && exe.CriadoEm is { } criado && (DateTime.Now - criado).TotalDays <= 30)
        {
            avaliacao.Somar(5, $"arquivo criado há {(int)(DateTime.Now - criado).TotalDays} dia(s)");
        }

        return avaliacao;
    }

    /// <summary>SHA256 só pros suspeitos (arquivo grande pesa); null se não der.</summary>
    public static string? Sha256(string caminho)
    {
        try
        {
            var info = new FileInfo(caminho);
            if (!info.Exists || info.Length > 200L * 1024 * 1024) return null;
            using var fluxo = new FileStream(caminho, FileMode.Open, FileAccess.Read, FileShare.ReadWrite | FileShare.Delete);
            return Convert.ToHexString(SHA256.HashData(fluxo));
        }
        catch
        {
            return null;
        }
    }

    /// <summary>Extrai o executável de uma linha de comando ("C:\x y\a.exe" -p, C:\a.exe -p, %windir%\..., rundll32 a.dll,Entrada).</summary>
    public static string? ExecutavelDaLinha(string? linha)
    {
        if (string.IsNullOrWhiteSpace(linha)) return null;
        linha = Environment.ExpandEnvironmentVariables(linha.Trim());

        string candidato;
        if (linha.StartsWith('"'))
        {
            var fim = linha.IndexOf('"', 1);
            candidato = fim > 1 ? linha[1..fim] : linha.Trim('"');
        }
        else
        {
            var m = Regex.Match(linha, @"^(.+?\.(exe|com|bat|cmd|dll|scr|vbs|js|ps1|jar|msi|cpl))(\s|,|$)", RegexOptions.IgnoreCase);
            candidato = m.Success ? m.Groups[1].Value : linha.Split(' ')[0];
        }

        candidato = Normalizar(candidato) ?? candidato;
        if (!Path.IsPathRooted(candidato))
        {
            // Além de System32/Windows, o PATH -- powershell.exe fica em
            // System32\WindowsPowerShell\v1.0 e wmic em System32\wbem; sem isso
            // o item aparecia como "arquivo não existe" e o Confiar falhava.
            var pastasPath = (Environment.GetEnvironmentVariable("PATH", EnvironmentVariableTarget.Machine) ?? "")
                .Split(';', StringSplitOptions.RemoveEmptyEntries | StringSplitOptions.TrimEntries)
                .Select(Environment.ExpandEnvironmentVariables)
                .Where(p => Path.IsPathRooted(p));
            foreach (var pasta in new[] { Path.Combine(PastaWindows, "System32"), PastaWindows.TrimEnd('\\') }.Concat(pastasPath))
            {
                var tentativa = Path.Combine(pasta, candidato.EndsWith(".exe", StringComparison.OrdinalIgnoreCase) || candidato.Contains('.') ? candidato : candidato + ".exe");
                if (File.Exists(tentativa)) return tentativa;
            }
        }

        return candidato;
    }

    // ------------------------------------------------------------------

    private static string? Normalizar(string? caminho)
    {
        if (string.IsNullOrWhiteSpace(caminho)) return null;
        caminho = Environment.ExpandEnvironmentVariables(caminho.Trim().Trim('"'));
        if (caminho.StartsWith(@"\??\") || caminho.StartsWith(@"\\?\")) caminho = caminho[4..];
        if (caminho.StartsWith(@"\SystemRoot\", StringComparison.OrdinalIgnoreCase)) caminho = PastaWindows + caminho[12..];
        if (caminho.StartsWith(@"system32\", StringComparison.OrdinalIgnoreCase)) caminho = PastaWindows + caminho;
        return caminho;
    }

    private static void PontuarArquivo(InfoExecutavel info)
    {
        var caminho = info.Caminho;
        var nome = Path.GetFileName(caminho);
        var naPastaDoWindows = caminho.StartsWith(PastaWindows, StringComparison.OrdinalIgnoreCase);

        void Somar(int pontos, string motivo)
        {
            if (info.Motivos.Contains(motivo)) return;
            info.Pontos += pontos;
            info.Motivos.Add(motivo);
        }

        if (info.Oculto) Somar(35, "arquivo oculto");

        if (info.Assinatura == "invalida") Somar(35, "assinatura digital inválida ou adulterada");
        else if (info.Assinatura == "ausente") Somar(20, "sem assinatura digital");
        else if (info.Assinatura == "nao_confiavel") Somar(10, $"assinado por certificado que o Windows não reconhece ({info.Assinante ?? "desconhecido"})");
        else if (info.Assinatura == "expirada") Somar(5, "certificado da assinatura expirado");

        // Pasta estranha pesa de verdade só sem assinatura (instalador assinado roda do Temp o tempo todo).
        var pesoLocal = info.TemAssinatura ? 0.25 : 1.0;
        if (Regex.IsMatch(caminho, @"\\(AppData\\Local\\Temp|Windows\\Temp|Temp)\\", RegexOptions.IgnoreCase)) Somar((int)(30 * pesoLocal), "fica em pasta temporária");
        else if (caminho.Contains(@"\$Recycle.Bin\", StringComparison.OrdinalIgnoreCase)) Somar(45, "fica na lixeira");
        else if (Regex.IsMatch(caminho, @"\\Users\\Public\\", RegexOptions.IgnoreCase)) Somar((int)(15 * pesoLocal), "fica na pasta pública de usuários");
        else if (Regex.IsMatch(caminho, @"^[A-Z]:\\ProgramData\\[^\\]+(\\[^\\]+)?$", RegexOptions.IgnoreCase) && !info.Assinado) Somar(15, "solto na raiz do ProgramData");
        else if (Regex.IsMatch(caminho, @"\\AppData\\(Roaming|Local)\\", RegexOptions.IgnoreCase)
                 && !Regex.IsMatch(caminho, @"\\AppData\\Local\\Programs\\", RegexOptions.IgnoreCase) && !info.Assinado) Somar(10, "fica no perfil do usuário (AppData)");

        if (naPastaDoWindows && !info.Assinado
            && !Regex.IsMatch(caminho, @"\\(Microsoft\.NET|assembly|WinSxS|servicing|SoftwareDistribution|Installer)\\", RegexOptions.IgnoreCase))
        {
            Somar(15, "dentro da pasta do Windows sem assinatura");
        }

        // Ativadores piratas de Windows/Office: não são "o vírus" em si, mas
        // deixam a licença irregular, rodam como serviço sem assinatura e vêm
        // de sites que costumam embutir trojan (KMS-R@1n no MDA-PC-0006).
        if (Regex.IsMatch(caminho, @"KMS-?R@1n|KMSAuto|AutoKMS|KMSpico|KMS_VL_ALL|SECOH-QAD|KMSELDI|KMS-QADhook", RegexOptions.IgnoreCase))
        {
            Somar(20, "ativador pirata de Windows/Office (KMS) -- licença irregular e origem comum de vírus");
        }

        if (ImitaWindows(nome, caminho, info.Microsoft) is { } imitado)
        {
            Somar(50, $"nome \"{nome}\" imita o {imitado} do Windows");
        }

        var extensao = Path.GetExtension(caminho).ToLowerInvariant();
        if (extensao is ".exe" or ".dll" or ".scr" or ".com")
        {
            if (info.TipoReal == "zip") Somar(40, $"arquivo {extensao} que na verdade é um pacote ZIP/JAR");
            else if (info.TipoReal == "script") Somar(30, $"arquivo {extensao} que na verdade é um script de texto");
        }
    }

    /// <summary>Nome do processo do Windows imitado, ou null. Igual de verdade E no lugar certo = não imita.</summary>
    private static string? ImitaWindows(string nome, string caminho, bool assinadoMicrosoft)
    {
        if (string.IsNullOrEmpty(nome)) return null;
        if (!nome.EndsWith(".exe", StringComparison.OrdinalIgnoreCase)) nome += ".exe";
        var normalizado = Regex.Replace(nome.ToLowerInvariant(), @"[\s_\-\.]", "").Replace('0', 'o').Replace('1', 'l');

        foreach (var original in NomesDoWindows)
        {
            var alvo = original.Replace(".", "");
            if (normalizado != alvo) continue;

            var mesmoNome = string.Equals(nome, original, StringComparison.OrdinalIgnoreCase);
            var lugarCerto = caminho.StartsWith(PastaWindows, StringComparison.OrdinalIgnoreCase) || string.IsNullOrEmpty(caminho) || assinadoMicrosoft;
            if (mesmoNome && lugarCerto) return null;
            return original;
        }

        return null;
    }

    /// <summary>
    /// O que de fato é "o programa" de uma linha de comando: o script/jar
    /// quando o executável é um interpretador (powershell -File x.ps1), senão
    /// o próprio executável. Confiar no powershell.exe inteiro liberaria
    /// qualquer script -- o certo é confiar no x.ps1.
    /// </summary>
    public static string ProgramaReal(string exe, string? linha)
        => (string.IsNullOrEmpty(linha) ? null : AlvoDeInterpretador(exe, linha)) is { } alvo
           ? Normalizar(alvo) ?? alvo
           : exe;

    private static string? AlvoDeInterpretador(string exe, string linha)
    {
        var nome = Path.GetFileName(exe).ToLowerInvariant();
        Match m;
        if (nome is "java.exe" or "javaw.exe")
        {
            m = Regex.Match(linha, @"-jar\s+(""([^""]+)""|(\S+))", RegexOptions.IgnoreCase);
            return m.Success ? (m.Groups[2].Success && m.Groups[2].Value != "" ? m.Groups[2].Value : m.Groups[3].Value) : null;
        }
        if (nome is "wscript.exe" or "cscript.exe" or "mshta.exe" or "powershell.exe" or "pwsh.exe" or "cmd.exe" or "rundll32.exe" or "regsvr32.exe")
        {
            m = Regex.Match(linha, @"(""([^""]+\.(vbs|js|jse|vbe|ps1|bat|cmd|hta|dll|exe))""|(\S+\.(vbs|js|jse|vbe|ps1|bat|cmd|hta|dll)))", RegexOptions.IgnoreCase);
            if (!m.Success) return null;
            var alvo = m.Groups[2].Success && m.Groups[2].Value != "" ? m.Groups[2].Value : m.Groups[4].Value;
            return Path.GetFileName(alvo).Equals(Path.GetFileName(exe), StringComparison.OrdinalIgnoreCase) ? null : alvo.Split(',')[0];
        }

        return null;
    }

    private static string TipoPeloConteudo(string caminho)
    {
        try
        {
            using var fluxo = new FileStream(caminho, FileMode.Open, FileAccess.Read, FileShare.ReadWrite | FileShare.Delete);
            var cabecalho = new byte[4];
            var lidos = fluxo.Read(cabecalho, 0, 4);
            if (lidos >= 2 && cabecalho[0] == 'M' && cabecalho[1] == 'Z') return "pe";
            if (lidos >= 4 && cabecalho[0] == 'P' && cabecalho[1] == 'K' && cabecalho[2] == 3 && cabecalho[3] == 4) return "zip";
            if (lidos >= 2 && cabecalho.Take(lidos).All(b => b is >= 0x20 and < 0x7F or (byte)'\r' or (byte)'\n' or (byte)'\t')) return "script";
            return "outro";
        }
        catch
        {
            return "outro";
        }
    }

    // ------------------------------------------------------------------
    // Assinatura digital: embutida (WinVerifyTrust) ou por catálogo do
    // Windows (a maioria dos .exe do System32 não tem assinatura própria
    // -- vem num .cat em CatRoot; sem essa checagem todo o Windows
    // pareceria "sem assinatura").
    // ------------------------------------------------------------------

    private static (string assinatura, string? assinante) VerificarAssinatura(string caminho)
    {
        try
        {
            var resultado = VerificarEmbutida(caminho);
            if (resultado == 0)
            {
                return ("valida", NomeDoAssinante(caminho));
            }

            if (resultado is TrustENoSignature or TrustESubjectFormUnknown or TrustEProviderUnknown)
            {
                var catalogo = ProcurarCatalogo(caminho);
                if (catalogo != null)
                {
                    var (ok, arquivoCatalogo) = catalogo.Value;
                    return ok ? ("catalogo", NomeDoAssinante(arquivoCatalogo) ?? "Windows (catálogo)") : ("invalida", null);
                }

                return ("ausente", null);
            }

            // Assinado, mas: certificado vencido (sem carimbo de tempo) ou de
            // emissor que o Windows não conhece (MeshAgent usa o certificado do
            // próprio MeshCentral) -- bem diferente de arquivo ADULTERADO.
            return resultado switch
            {
                CertEExpirado or CertEPeriodoInvalido => ("expirada", NomeDoAssinante(caminho)),
                CertERaizNaoConfiavel or CertEEncadeamento or CertERaizDeTeste => ("nao_confiavel", NomeDoAssinante(caminho)),
                _ => ("invalida", NomeDoAssinante(caminho)) // hash não confere, revogado, desconfiado explicitamente
            };
        }
        catch
        {
            return ("desconhecida", null);
        }
    }

    /// <summary>Publicador do pacote MSIX (pasta "Nome_versão_arq__idDoPublicador" de WindowsApps).</summary>
    private static string? PublicadorDoPacote(string caminho)
    {
        var m = Regex.Match(caminho, @"\\WindowsApps\\([^\\_]+)_", RegexOptions.IgnoreCase);
        if (!m.Success) return null;
        var nome = m.Groups[1].Value;
        // 8wekyb3d8bbwe = identificador de publicador da própria Microsoft (ex.: MSTeams_8wekyb3d8bbwe).
        return nome.StartsWith("Microsoft.", StringComparison.OrdinalIgnoreCase) || caminho.Contains("_8wekyb3d8bbwe", StringComparison.OrdinalIgnoreCase) ? "Microsoft Corporation (pacote)" : nome.Split('.')[0] + " (pacote da loja)";
    }

    private static string? NomeDoAssinante(string caminho)
    {
        try
        {
#pragma warning disable SYSLIB0057
            var certificado = X509Certificate.CreateFromSignedFile(caminho);
#pragma warning restore SYSLIB0057
            var assunto = certificado.Subject;
            var cn = Regex.Match(assunto, @"CN=(""[^""]+""|[^,]+)").Groups[1].Value.Trim('"');
            var o = Regex.Match(assunto, @"\bO=(""[^""]+""|[^,]+)").Groups[1].Value.Trim('"');
            return string.IsNullOrEmpty(o) || o == cn ? cn : $"{cn} ({o})";
        }
        catch
        {
            return null;
        }
    }

    private const int TrustENoSignature = unchecked((int)0x800B0100);
    private const int TrustESubjectFormUnknown = unchecked((int)0x800B0003);
    private const int TrustEProviderUnknown = unchecked((int)0x800B0001);
    private const int CertEExpirado = unchecked((int)0x800B0101);
    private const int CertEPeriodoInvalido = unchecked((int)0x800B0102);
    private const int CertERaizNaoConfiavel = unchecked((int)0x800B0109);
    private const int CertEEncadeamento = unchecked((int)0x800B010A);
    private const int CertERaizDeTeste = unchecked((int)0x800B010D);

    private static readonly Guid AcaoVerificar = new("00AAC56B-CD44-11d0-8CC2-00C04FC295EE");

    private static int VerificarEmbutida(string caminho)
    {
        var arquivo = new WinTrustFileInfo { cbStruct = (uint)Marshal.SizeOf<WinTrustFileInfo>(), pcwszFilePath = caminho };
        var ponteiro = Marshal.AllocHGlobal(Marshal.SizeOf<WinTrustFileInfo>());
        try
        {
            Marshal.StructureToPtr(arquivo, ponteiro, false);
            var dados = NovoWinTrustData(1, ponteiro);
            var acao = AcaoVerificar;
            return WinVerifyTrust(IntPtr.Zero, ref acao, ref dados);
        }
        finally
        {
            Marshal.DestroyStructure<WinTrustFileInfo>(ponteiro);
            Marshal.FreeHGlobal(ponteiro);
        }
    }

    /// <summary>(assinatura ok?, arquivo .cat) se o hash do arquivo estiver em algum catálogo; null se não estiver em nenhum.</summary>
    private static (bool ok, string catalogo)? ProcurarCatalogo(string caminho)
    {
        foreach (var algoritmo in new[] { "SHA256", null })
        {
            IntPtr admin;
            var adquiriu = algoritmo != null
                ? CryptCATAdminAcquireContext2(out admin, IntPtr.Zero, algoritmo, IntPtr.Zero, 0)
                : CryptCATAdminAcquireContext(out admin, IntPtr.Zero, 0);
            if (!adquiriu) continue;

            try
            {
                using var fluxo = new FileStream(caminho, FileMode.Open, FileAccess.Read, FileShare.ReadWrite | FileShare.Delete);
                var handle = fluxo.SafeFileHandle.DangerousGetHandle();

                uint tamanhoHash = 0;
                if (algoritmo != null) CryptCATAdminCalcHashFromFileHandle2(admin, handle, ref tamanhoHash, null, 0);
                else CryptCATAdminCalcHashFromFileHandle(handle, ref tamanhoHash, null, 0);
                if (tamanhoHash == 0 || tamanhoHash > 128) continue;

                var hash = new byte[tamanhoHash];
                var calculou = algoritmo != null
                    ? CryptCATAdminCalcHashFromFileHandle2(admin, handle, ref tamanhoHash, hash, 0)
                    : CryptCATAdminCalcHashFromFileHandle(handle, ref tamanhoHash, hash, 0);
                if (!calculou) continue;

                var contextoCatalogo = CryptCATAdminEnumCatalogFromHash(admin, hash, tamanhoHash, 0, IntPtr.Zero);
                if (contextoCatalogo == IntPtr.Zero) continue;

                try
                {
                    var infoCatalogo = new CatalogInfo { cbStruct = (uint)Marshal.SizeOf<CatalogInfo>() };
                    if (!CryptCATCatalogInfoFromContext(contextoCatalogo, ref infoCatalogo, 0)) continue;

                    var tag = Convert.ToHexString(hash);
                    var catalogo = new WinTrustCatalogInfo
                    {
                        cbStruct = (uint)Marshal.SizeOf<WinTrustCatalogInfo>(),
                        pcwszCatalogFilePath = infoCatalogo.wszCatalogFile,
                        pcwszMemberTag = tag,
                        pcwszMemberFilePath = caminho,
                        hMemberFile = handle,
                        hCatAdmin = admin
                    };
                    var ponteiro = Marshal.AllocHGlobal(Marshal.SizeOf<WinTrustCatalogInfo>());
                    try
                    {
                        Marshal.StructureToPtr(catalogo, ponteiro, false);
                        var dados = NovoWinTrustData(2, ponteiro);
                        var acao = AcaoVerificar;
                        return (WinVerifyTrust(IntPtr.Zero, ref acao, ref dados) == 0, infoCatalogo.wszCatalogFile);
                    }
                    finally
                    {
                        Marshal.DestroyStructure<WinTrustCatalogInfo>(ponteiro);
                        Marshal.FreeHGlobal(ponteiro);
                    }
                }
                finally
                {
                    CryptCATAdminReleaseCatalogContext(admin, contextoCatalogo, 0);
                }
            }
            catch
            {
                // arquivo travado, sem acesso: tenta o próximo algoritmo
            }
            finally
            {
                CryptCATAdminReleaseContext(admin, 0);
            }
        }

        return null;
    }

    private static WinTrustData NovoWinTrustData(uint escolha, IntPtr info) => new()
    {
        cbStruct = (uint)Marshal.SizeOf<WinTrustData>(),
        dwUIChoice = 2,            // WTD_UI_NONE
        fdwRevocationChecks = 0,   // WTD_REVOKE_NONE
        dwUnionChoice = escolha,   // 1 = arquivo, 2 = catálogo
        pInfo = info,
        dwStateAction = 0,
        dwProvFlags = 0x1000 | 0x10 // WTD_CACHE_ONLY_URL_RETRIEVAL | WTD_REVOCATION_CHECK_NONE: nunca vai à internet
    };

    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    private struct WinTrustFileInfo
    {
        public uint cbStruct;
        public string pcwszFilePath;
        public IntPtr hFile;
        public IntPtr pgKnownSubject;
    }

    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    private struct WinTrustCatalogInfo
    {
        public uint cbStruct;
        public uint dwCatalogVersion;
        public string pcwszCatalogFilePath;
        public string pcwszMemberTag;
        public string pcwszMemberFilePath;
        public IntPtr hMemberFile;
        public IntPtr pbCalculatedFileHash;
        public uint cbCalculatedFileHash;
        public IntPtr pcCatalogContext;
        public IntPtr hCatAdmin;
    }

    [StructLayout(LayoutKind.Sequential)]
    private struct WinTrustData
    {
        public uint cbStruct;
        public IntPtr pPolicyCallbackData;
        public IntPtr pSIPClientData;
        public uint dwUIChoice;
        public uint fdwRevocationChecks;
        public uint dwUnionChoice;
        public IntPtr pInfo;
        public uint dwStateAction;
        public IntPtr hWVTStateData;
        public IntPtr pwszURLReference;
        public uint dwProvFlags;
        public uint dwUIContext;
        public IntPtr pSignatureSettings;
    }

    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    private struct CatalogInfo
    {
        public uint cbStruct;
        [MarshalAs(UnmanagedType.ByValTStr, SizeConst = 260)]
        public string wszCatalogFile;
    }

    [DllImport("wintrust.dll", CharSet = CharSet.Unicode)]
    private static extern int WinVerifyTrust(IntPtr hwnd, ref Guid pgActionID, ref WinTrustData pWVTData);

    [DllImport("wintrust.dll", SetLastError = true)]
    private static extern bool CryptCATAdminAcquireContext(out IntPtr phCatAdmin, IntPtr pgSubsystem, uint dwFlags);

    [DllImport("wintrust.dll", SetLastError = true, CharSet = CharSet.Unicode)]
    private static extern bool CryptCATAdminAcquireContext2(out IntPtr phCatAdmin, IntPtr pgSubsystem, string pwszHashAlgorithm, IntPtr pStrongHashPolicy, uint dwFlags);

    [DllImport("wintrust.dll", SetLastError = true)]
    private static extern bool CryptCATAdminCalcHashFromFileHandle(IntPtr hFile, ref uint pcbHash, byte[]? pbHash, uint dwFlags);

    [DllImport("wintrust.dll", SetLastError = true)]
    private static extern bool CryptCATAdminCalcHashFromFileHandle2(IntPtr hCatAdmin, IntPtr hFile, ref uint pcbHash, byte[]? pbHash, uint dwFlags);

    [DllImport("wintrust.dll", SetLastError = true)]
    private static extern IntPtr CryptCATAdminEnumCatalogFromHash(IntPtr hCatAdmin, byte[] pbHash, uint cbHash, uint dwFlags, IntPtr phPrevCatInfo);

    [DllImport("wintrust.dll", SetLastError = true, CharSet = CharSet.Unicode)]
    private static extern bool CryptCATCatalogInfoFromContext(IntPtr hCatInfo, ref CatalogInfo psCatInfo, uint dwFlags);

    [DllImport("wintrust.dll", SetLastError = true)]
    private static extern bool CryptCATAdminReleaseCatalogContext(IntPtr hCatAdmin, IntPtr hCatInfo, uint dwFlags);

    [DllImport("wintrust.dll", SetLastError = true)]
    private static extern bool CryptCATAdminReleaseContext(IntPtr hCatAdmin, uint dwFlags);
}
