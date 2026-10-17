using System.Diagnostics;
using System.Diagnostics.Eventing.Reader;
using System.Net.Http.Headers;
using System.Text;
using System.Text.Json;
using System.Xml.Linq;

namespace RdIntranetAgente;

/// <summary>
/// Trilha de acesso (Segurança > Trilha de acesso no portal): lê do log de
/// segurança do Windows quem entrou na máquina e quando -- login, logoff,
/// bloqueio/desbloqueio de tela, senha errada e acesso negado -- e manda
/// pro servidor a cada 5 minutos (POST /api/ativos/acessos), continuando
/// do último RecordId que o servidor já tem. O log do Windows sobrescreve
/// em semanas; o portal guarda por anos, pra evidência de auditoria.
///
/// Na primeira vez também garante o que o Windows não faz por padrão:
/// auditoria de "Outros eventos de logon/logoff" (bloqueio e desbloqueio
/// de tela) e um log de segurança de 256 MB. Precisa do agente elevado
/// (o log de segurança só é legível por administrador).
/// </summary>
public sealed class AcessosService : IDisposable
{
    private const int MaxPorEnvio = 500;
    private const long TamanhoMinimoLog = 256L * 1024 * 1024;

    /// <summary>Interativo, desbloqueio, Área de Trabalho Remota, interativo com credencial guardada.</summary>
    private static readonly HashSet<int> TiposInterativos = new() { 2, 7, 10, 11 };

    /// <summary>Logon (sucesso e falha), Logoff, Outros eventos de logon/logoff -- pelo GUID, que não muda com o idioma.</summary>
    private static readonly (string Guid, bool Falha)[] Subcategorias =
    {
        ("{0CCE9215-69AE-11D9-BED3-505054503030}", true),
        ("{0CCE9216-69AE-11D9-BED3-505054503030}", false),
        ("{0CCE921C-69AE-11D9-BED3-505054503030}", false),
    };

    private static readonly XNamespace Ns = "http://schemas.microsoft.com/win/2004/08/events/event";

    private readonly Func<Config> _config;
    private readonly Func<string?> _machineGuid;
    private readonly System.Threading.Timer _timer;
    private int _executando;
    private long? _ultimoLido;
    private bool _ambienteConferido;
    private bool _avisouSemPermissao;

    public AcessosService(Func<Config> config, Func<string?> machineGuid)
    {
        _config = config;
        _machineGuid = machineGuid;
        _timer = new System.Threading.Timer(_ => Ciclo(), null, TimeSpan.FromSeconds(90), TimeSpan.FromMinutes(5));
    }

    private void Ciclo()
    {
        if (Interlocked.Exchange(ref _executando, 1) == 1) return;
        try
        {
            var config = _config();
            var guid = _machineGuid();
            if (!config.EstaConfigurado || string.IsNullOrEmpty(guid)) return;

            if (!Program.EstaElevado())
            {
                if (!_avisouSemPermissao)
                {
                    _avisouSemPermissao = true;
                    LogAtividade.Registrar(NivelAtividade.Aviso, "TRILHA", "Trilha de acesso parada: o agente não está como administrador e não consegue ler o log de segurança do Windows.");
                }
                return;
            }

            if (!_ambienteConferido)
            {
                _ambienteConferido = true;
                GarantirAuditoria();
                GarantirTamanhoDoLog();
            }

            // Primeira vez (ou depois de reiniciar): pergunta ao servidor de onde continuar.
            _ultimoLido ??= Enviar(config, guid, Array.Empty<object>());
            if (_ultimoLido == null) return;

            // Log zerado (Windows reinstalado, log limpo): o servidor está à frente do log -- recomeça.
            var maisNovo = RecordMaisNovo();
            if (maisNovo != null && _ultimoLido > maisNovo) _ultimoLido = 0;

            var total = 0;
            while (true)
            {
                var (eventos, ultimoLido) = Ler(_ultimoLido.Value);
                if (ultimoLido == _ultimoLido.Value) break;

                if (eventos.Count > 0 && Enviar(config, guid, eventos) == null) break;

                _ultimoLido = ultimoLido;
                total += eventos.Count;
                if (eventos.Count < MaxPorEnvio) break;
            }

            if (total > 0)
            {
                LogAtividade.Registrar(NivelAtividade.Info, "TRILHA", $"Trilha de acesso: {total} evento(s) de login/logoff enviados ao servidor.");
            }
        }
        catch (Exception ex)
        {
            LogAtividade.Registrar(NivelAtividade.Erro, "TRILHA", $"Falha na trilha de acesso: {ex.Message}");
        }
        finally
        {
            Interlocked.Exchange(ref _executando, 0);
        }
    }

    /// <summary>Lê do log de segurança, a partir de $depoisDe, até MaxPorEnvio eventos que interessam. Devolve também o último RecordId percorrido.</summary>
    private static (List<object> Eventos, long UltimoLido) Ler(long depoisDe)
    {
        var xpath = "*[System[(EventID=4624 or EventID=4625 or EventID=4634 or EventID=4647 or EventID=4800 or EventID=4801) and EventRecordID > " + depoisDe + "]]";
        var eventos = new List<object>();
        var ultimo = depoisDe;

        using var leitor = new EventLogReader(new EventLogQuery("Security", PathType.LogName, xpath));
        for (var registro = leitor.ReadEvent(); registro != null; registro = leitor.ReadEvent())
        {
            using (registro)
            {
                ultimo = registro.RecordId ?? ultimo;
                if (Interpretar(registro) is { } evento) eventos.Add(evento);
            }
            if (eventos.Count >= MaxPorEnvio) break;
        }

        return (eventos, ultimo);
    }

    /// <summary>Modo --teste-trilha: os eventos que iriam pro servidor, do começo do log (até MaxPorEnvio).</summary>
    internal static List<object> LerParaTeste() => Ler(0).Eventos;

    private static object? Interpretar(EventRecord registro)
    {
        var dados = XDocument.Parse(registro.ToXml()).Descendants(Ns + "Data")
            .Where(d => d.Attribute("Name") != null)
            .ToDictionary(d => d.Attribute("Name")!.Value, d => d.Value);
        string D(string nome) => dados.TryGetValue(nome, out var v) ? v : "";

        var id = registro.Id;
        var sid = D("TargetUserSid");
        int? tipo = int.TryParse(D("LogonType"), out var t) ? t : null;

        if (ContaDoSistema(sid)) return null;

        switch (id)
        {
            case 4624:
                if (tipo == null || !TiposInterativos.Contains(tipo.Value)) return null;
                // Administrador ganha duas sessões no login (normal + elevada, ligadas): fica só a normal.
                if (D("TargetLinkedLogonId") is var ligada && ligada != "" && ligada != "0x0" && D("ElevatedToken") == "%%1842") return null;
                break;
            case 4625:
                if (tipo == null || !TiposInterativos.Contains(tipo.Value)) return null;
                break;
            case 4634:
                if (tipo is not (2 or 10 or 11)) return null;
                break;
        }

        return new
        {
            record_id = registro.RecordId,
            event_id = id,
            quando = registro.TimeCreated?.ToUniversalTime().ToString("o"),
            usuario = D("TargetUserName"),
            dominio = D("TargetDomainName"),
            sid,
            logon_id = D("TargetLogonId"),
            tipo_logon = tipo,
            ip = D("IpAddress"),
            status = D("Status"),
            substatus = D("SubStatus"),
        };
    }

    /// <summary>SYSTEM, serviço local/rede, Gerenciador de Janelas (DWM) e driver de fontes (UMFD) -- logins da própria máquina, não de pessoas.</summary>
    private static bool ContaDoSistema(string sid) =>
        sid is "S-1-5-18" or "S-1-5-19" or "S-1-5-20"
        || sid.StartsWith("S-1-5-90-") || sid.StartsWith("S-1-5-96-");

    private static long? RecordMaisNovo()
    {
        using var leitor = new EventLogReader(new EventLogQuery("Security", PathType.LogName, "*") { ReverseDirection = true });
        using var registro = leitor.ReadEvent();
        return registro?.RecordId;
    }

    /// <summary>POST /api/ativos/acessos -- devolve o último RecordId que o servidor guardou (null em falha).</summary>
    private static long? Enviar(Config config, string guid, IReadOnlyCollection<object> eventos)
    {
        var json = JsonSerializer.Serialize(new { machine_guid = guid, eventos });
        var handler = new HttpClientHandler { ServerCertificateCustomValidationCallback = (msg, cert, chain, erros) => true };
        using var cliente = new HttpClient(handler) { Timeout = TimeSpan.FromSeconds(60) };
        cliente.DefaultRequestHeaders.Add("X-RD-Agente-Chave", config.ApiKey);

        var conteudo = new ByteArrayContent(Encoding.UTF8.GetBytes(json));
        conteudo.Headers.ContentType = new MediaTypeHeaderValue("application/json") { CharSet = "utf-8" };

        try
        {
            using var resposta = cliente.PostAsync(config.ServerUrl.TrimEnd('/') + "/api/ativos/acessos", conteudo).GetAwaiter().GetResult();
            if (!resposta.IsSuccessStatusCode) return null;

            using var doc = JsonDocument.Parse(resposta.Content.ReadAsStringAsync().GetAwaiter().GetResult());
            return doc.RootElement.TryGetProperty("ultimo_record_id", out var ultimo) ? ultimo.GetInt64() : null;
        }
        catch
        {
            return null;
        }
    }

    /// <summary>Liga as subcategorias de auditoria que faltam (auditpol /r: 0 nada, 1 sucesso, 2 falha, 3 os dois).</summary>
    private static void GarantirAuditoria()
    {
        foreach (var (guid, falha) in Subcategorias)
        {
            try
            {
                var csv = Rodar("auditpol.exe", $"/get /subcategory:{guid} /r");
                var valor = csv.Split('\n').Skip(1).Select(l => l.Trim().Split(',')).Where(c => c.Length >= 7).Select(c => c[^1]).FirstOrDefault() ?? "";
                var atual = int.TryParse(valor, out var v) ? v : 0;
                var quer = falha ? 3 : 1;
                if ((atual & quer) == quer) continue;

                Rodar("auditpol.exe", $"/set /subcategory:{guid} /success:enable" + (falha ? " /failure:enable" : ""));
                LogAtividade.Registrar(NivelAtividade.Seguranca, "TRILHA", $"Auditoria do Windows ligada para a trilha de acesso ({guid}).");
            }
            catch (Exception ex)
            {
                LogAtividade.Registrar(NivelAtividade.Aviso, "TRILHA", $"Não consegui ajustar a auditoria do Windows ({guid}): {ex.Message}");
            }
        }
    }

    private static void GarantirTamanhoDoLog()
    {
        try
        {
            using var log = new EventLogConfiguration("Security");
            if (log.MaximumSizeInBytes >= TamanhoMinimoLog) return;

            log.MaximumSizeInBytes = TamanhoMinimoLog;
            log.SaveChanges();
            LogAtividade.Registrar(NivelAtividade.Seguranca, "TRILHA", "Log de segurança do Windows aumentado para 256 MB (guarda mais histórico de acesso).");
        }
        catch (Exception ex)
        {
            LogAtividade.Registrar(NivelAtividade.Aviso, "TRILHA", $"Não consegui aumentar o log de segurança: {ex.Message}");
        }
    }

    private static string Rodar(string exe, string argumentos)
    {
        using var p = Process.Start(new ProcessStartInfo(exe, argumentos)
        {
            UseShellExecute = false,
            CreateNoWindow = true,
            RedirectStandardOutput = true,
            RedirectStandardError = true,
        })!;
        var saida = p.StandardOutput.ReadToEnd();
        p.WaitForExit(15000);
        return saida;
    }

    public void Dispose() => _timer.Dispose();
}
