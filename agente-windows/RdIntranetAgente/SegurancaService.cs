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
    private static readonly string[] NomesCanary =
    {
        "!!!_RD_NAO_ALTERAR.docx",
        "zzz_RD_NAO_ALTERAR.xlsx"
    };

    private static readonly string[] ExtensoesIgnoradasFim =
    {
        ".tmp", ".temp", ".crdownload", ".part", ".partial", ".download", ".lnk", ".ini", ".db", ".lock", ".log", ".etl"
    };

    private static readonly string[] TrechosIgnoradosFim =
    {
        @"\.git\", @"\node_modules\", @"\.vs\", @"\AppData\", @"\$RECYCLE.BIN\"
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

    // Shadow copies
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
                watcher.Created += (s, e) => RegistrarFim(e.FullPath, 'C', null);
                watcher.Changed += (s, e) => RegistrarFim(e.FullPath, 'M', null);
                watcher.Deleted += (s, e) => RegistrarFim(e.FullPath, 'D', null);
                watcher.Renamed += (s, e) =>
                {
                    var extAntiga = Path.GetExtension(e.OldFullPath);
                    var extNova = Path.GetExtension(e.FullPath);
                    RegistrarFim(e.FullPath, 'R', string.Equals(extAntiga, extNova, StringComparison.OrdinalIgnoreCase) ? null : extNova);
                };
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
    }

    private void RegistrarFim(string caminho, char tipo, string? extNova)
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
                return;
            }

            // Arquivo acabou de ser criado (cópia, download, "salvar como"):
            // o "Changed" que vem logo depois é a própria escrita dele.
            if (tipo == 'M' && _criadosRecentemente.TryGetValue(caminho, out var criadoEm) && (agora - criadoEm).TotalSeconds < 120)
            {
                return;
            }

            // Renomear sem trocar extensão (organizar arquivos) não conta.
            if (tipo == 'R' && extNova == null)
            {
                return;
            }

            _janelaFim.AddLast((agora, caminho, tipo, extNova));
        }
    }

    private static bool DeveIgnorarFim(string caminho)
    {
        var nome = Path.GetFileName(caminho);
        if (nome.StartsWith("~$") || nome.StartsWith(".~")
            || nome.Equals("desktop.ini", StringComparison.OrdinalIgnoreCase)
            || nome.Equals("Thumbs.db", StringComparison.OrdinalIgnoreCase))
        {
            return true;
        }

        if (NomesCanary.Any(n => nome.Equals(n, StringComparison.OrdinalIgnoreCase)))
        {
            return true;
        }

        var ext = Path.GetExtension(caminho);
        if (ExtensoesIgnoradasFim.Any(x => ext.Equals(x, StringComparison.OrdinalIgnoreCase)))
        {
            return true;
        }

        return TrechosIgnoradosFim.Any(t => caminho.Contains(t, StringComparison.OrdinalIgnoreCase));
    }

    private void AvaliarFim()
    {
        EventoSeguranca evento;

        lock (_trava)
        {
            if (_atual?.Fim != true)
            {
                return;
            }

            var agora = DateTime.Now;
            var janela = Math.Max(5, _atual.FimJanelaSegundos);
            var corte = agora.AddSeconds(-janela);

            while (_janelaFim.First != null && _janelaFim.First.Value.quando < corte)
            {
                _janelaFim.RemoveFirst();
            }

            foreach (var chave in _criadosRecentemente.Where(k => (agora - k.Value).TotalSeconds > 120).Select(k => k.Key).ToList())
            {
                _criadosRecentemente.Remove(chave);
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

            var alterados = porArquivo.Count(e => e.tipo == 'M');
            var extensaoTrocada = porArquivo.Count(e => e.tipo == 'R');
            var excluidos = porArquivo.Count(e => e.tipo == 'D');
            var total = porArquivo.Count;

            var critico = Math.Max(5, _atual.FimLimiarCritico);
            var aviso = Math.Max(1, Math.Min(critico - 1, _atual.FimLimiarAviso));

            string? severidade = null;
            if (total >= critico && alterados + extensaoTrocada >= critico / 2)
            {
                severidade = "CRITICAL";
            }
            else if (total >= aviso || (_fimEstouro && total > 0))
            {
                severidade = "WARNING";
            }

            if (severidade == null)
            {
                return;
            }

            var extensoesNovas = porArquivo
                .Where(e => e.extNova != null)
                .GroupBy(e => e.extNova!.ToLowerInvariant())
                .OrderByDescending(g => g.Count())
                .Select(g => g.Key)
                .Take(5)
                .ToList();

            var pastaPrincipal = porArquivo
                .GroupBy(e => Path.GetDirectoryName(e.caminho) ?? "?", StringComparer.OrdinalIgnoreCase)
                .OrderByDescending(g => g.Count())
                .First().Key;

            evento = new EventoSeguranca
            {
                Tipo = "MASS_FILE_CHANGE",
                Severidade = severidade,
                Resumo = $"{total} arquivos alterados em {janela}s em {pastaPrincipal}",
                Detalhes = new Dictionary<string, object?>
                {
                    ["eventos"] = total,
                    ["janela_segundos"] = janela,
                    ["pasta"] = pastaPrincipal,
                    ["alterados"] = alterados,
                    ["extensao_trocada"] = extensaoTrocada,
                    ["excluidos"] = excluidos,
                    ["extensoes_novas"] = extensoesNovas,
                    ["estouro_buffer"] = _fimEstouro,
                    ["exemplos"] = porArquivo.Take(10).Select(e => e.caminho).ToList()
                }
            };

            // Não repete o mesmo surto a cada segundo -- silencia por duas
            // janelas e recomeça a contagem do zero.
            _janelaFim.Clear();
            _fimEstouro = false;
            _fimSilenciadoAte = agora.AddSeconds(janela * 2);
        }

        Registrar(evento);
    }

    // ------------------------------------------------------------------
    // 2. Shadow copies
    // ------------------------------------------------------------------

    private void VerificarShadowCopies()
    {
        int? anterior;
        lock (_trava)
        {
            if (_atual?.ShadowCopy != true) return;
            anterior = _shadowContagem;
        }

        int contagem;
        try
        {
            using var busca = new ManagementObjectSearcher(@"root\cimv2", "SELECT ID FROM Win32_ShadowCopy");
            using var resultados = busca.Get();
            contagem = resultados.Count;
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
            _shadowContagem = contagem;
            _shadowErro = null;
            _shadowVerificadoEm = DateTime.Now;
        }

        if (anterior is not int antes || contagem >= antes)
        {
            return;
        }

        // Sumir UMA só é rotina, mesmo quando era a única (1 → 0): o
        // Windows descarta a mais antiga quando o espaço enche, e
        // ferramentas como o Dell SupportAssist criam e apagam a própria
        // cópia temporária -- foi exatamente isso que isolou uma máquina
        // à toa no Maurílio (2026-09-26). Só 2 ou mais de uma vez é crítico.
        var removidas = antes - contagem;
        if (removidas < 2)
        {
            LogAtividade.Registrar(NivelAtividade.Info, "SEGURANÇA", $"Uma shadow copy saiu ({antes} → {contagem}) -- rotina do Windows ou de ferramenta de backup.");
            return;
        }

        Registrar(new EventoSeguranca
        {
            Tipo = "SHADOW_COPY_DELETE_ATTEMPT",
            Severidade = "CRITICAL",
            Resumo = $"{removidas} shadow copies removidas de uma vez ({antes} → {contagem})",
            Detalhes = new Dictionary<string, object?>
            {
                ["linha_comando"] = $"Contagem de shadow copies caiu de {antes} para {contagem} em menos de 1 minuto",
                ["contagem_anterior"] = antes,
                ["contagem_atual"] = contagem
            }
        });
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
