using System.Collections.Generic;
using System.IO;
using System.Management;
using System.Security.Cryptography;
using System.Text;
using RdIntranetAgente.Models;

namespace RdIntranetAgente;

/// <summary>Retrato do módulo pra tela -- montado sob trava, seguro de ler de qualquer thread.</summary>
public sealed class StatusSeguranca
{
    public ModuloSeguranca? Configuracao { get; init; }
    public bool CanaryAtivo { get; init; }
    public int CanaryArquivos { get; init; }
    public int CanaryPastas { get; init; }
    public bool FimAtivo { get; init; }
    public int FimPastas { get; init; }
    public int FimAlteracoesJanela { get; init; }
    public bool ShadowAtivo { get; init; }
    public int? ShadowContagem { get; init; }
    public DateTime? ShadowVerificadoEm { get; init; }
    public string? ShadowErro { get; init; }
    public int EventosPendentesEnvio { get; init; }
    public List<EventoSeguranca> EventosRecentes { get; init; } = new();
}

/// <summary>
/// Módulo anti-ransomware do lado do agente. Só DETECTA e reporta -- não
/// encerra processo, não mexe em firewall nem em arquivo do usuário. A
/// reação (alerta, isolamento de rede com ou sem confirmação) é decidida
/// e aplicada pelo servidor (SegurancaIsolamentoService), pelo canal de
/// comandos que já existe. Cada detector liga/desliga sozinho conforme o
/// bloco "modulo_seguranca" do checkin, sem reinstalar nada:
///
/// 1. Arquivos-isca (canary): arquivos ocultos em pastas que ransomware
///    percorre (Documentos, Área de Trabalho, Documentos Públicos), com
///    nomes que ordenam no começo e no fim da pasta. Ninguém tem motivo
///    pra alterar esses arquivos -- mudança de conteúdo, exclusão ou
///    renomeação é tratada como crítica.
/// 2. Shadow copies: conta os pontos de restauração (Win32_ShadowCopy) a
///    cada minuto. O Windows apaga os mais antigos um de cada vez quando
///    o espaço reservado enche -- queda de 1 é rotina; sumir 2 ou mais de
///    uma vez (ou todos) é o padrão de limpeza de backup antes de
///    criptografar.
/// 3. Mudança em massa (FIM): janela deslizante sobre as pastas do
///    usuário. Arquivo NOVO não conta (copiar uma pasta grande não é
///    ataque); conta alteração de arquivo que já existia, troca de
///    extensão e exclusão. Crítico só quando a maior parte do volume é
///    alteração/troca de extensão -- exclusão pura (esvaziar uma pasta)
///    no máximo gera aviso.
/// </summary>
public sealed class SegurancaService : IDisposable
{
    // Nome da máquina no arquivo: com Documentos/Área de Trabalho
    // sincronizados pelo OneDrive em mais de um computador, a isca de uma
    // máquina aparece na outra -- cada uma só vigia as próprias.
    private static readonly string SufixoMaquina = new string(Environment.MachineName.Where(char.IsLetterOrDigit).ToArray());

    private static readonly string[] NomesCanary =
    {
        $"!!!_RD_NAO_ALTERAR_{SufixoMaquina}.docx",
        $"zzz_RD_NAO_ALTERAR_{SufixoMaquina}.xlsx"
    };

    /// <summary>Nomes usados até a 1.0.34 -- removidos ao religar as iscas.</summary>
    private static readonly string[] NomesCanaryAntigos =
    {
        "!!!_RD_NAO_ALTERAR.docx",
        "zzz_RD_NAO_ALTERAR.xlsx"
    };

    private static readonly string[] ExtensoesIgnoradasFim =
    {
        ".tmp", ".temp", ".crdownload", ".opdownload", ".part", ".partial", ".download", ".!ut", ".!qb", ".lnk", ".ini",
        ".db", ".lock", ".lck", ".log", ".etl", ".swp", ".swo", ".~tmp", ".bak", ".cache", ".pyc", ".tlog", ".pdb", ".obj"
    };

    private static readonly string[] TrechosIgnoradosFim =
    {
        @"\.git\", @"\node_modules\", @"\.vs\", @"\.vscode\", @"\.idea\", @"\AppData\", @"\$RECYCLE.BIN\",
        @"\bin\Debug\", @"\bin\Release\", @"\obj\", @"\__pycache__\", @"\.venv\", @"\venv\", @"\.gradle\",
        @"\.dropbox.cache\", @"\.tmp.drivedownload\", @"\.tmp.driveupload\", @"\.sync\"
    };

    /// <summary>
    /// Extensões de destino comuns: renomear PRA uma delas não é sinal de
    /// criptografia (Office .tmp->.docx, download .crdownload->.pdf,
    /// .jpeg->.jpg). Ransomware renomeia pra extensão própria (.locked,
    /// .lockbit, .xyz123).
    /// </summary>
    private static readonly HashSet<string> ExtensoesComuns = new(StringComparer.OrdinalIgnoreCase)
    {
        ".doc", ".docx", ".docm", ".dot", ".dotx", ".xls", ".xlsx", ".xlsm", ".xlsb", ".csv", ".ppt", ".pptx", ".pptm",
        ".pdf", ".txt", ".rtf", ".odt", ".ods", ".odp", ".xml", ".json", ".html", ".htm", ".md", ".msg", ".eml",
        ".jpg", ".jpeg", ".png", ".gif", ".bmp", ".tif", ".tiff", ".webp", ".heic", ".svg", ".ico", ".raw", ".psd",
        ".mp3", ".wav", ".mp4", ".mov", ".avi", ".mkv", ".wmv", ".m4a", ".zip", ".rar", ".7z", ".gz", ".tar",
        ".exe", ".msi", ".dll", ".iso", ".lnk", ".url", ".ini", ".cfg", ".log", ".bak", ".old", ".sql", ".db",
        ".dwg", ".dxf", ".ai", ".eps", ".cdr", ".ofx", ".xps", ".oxps", ".epub", ".pst", ".ost", ".vcf", ".ics",
        ".js", ".ts", ".css", ".php", ".py", ".cs", ".java", ".c", ".h", ".cpp", ".ps1", ".bat", ".cmd", ".sh"
    };

    private readonly Func<Config> _config;
    private readonly Func<string?> _machineGuid;
    private readonly object _trava = new();

    private ModuloSeguranca? _atual;

    // Arquivos-isca
    private readonly List<FileSystemWatcher> _watchersCanary = new();
    private readonly Dictionary<string, string> _hashCanary = new(StringComparer.OrdinalIgnoreCase);
    private readonly Dictionary<string, DateTime> _ultimoDisparoCanary = new(StringComparer.OrdinalIgnoreCase);
    private DateTime _ignorarCanaryAte = DateTime.MinValue;

    // Mudança em massa
    private readonly List<FileSystemWatcher> _watchersFim = new();
    private readonly LinkedList<(DateTime quando, string caminho, char tipo, string? extNova)> _janelaFim = new();
    private readonly Dictionary<string, DateTime> _criadosRecentemente = new(StringComparer.OrdinalIgnoreCase);
    private DateTime _fimSilenciadoAte = DateTime.MinValue;
    private bool _fimEstouro;
    private readonly Dictionary<string, Dictionary<string, DateTime>> _notasVistas = new(StringComparer.OrdinalIgnoreCase);

    // Lista pública de ransomware (opcional, ligada na Central de Segurança)
    private HashSet<string> _assinaturasExtensoes = new(StringComparer.OrdinalIgnoreCase);
    private HashSet<string> _assinaturasNotas = new(StringComparer.OrdinalIgnoreCase);
    private string? _assinaturasVersao;
    private bool _baixandoAssinaturas;

    // Shadow copies
    private Dictionary<string, DateTime>? _shadowIds;
    private int? _shadowContagem;
    private DateTime? _shadowVerificadoEm;
    private string? _shadowErro;

    // Envio
    private readonly List<EventoSeguranca> _eventos = new();
    private readonly System.Threading.Timer _timerAvaliacao;
    private int _tick;
    private bool _enviando;

    /// <summary>Detecção nova -- a bandeja usa pra mostrar o balão. Disparado fora da thread de UI.</summary>
    public event Action<EventoSeguranca>? Detectado;

    public SegurancaService(Func<Config> config, Func<string?> machineGuid)
    {
        _config = config;
        _machineGuid = machineGuid;
        _timerAvaliacao = new System.Threading.Timer(_ => Tick(), null, 1000, 1000);
    }

    public void Aplicar(ModuloSeguranca? modulo)
    {
        lock (_trava)
        {
            var anterior = _atual;
            _atual = modulo;

            var canary = modulo?.Canary == true;
            var fim = modulo?.Fim == true;
            var shadow = modulo?.ShadowCopy == true;

            if (canary != (anterior?.Canary == true))
            {
                if (canary) LigarCanary(); else DesligarCanary();
                LogAtividade.Registrar(NivelAtividade.Seguranca, "SEGURANÇA", $"Arquivos-isca {(canary ? "ativados" : "desativados")} pelo servidor.");
            }

            if (fim != (anterior?.Fim == true))
            {
                if (fim) LigarFim(); else DesligarFim();
                LogAtividade.Registrar(NivelAtividade.Seguranca, "SEGURANÇA", $"Monitor de mudança em massa {(fim ? "ativado" : "desativado")} pelo servidor.");
            }

            if (shadow != (anterior?.ShadowCopy == true))
            {
                _shadowContagem = null;
                _shadowIds = null;
                _shadowErro = null;
                _shadowVerificadoEm = null;
                LogAtividade.Registrar(NivelAtividade.Seguranca, "SEGURANÇA", $"Monitor de shadow copies {(shadow ? "ativado" : "desativado")} pelo servidor.");
                if (shadow) _tick = 59; // primeira leitura já no próximo segundo
            }

            if (modulo?.Isolado == true && anterior?.Isolado != true)
            {
                LogAtividade.Registrar(NivelAtividade.Erro, "SEGURANÇA", "Esta máquina está com a rede ISOLADA pela TI.");
            }
            else if (modulo?.Isolado != true && anterior?.Isolado == true)
            {
                LogAtividade.Registrar(NivelAtividade.Sucesso, "SEGURANÇA", "Isolamento de rede removido pela TI.");
            }

            var versaoAssinaturas = modulo?.AssinaturasVersao;
            if (versaoAssinaturas == null && _assinaturasVersao != null)
            {
                _assinaturasExtensoes = new(StringComparer.OrdinalIgnoreCase);
                _assinaturasNotas = new(StringComparer.OrdinalIgnoreCase);
                _assinaturasVersao = null;
                LogAtividade.Registrar(NivelAtividade.Seguranca, "SEGURANÇA", "Lista pública de ransomware desligada pelo servidor.");
            }
            else if (versaoAssinaturas != null && versaoAssinaturas != _assinaturasVersao && !_baixandoAssinaturas)
            {
                _baixandoAssinaturas = true;
                _ = BaixarAssinaturasAsync();
            }
        }
    }

    public StatusSeguranca ObterStatus()
    {
        lock (_trava)
        {
            var corte = DateTime.Now.AddSeconds(-Math.Max(5, _atual?.FimJanelaSegundos ?? 30));
            return new StatusSeguranca
            {
                Configuracao = _atual,
                CanaryAtivo = _watchersCanary.Count > 0,
                CanaryArquivos = _hashCanary.Count,
                CanaryPastas = _watchersCanary.Count,
                FimAtivo = _watchersFim.Count > 0,
                FimPastas = _watchersFim.Count,
                FimAlteracoesJanela = _janelaFim.Count(e => e.quando >= corte),
                ShadowAtivo = _atual?.ShadowCopy == true,
                ShadowContagem = _shadowContagem,
                ShadowVerificadoEm = _shadowVerificadoEm,
                ShadowErro = _shadowErro,
                EventosPendentesEnvio = _eventos.Count(e => !e.Enviado),
                EventosRecentes = _eventos.OrderByDescending(e => e.OcorridoEm).Take(20).ToList()
            };
        }
    }

    // ------------------------------------------------------------------
    // Pastas monitoradas
    // ------------------------------------------------------------------

    private static List<string> PastasUsuario(bool incluirImagens)
    {
        var pastas = new List<string>
        {
            Environment.GetFolderPath(Environment.SpecialFolder.MyDocuments),
            Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory),
            Environment.GetFolderPath(Environment.SpecialFolder.CommonDocuments)
        };

        if (incluirImagens)
        {
            pastas.Add(Environment.GetFolderPath(Environment.SpecialFolder.MyPictures));
        }

        return pastas
            .Where(p => !string.IsNullOrWhiteSpace(p) && Directory.Exists(p))
            .Distinct(StringComparer.OrdinalIgnoreCase)
            .ToList();
    }

    // ------------------------------------------------------------------
    // 1. Arquivos-isca
    // ------------------------------------------------------------------

    private void LigarCanary()
    {
        foreach (var pasta in PastasUsuario(incluirImagens: false))
        {
            try
            {
                foreach (var antigo in NomesCanaryAntigos)
                {
                    var caminhoAntigo = Path.Combine(pasta, antigo);
                    try
                    {
                        if (File.Exists(caminhoAntigo))
                        {
                            File.SetAttributes(caminhoAntigo, FileAttributes.Normal);
                            File.Delete(caminhoAntigo);
                        }
                    }
                    catch
                    {
                        // preso/sem permissão -- inofensivo, ninguém mais vigia esse nome
                    }
                }

                foreach (var nome in NomesCanary)
                {
                    PlantarCanary(Path.Combine(pasta, nome));
                }

                var watcher = new FileSystemWatcher(pasta)
                {
                    IncludeSubdirectories = false,
                    NotifyFilter = NotifyFilters.FileName | NotifyFilters.LastWrite | NotifyFilters.Size
                };
                watcher.Changed += (s, e) => AoMudarCanary(e.FullPath, "alterado");
                watcher.Deleted += (s, e) => AoMudarCanary(e.FullPath, "excluído");
                watcher.Renamed += (s, e) => AoMudarCanary(e.OldFullPath, $"renomeado para {Path.GetFileName(e.FullPath)}");
                watcher.EnableRaisingEvents = true;
                _watchersCanary.Add(watcher);
            }
            catch (Exception ex)
            {
                LogAtividade.Registrar(NivelAtividade.Aviso, "SEGURANÇA", $"Não consegui preparar arquivos-isca em {pasta}: {ex.Message}");
            }
        }
    }

    private void DesligarCanary()
    {
        foreach (var w in _watchersCanary)
        {
            w.EnableRaisingEvents = false;
            w.Dispose();
        }
        _watchersCanary.Clear();

        foreach (var caminho in _hashCanary.Keys.ToList())
        {
            try
            {
                if (File.Exists(caminho))
                {
                    File.SetAttributes(caminho, FileAttributes.Normal);
                    File.Delete(caminho);
                }
            }
            catch
            {
                // arquivo preso/sem permissão -- fica pra trás, inofensivo
            }
        }
        _hashCanary.Clear();
    }

    private void PlantarCanary(string caminho)
    {
        _ignorarCanaryAte = DateTime.Now.AddSeconds(3);

        if (!File.Exists(caminho))
        {
            var texto = new StringBuilder()
                .AppendLine("Arquivo de proteção do RD Intranet -- NÃO ALTERE, MOVA OU APAGUE.")
                .AppendLine("Serve para detectar ransomware: qualquer alteração aqui gera um alerta para a TI.")
                .AppendLine(Convert.ToBase64String(RandomNumberGenerator.GetBytes(3072)))
                .ToString();

            File.WriteAllText(caminho, texto, Encoding.UTF8);
            File.SetAttributes(caminho, FileAttributes.Hidden);
        }

        _hashCanary[caminho] = HashArquivo(caminho) ?? "";
    }

    private void AoMudarCanary(string caminho, string mudanca)
    {
        string? pasta;

        lock (_trava)
        {
            if (!_hashCanary.TryGetValue(caminho, out var hashConhecido) || DateTime.Now < _ignorarCanaryAte)
            {
                return;
            }

            // Antivírus/indexador podem disparar "Changed" só lendo o
            // arquivo (atributos, timestamps) -- só vale se o CONTEÚDO mudou.
            if (mudanca == "alterado")
            {
                var hashAtual = HashArquivo(caminho);
                if (hashAtual == null || hashAtual == hashConhecido)
                {
                    return;
                }
            }

            if (_ultimoDisparoCanary.TryGetValue(caminho, out var ultimo) && (DateTime.Now - ultimo).TotalSeconds < 60)
            {
                return;
            }
            _ultimoDisparoCanary[caminho] = DateTime.Now;
            pasta = Path.GetDirectoryName(caminho);
        }

        Registrar(new EventoSeguranca
        {
            Tipo = "CANARY_TRIGGERED",
            Severidade = "CRITICAL",
            Resumo = $"Arquivo-isca {mudanca}: {caminho}",
            Detalhes = new Dictionary<string, object?>
            {
                ["caminho"] = caminho,
                ["mudanca"] = mudanca,
                ["pasta"] = pasta
            }
        });

        // Rearma a isca depois de um tempo -- se a atividade continuar,
        // ela é tocada de novo e gera outro evento.
        _ = Task.Delay(TimeSpan.FromSeconds(30)).ContinueWith(_ =>
        {
            lock (_trava)
            {
                if (_watchersCanary.Count == 0) return;
                try
                {
                    if (File.Exists(caminho))
                    {
                        _ignorarCanaryAte = DateTime.Now.AddSeconds(3);
                        File.SetAttributes(caminho, FileAttributes.Normal);
                        File.Delete(caminho);
                    }
                    PlantarCanary(caminho);
                }
                catch
                {
                    // sem permissão agora -- tenta de novo quando o módulo for religado
                }
            }
        });
    }

    private static string? HashArquivo(string caminho)
    {
        try
        {
            using var fluxo = new FileStream(caminho, FileMode.Open, FileAccess.Read, FileShare.ReadWrite | FileShare.Delete);
            return Convert.ToHexString(SHA256.HashData(fluxo));
        }
        catch
        {
            return null;
        }
    }

    // ------------------------------------------------------------------
    // 3. Mudança em massa
    // ------------------------------------------------------------------

    private void LigarFim()
    {
        foreach (var pasta in PastasUsuario(incluirImagens: true))
        {
            try
            {
                var watcher = new FileSystemWatcher(pasta)
                {
                    IncludeSubdirectories = true,
                    InternalBufferSize = 64 * 1024,
                    NotifyFilter = NotifyFilters.FileName | NotifyFilters.LastWrite | NotifyFilters.Size
                };
                watcher.Created += (s, e) => RegistrarFim(e.FullPath, 'C', null, null);
                watcher.Changed += (s, e) => RegistrarFim(e.FullPath, 'M', null, null);
                watcher.Deleted += (s, e) => RegistrarFim(e.FullPath, 'D', null, null);
                watcher.Renamed += (s, e) => RegistrarFim(e.FullPath, 'R', Path.GetExtension(e.OldFullPath), Path.GetExtension(e.FullPath));
                watcher.Error += (s, e) =>
                {
                    lock (_trava) { _fimEstouro = true; }
                };
                watcher.EnableRaisingEvents = true;
                _watchersFim.Add(watcher);
            }
            catch (Exception ex)
            {
                LogAtividade.Registrar(NivelAtividade.Aviso, "SEGURANÇA", $"Não consegui monitorar {pasta}: {ex.Message}");
            }
        }
    }

    private void DesligarFim()
    {
        foreach (var w in _watchersFim)
        {
            w.EnableRaisingEvents = false;
            w.Dispose();
        }
        _watchersFim.Clear();
        _janelaFim.Clear();
        _criadosRecentemente.Clear();
        _notasVistas.Clear();
    }

    /// <summary>
    /// Tipos guardados na janela: M = arquivo existente alterado, D =
    /// excluído, R = renomeado pra extensão incomum, K = renomeado pra
    /// extensão conhecida de ransomware (lista pública, se ligada).
    /// </summary>
    private void RegistrarFim(string caminho, char tipo, string? extAntiga, string? extNova)
    {
        if (DeveIgnorarFim(caminho))
        {
            return;
        }

        lock (_trava)
        {
            var agora = DateTime.Now;

            if (tipo == 'C')
            {
                _criadosRecentemente[caminho] = agora;
                RegistrarNotaDeResgate(caminho, agora);
                return;
            }

            // Arquivo acabou de ser criado (cópia, download, "salvar como"):
            // o "Changed" que vem logo depois é a própria escrita dele.
            if (tipo == 'M' && _criadosRecentemente.TryGetValue(caminho, out var criadoEm) && (agora - criadoEm).TotalSeconds < 120)
            {
                return;
            }

            string? extSuspeita = null;
            if (tipo == 'R')
            {
                var novaNormalizada = (extNova ?? "").ToLowerInvariant();
                if (string.Equals(extAntiga, extNova, StringComparison.OrdinalIgnoreCase))
                {
                    return; // renomear mantendo a extensão (organizar arquivos) não conta
                }

                if (_assinaturasExtensoes.Contains(novaNormalizada))
                {
                    tipo = 'K';
                }
                else if (novaNormalizada == "" || ExtensoesComuns.Contains(novaNormalizada))
                {
                    // Office salva em .tmp e renomeia pra .docx; navegador
                    // termina download de .crdownload pra .pdf -- extensão
                    // de destino comum não é sinal de criptografia.
                    return;
                }

                extSuspeita = novaNormalizada;
                RegistrarNotaDeResgate(caminho, agora);
            }

            _janelaFim.AddLast((agora, caminho, tipo, extSuspeita));
        }
    }

    /// <summary>A mesma nota de resgate (lista pública) aparecendo em 2+ pastas é crítico na hora.</summary>
    private void RegistrarNotaDeResgate(string caminho, DateTime agora)
    {
        if (_assinaturasNotas.Count == 0)
        {
            return;
        }

        var nome = Path.GetFileName(caminho).ToLowerInvariant();
        if (!_assinaturasNotas.Contains(nome))
        {
            return;
        }

        if (!_notasVistas.TryGetValue(nome, out var pastas))
        {
            pastas = new Dictionary<string, DateTime>(StringComparer.OrdinalIgnoreCase);
            _notasVistas[nome] = pastas;
        }
        pastas[Path.GetDirectoryName(caminho) ?? "?"] = agora;
    }

    private bool DeveIgnorarFim(string caminho)
    {
        var nome = Path.GetFileName(caminho);
        if (nome.StartsWith("~$") || nome.StartsWith(".~") || nome.StartsWith("~WRL", StringComparison.OrdinalIgnoreCase)
            || nome.Equals("desktop.ini", StringComparison.OrdinalIgnoreCase)
            || nome.Equals("Thumbs.db", StringComparison.OrdinalIgnoreCase)
            || nome.Equals(".DS_Store", StringComparison.OrdinalIgnoreCase)
            || nome.Contains("_RD_NAO_ALTERAR", StringComparison.OrdinalIgnoreCase))
        {
            return true;
        }

        var ext = Path.GetExtension(caminho);
        if (ExtensoesIgnoradasFim.Any(x => ext.Equals(x, StringComparison.OrdinalIgnoreCase)))
        {
            return true;
        }

        if (TrechosIgnoradosFim.Any(t => caminho.Contains(t, StringComparison.OrdinalIgnoreCase)))
        {
            return true;
        }

        // Exceções da Central de Segurança (servidor).
        var modulo = _atual;
        if (modulo != null)
        {
            if (modulo.FimExtensoesIgnoradas.Any(x => ext.Equals(x, StringComparison.OrdinalIgnoreCase)))
            {
                return true;
            }
            if (modulo.FimPastasIgnoradas.Any(t => t.Length > 2 && caminho.Contains(t, StringComparison.OrdinalIgnoreCase)))
            {
                return true;
            }
        }

        return false;
    }

    private void AvaliarFim()
    {
        List<string> amostrar;
        int janela, critico, aviso, total, alterados, suspeitas, conhecidas, excluidos;
        List<string> extensoesNovas, exemplos;
        string pastaPrincipal;
        bool estouro;
        (string nome, int pastas)? nota = null;

        lock (_trava)
        {
            if (_atual?.Fim != true)
            {
                return;
            }

            var agora = DateTime.Now;
            janela = Math.Max(5, _atual.FimJanelaSegundos);
            var corte = agora.AddSeconds(-janela);

            while (_janelaFim.First != null && _janelaFim.First.Value.quando < corte)
            {
                _janelaFim.RemoveFirst();
            }

            foreach (var chave in _criadosRecentemente.Where(k => (agora - k.Value).TotalSeconds > 120).Select(k => k.Key).ToList())
            {
                _criadosRecentemente.Remove(chave);
            }

            // Notas de resgate: esquece pastas vistas há mais de 10 minutos.
            foreach (var (nome, pastas) in _notasVistas.ToList())
            {
                foreach (var pasta in pastas.Where(p => (agora - p.Value).TotalMinutes > 10).Select(p => p.Key).ToList())
                {
                    pastas.Remove(pasta);
                }
                if (pastas.Count == 0) _notasVistas.Remove(nome);
                else if (pastas.Count >= 2 && nota == null) nota = (nome, pastas.Count);
            }

            if (agora < _fimSilenciadoAte)
            {
                _fimEstouro = false;
                return;
            }

            // Um arquivo tocado várias vezes (salvar repetido) conta uma vez só.
            var porArquivo = _janelaFim
                .GroupBy(e => e.caminho, StringComparer.OrdinalIgnoreCase)
                .Select(g => g.Last())
                .ToList();

            alterados = porArquivo.Count(e => e.tipo == 'M');
            suspeitas = porArquivo.Count(e => e.tipo == 'R');
            conhecidas = porArquivo.Count(e => e.tipo == 'K');
            excluidos = porArquivo.Count(e => e.tipo == 'D');
            total = porArquivo.Count;
            estouro = _fimEstouro;

            critico = Math.Max(5, _atual.FimLimiarCritico);
            aviso = Math.Max(1, Math.Min(critico - 1, _atual.FimLimiarAviso));

            var candidato = nota != null || conhecidas >= 5 || total >= aviso || (estouro && total > 0);
            if (!candidato)
            {
                return;
            }

            extensoesNovas = porArquivo
                .Where(e => e.extNova != null)
                .GroupBy(e => e.extNova!)
                .OrderByDescending(g => g.Count())
                .Select(g => g.Key)
                .Take(5)
                .ToList();

            pastaPrincipal = porArquivo.Count > 0
                ? porArquivo.GroupBy(e => Path.GetDirectoryName(e.caminho) ?? "?", StringComparer.OrdinalIgnoreCase)
                    .OrderByDescending(g => g.Count()).First().Key
                : "?";

            exemplos = porArquivo.Take(10).Select(e => e.caminho).ToList();
            amostrar = porArquivo.Where(e => e.tipo == 'M').Select(e => e.caminho).Take(12).ToList();

            // Não repete o mesmo surto a cada segundo -- silencia por duas
            // janelas e recomeça a contagem do zero.
            _janelaFim.Clear();
            _fimEstouro = false;
            _fimSilenciadoAte = agora.AddSeconds(janela * 2);
            if (nota != null) _notasVistas.Remove(nota.Value.nome);
        }

        // Leitura de arquivo fora da trava: conteúdo ainda válido (cabeçalho
        // certo, texto legível) = sincronização/backup/git, não criptografia.
        var (amostrados, invalidos) = (0, 0);
        if (total >= critico && alterados >= critico / 2)
        {
            foreach (var caminho in amostrar)
            {
                var invalido = ConteudoInvalido(caminho);
                if (invalido == null) continue;
                amostrados++;
                if (invalido == true) invalidos++;
            }
        }

        string? severidade = null;
        string motivo;
        if (nota != null)
        {
            severidade = "CRITICAL";
            motivo = $"nota de resgate conhecida \"{nota.Value.nome}\" criada em {nota.Value.pastas} pastas";
        }
        else if (conhecidas >= 5)
        {
            severidade = "CRITICAL";
            motivo = $"{conhecidas} arquivos renomeados pra extensão conhecida de ransomware";
        }
        else if (total >= critico && suspeitas + conhecidas >= critico / 2)
        {
            severidade = "CRITICAL";
            motivo = "maioria renomeada pra extensão incomum";
        }
        else if (total >= critico && amostrados >= 4 && invalidos * 2 >= amostrados)
        {
            severidade = "CRITICAL";
            motivo = $"{invalidos} de {amostrados} arquivos da amostra com conteúdo inválido";
        }
        else
        {
            severidade = "WARNING";
            motivo = amostrados > 0
                ? $"volume alto, mas {amostrados - invalidos} de {amostrados} arquivos da amostra continuam válidos (sincronização/backup?)"
                : "volume alto de alterações";
        }

        Registrar(new EventoSeguranca
        {
            Tipo = nota != null ? "RANSOM_NOTE_CREATED" : "MASS_FILE_CHANGE",
            Severidade = severidade,
            Resumo = nota != null
                ? $"Nota de resgate \"{nota.Value.nome}\" criada em {nota.Value.pastas} pastas"
                : $"{total} arquivos alterados em {janela}s em {pastaPrincipal} -- {motivo}",
            Detalhes = new Dictionary<string, object?>
            {
                ["eventos"] = total,
                ["janela_segundos"] = janela,
                ["pasta"] = pastaPrincipal,
                ["alterados"] = alterados,
                ["extensao_suspeita"] = suspeitas,
                ["extensao_ransomware"] = conhecidas,
                ["excluidos"] = excluidos,
                ["extensoes_novas"] = extensoesNovas,
                ["amostrados"] = amostrados,
                ["conteudo_invalido"] = invalidos,
                ["nota_resgate"] = nota?.nome,
                ["motivo"] = motivo,
                ["estouro_buffer"] = estouro,
                ["exemplos"] = exemplos
            }
        });
    }

    /// <summary>
    /// true = conteúdo não bate com o tipo do arquivo (cabeçalho trocado ou
    /// texto com cara de dado aleatório); false = válido; null = tipo que
    /// não dá pra conferir, arquivo vazio ou inacessível.
    /// </summary>
    private static bool? ConteudoInvalido(string caminho)
    {
        var ext = Path.GetExtension(caminho).ToLowerInvariant();
        byte[] inicio;
        try
        {
            using var fluxo = new FileStream(caminho, FileMode.Open, FileAccess.Read, FileShare.ReadWrite | FileShare.Delete);
            inicio = new byte[4096];
            var lidos = fluxo.Read(inicio, 0, inicio.Length);
            if (lidos < 8) return null;
            Array.Resize(ref inicio, lidos);
        }
        catch
        {
            return null;
        }

        bool Comeca(params byte[] assinatura) => inicio.Length >= assinatura.Length && inicio.Take(assinatura.Length).SequenceEqual(assinatura);

        switch (ext)
        {
            case ".docx": case ".xlsx": case ".pptx": case ".zip": case ".odt": case ".ods": case ".odp": case ".jar":
                return !Comeca(0x50, 0x4B, 0x03, 0x04) && !Comeca(0x50, 0x4B, 0x05, 0x06);
            case ".pdf":
                return Encoding.ASCII.GetString(inicio, 0, Math.Min(1024, inicio.Length)).IndexOf("%PDF", StringComparison.Ordinal) < 0;
            case ".png":
                return !Comeca(0x89, 0x50, 0x4E, 0x47);
            case ".jpg": case ".jpeg":
                return !Comeca(0xFF, 0xD8, 0xFF);
            case ".gif":
                return !Comeca(0x47, 0x49, 0x46, 0x38);
            case ".bmp":
                return !Comeca(0x42, 0x4D);
            case ".doc": case ".xls": case ".ppt": case ".msg":
                return !Comeca(0xD0, 0xCF, 0x11, 0xE0);
            case ".rtf":
                return !Comeca(0x7B, 0x5C, 0x72, 0x74, 0x66);
            case ".7z":
                return !Comeca(0x37, 0x7A, 0xBC, 0xAF);
            case ".rar":
                return !Comeca(0x52, 0x61, 0x72, 0x21);
            case ".txt": case ".csv": case ".xml": case ".html": case ".htm": case ".json": case ".sql": case ".md": case ".cs": case ".php": case ".js":
                // Texto fica bem abaixo de 6 bits/byte; dado criptografado passa de 7,5.
                return Entropia(inicio) > 7.2;
            default:
                return null;
        }
    }

    private static double Entropia(byte[] dados)
    {
        var contagem = new int[256];
        foreach (var b in dados) contagem[b]++;
        double entropia = 0;
        foreach (var c in contagem)
        {
            if (c == 0) continue;
            var p = (double)c / dados.Length;
            entropia -= p * Math.Log2(p);
        }
        return entropia;
    }

    // ------------------------------------------------------------------
    // 2. Shadow copies
    // ------------------------------------------------------------------

    private void VerificarShadowCopies()
    {
        Dictionary<string, DateTime>? anteriores;
        DateTime? verificadoAntes;
        lock (_trava)
        {
            if (_atual?.ShadowCopy != true) return;
            anteriores = _shadowIds;
            verificadoAntes = _shadowVerificadoEm;
        }

        var atuais = new Dictionary<string, DateTime>(StringComparer.OrdinalIgnoreCase);
        try
        {
            using var busca = new ManagementObjectSearcher(@"root\cimv2", "SELECT ID, InstallDate FROM Win32_ShadowCopy");
            using var resultados = busca.Get();
            foreach (ManagementObject copia in resultados)
            {
                using (copia)
                {
                    var id = copia["ID"]?.ToString();
                    if (string.IsNullOrEmpty(id)) continue;
                    var criada = DateTime.MinValue;
                    try { criada = ManagementDateTimeConverter.ToDateTime(copia["InstallDate"]?.ToString() ?? ""); } catch { }
                    atuais[id] = criada;
                }
            }
        }
        catch (Exception ex)
        {
            lock (_trava)
            {
                _shadowErro = ex is UnauthorizedAccessException or ManagementException
                    ? "Sem permissão para consultar (agente sem elevação?)"
                    : ex.Message;
                _shadowVerificadoEm = DateTime.Now;
            }
            return;
        }

        lock (_trava)
        {
            _shadowIds = atuais;
            _shadowContagem = atuais.Count;
            _shadowErro = null;
            _shadowVerificadoEm = DateTime.Now;
        }

        if (anteriores == null)
        {
            return;
        }

        var removidas = anteriores.Where(a => !atuais.ContainsKey(a.Key)).Select(a => a.Value).ToList();
        if (removidas.Count == 0)
        {
            return;
        }

        // Explicações legítimas, levantadas em documentação da Microsoft,
        // de fabricantes e nos incidentes do Maurílio (26 e 27/09):
        //  - temporárias: backup e ferramentas de fabricante (Dell
        //    SupportAssist) criam e apagam a própria cópia em minutos;
        //  - expiradas: o Windows 11 24H2 apaga pontos com mais de 60 dias;
        //  - Volsnap 33: o Windows apagou a mais antiga por limite de espaço;
        //    Volsnap 25/32/35/36: descartou todas por falta de espaço/volume.
        var agora = DateTime.Now;
        var temporarias = removidas.Count(c => c != DateTime.MinValue && (agora - c).TotalHours < 2);
        var expiradas = removidas.Count(c => c != DateTime.MinValue && (agora - c).TotalDays > 55);
        var persistentes = removidas.Count - temporarias - expiradas;
        var (apagadasPeloWindows, windowsDescartouTodas) = ExclusoesFeitasPeloWindows(verificadoAntes);
        var semExplicacao = windowsDescartouTodas ? 0 : Math.Max(0, persistentes - apagadasPeloWindows);

        if (semExplicacao < 2)
        {
            LogAtividade.Registrar(NivelAtividade.Info, "SEGURANÇA",
                $"{removidas.Count} shadow copies saíram ({anteriores.Count} → {atuais.Count}) -- explicadas: {temporarias} temporárias, {expiradas} expiradas, {apagadasPeloWindows} pelo Windows{(windowsDescartouTodas ? " (descarte total por espaço)" : "")}.");
            return;
        }

        Registrar(new EventoSeguranca
        {
            Tipo = "SHADOW_COPY_DELETE_ATTEMPT",
            Severidade = "CRITICAL",
            Resumo = $"{removidas.Count} shadow copies removidas de uma vez ({anteriores.Count} → {atuais.Count}), {semExplicacao} sem explicação",
            Detalhes = new Dictionary<string, object?>
            {
                ["linha_comando"] = $"Contagem de shadow copies caiu de {anteriores.Count} para {atuais.Count} em menos de 1 minuto",
                ["contagem_anterior"] = anteriores.Count,
                ["contagem_atual"] = atuais.Count,
                ["temporarias"] = temporarias,
                ["expiradas"] = expiradas,
                ["apagadas_pelo_windows"] = apagadasPeloWindows,
                ["sem_explicacao"] = semExplicacao
            }
        });
    }

    /// <summary>
    /// Conta, no log Sistema, as exclusões de shadow copy que o próprio
    /// Windows fez desde a leitura anterior (com folga). Sem acesso ao log,
    /// devolve zero -- vale só a análise por idade das cópias.
    /// </summary>
    private static (int umaAUma, bool todas) ExclusoesFeitasPeloWindows(DateTime? desde)
    {
        try
        {
            var janelaMs = (long)Math.Clamp(((DateTime.Now - (desde ?? DateTime.Now.AddMinutes(-2))).TotalMilliseconds) + 30000, 60000, 600000);
            var consulta = new System.Diagnostics.Eventing.Reader.EventLogQuery(
                "System",
                System.Diagnostics.Eventing.Reader.PathType.LogName,
                $"*[System[(EventID=25 or EventID=32 or EventID=33 or EventID=35 or EventID=36) and TimeCreated[timediff(@SystemTime) <= {janelaMs}]]]");

            var umaAUma = 0;
            var todas = false;
            using var leitor = new System.Diagnostics.Eventing.Reader.EventLogReader(consulta);
            for (var registro = leitor.ReadEvent(); registro != null; registro = leitor.ReadEvent())
            {
                using (registro)
                {
                    if (!string.Equals(registro.ProviderName, "volsnap", StringComparison.OrdinalIgnoreCase))
                    {
                        continue;
                    }

                    if (registro.Id == 33) umaAUma++;
                    else todas = true;
                }
            }

            return (umaAUma, todas);
        }
        catch
        {
            return (0, false);
        }
    }

    private async Task BaixarAssinaturasAsync()
    {
        try
        {
            var lista = await new SegurancaEventoClient(_config()).BaixarAssinaturasAsync();
            if (lista is not { } l)
            {
                return; // tenta de novo no próximo checkin
            }

            lock (_trava)
            {
                _assinaturasExtensoes = new HashSet<string>(l.extensoes.Select(e => e.StartsWith('.') ? e : "." + e), StringComparer.OrdinalIgnoreCase);
                _assinaturasNotas = new HashSet<string>(l.notas, StringComparer.OrdinalIgnoreCase);
                _assinaturasVersao = l.versao;
            }
            LogAtividade.Registrar(NivelAtividade.Seguranca, "SEGURANÇA",
                $"Lista pública de ransomware carregada: {l.extensoes.Count} extensões, {l.notas.Count} notas de resgate.");
        }
        finally
        {
            lock (_trava) { _baixandoAssinaturas = false; }
        }
    }

    // ------------------------------------------------------------------
    // Registro e envio
    // ------------------------------------------------------------------

    private void Registrar(EventoSeguranca evento)
    {
        lock (_trava)
        {
            _eventos.Add(evento);
            while (_eventos.Count > 100 && _eventos.FindIndex(e => e.Enviado) is var i and >= 0)
            {
                _eventos.RemoveAt(i);
            }
        }

        LogAtividade.Registrar(
            evento.Severidade == "CRITICAL" ? NivelAtividade.Erro : NivelAtividade.Aviso,
            "SEGURANÇA",
            $"[{evento.Severidade}] {evento.Resumo}");

        try
        {
            Detectado?.Invoke(evento);
        }
        catch
        {
            // UI com problema não pode impedir o envio
        }

        _ = EnviarPendentesAsync();
    }

    private async Task EnviarPendentesAsync()
    {
        List<EventoSeguranca> pendentes;
        lock (_trava)
        {
            if (_enviando) return;
            pendentes = _eventos.Where(e => !e.Enviado).OrderBy(e => e.OcorridoEm).ToList();
            if (pendentes.Count == 0) return;
            _enviando = true;
        }

        try
        {
            var guid = _machineGuid();
            if (guid == null) return;

            var cliente = new SegurancaEventoClient(_config());
            foreach (var evento in pendentes)
            {
                if (!await cliente.EnviarAsync(guid, evento))
                {
                    break; // servidor fora -- tenta de novo no próximo ciclo
                }

                lock (_trava) { evento.Enviado = true; }
                LogAtividade.Registrar(NivelAtividade.Sucesso, "SEGURANÇA", $"Evento enviado ao servidor: {evento.Tipo}");
            }
        }
        finally
        {
            lock (_trava) { _enviando = false; }
        }
    }

    private void Tick()
    {
        try
        {
            AvaliarFim();

            _tick++;
            if (_tick >= 60)
            {
                _tick = 0;
                VerificarShadowCopies();
            }

            if (_tick % 30 == 0)
            {
                _ = EnviarPendentesAsync();
            }
        }
        catch
        {
            // o timer nunca pode morrer por causa de um tick ruim
        }
    }

    public void Dispose()
    {
        _timerAvaliacao.Dispose();
        lock (_trava)
        {
            // Não apaga as iscas ao sair (o agente reabre e reaproveita);
            // só para de observar.
            foreach (var w in _watchersCanary.Concat(_watchersFim))
            {
                w.EnableRaisingEvents = false;
                w.Dispose();
            }
            _watchersCanary.Clear();
            _watchersFim.Clear();
        }
    }
}
