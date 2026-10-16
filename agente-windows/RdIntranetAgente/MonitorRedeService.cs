using RdIntranetAgente.Models;
using System.Management;
using System.Net;
using System.Net.Sockets;
using System.Runtime.InteropServices;
using System.Text.Json;

namespace RdIntranetAgente;

/// <summary>Uma conexão TCP da tabela do Windows, com o processo dono.</summary>
public readonly record struct ConexaoTcp(int Pid, string Estado, IPAddress Local, int PortaLocal, IPAddress Remoto, int PortaRemota);

/// <summary>Processo em execução (WMI): caminho, linha de comando, pai e serviço que o hospeda.</summary>
public sealed class ProcessoInfo
{
    public int Pid { get; init; }
    public int PaiPid { get; init; }
    public string Nome { get; init; } = "";
    public string? Caminho { get; init; }
    public string? LinhaComando { get; init; }
    public DateTime? CriadoEm { get; init; }
    public string? Servico { get; set; }
    public bool ComoSystem { get; set; }
}

/// <summary>Tabela TCP (IPv4 + IPv6) com PID, direto da API -- instantâneo, ao contrário do netstat/Get-NetTCPConnection.</summary>
public static class TabelaTcp
{
    private const int AfInet = 2, AfInet6 = 23, TcpTableOwnerPidAll = 5;

    private static readonly string[] Estados =
    {
        "?", "CLOSED", "LISTEN", "SYN_SENT", "SYN_RCVD", "ESTABLISHED", "FIN_WAIT1", "FIN_WAIT2",
        "CLOSE_WAIT", "CLOSING", "LAST_ACK", "TIME_WAIT", "DELETE_TCB"
    };

    [DllImport("iphlpapi.dll", SetLastError = true)]
    private static extern uint GetExtendedTcpTable(IntPtr pTcpTable, ref int pdwSize, bool bOrder, int ulAf, int tableClass, uint reserved);

    public static List<ConexaoTcp> Ler()
    {
        var lista = new List<ConexaoTcp>();
        Ler(AfInet, lista);
        Ler(AfInet6, lista);
        return lista;
    }

    private static void Ler(int familia, List<ConexaoTcp> lista)
    {
        var tamanho = 0;
        GetExtendedTcpTable(IntPtr.Zero, ref tamanho, false, familia, TcpTableOwnerPidAll, 0);
        for (var tentativa = 0; tentativa < 3 && tamanho > 0; tentativa++)
        {
            tamanho += 64 * 1024; // a tabela pode crescer entre as duas chamadas
            var buffer = Marshal.AllocHGlobal(tamanho);
            try
            {
                if (GetExtendedTcpTable(buffer, ref tamanho, false, familia, TcpTableOwnerPidAll, 0) != 0) continue;

                var quantidade = Marshal.ReadInt32(buffer);
                var linha = buffer + 4;
                for (var i = 0; i < quantidade; i++)
                {
                    if (familia == AfInet)
                    {
                        // MIB_TCPROW_OWNER_PID: state, localAddr, localPort, remoteAddr, remotePort, pid (24 bytes)
                        var estado = Marshal.ReadInt32(linha);
                        var local = new IPAddress((uint)Marshal.ReadInt32(linha, 4));
                        var portaLocal = Porta(linha, 8);
                        var remoto = new IPAddress((uint)Marshal.ReadInt32(linha, 12));
                        var portaRemota = Porta(linha, 16);
                        var pid = Marshal.ReadInt32(linha, 20);
                        lista.Add(new ConexaoTcp(pid, NomeEstado(estado), local, portaLocal, remoto, portaRemota));
                        linha += 24;
                    }
                    else
                    {
                        // MIB_TCP6ROW_OWNER_PID: localAddr[16], scope, localPort, remoteAddr[16], scope, remotePort, state, pid (56 bytes)
                        var local = new IPAddress(Bytes(linha, 0, 16));
                        var portaLocal = Porta(linha, 20);
                        var remoto = new IPAddress(Bytes(linha, 24, 16));
                        var portaRemota = Porta(linha, 44);
                        var estado = Marshal.ReadInt32(linha, 48);
                        var pid = Marshal.ReadInt32(linha, 52);
                        lista.Add(new ConexaoTcp(pid, NomeEstado(estado), local, portaLocal, remoto, portaRemota));
                        linha += 56;
                    }
                }
                return;
            }
            finally
            {
                Marshal.FreeHGlobal(buffer);
            }
        }
    }

    private static int Porta(IntPtr linha, int deslocamento) => (Marshal.ReadByte(linha, deslocamento) << 8) | Marshal.ReadByte(linha, deslocamento + 1);

    private static byte[] Bytes(IntPtr linha, int deslocamento, int tamanho)
    {
        var bytes = new byte[tamanho];
        Marshal.Copy(linha + deslocamento, bytes, 0, tamanho);
        return bytes;
    }

    private static string NomeEstado(int estado) => estado >= 0 && estado < Estados.Length ? Estados[estado] : "?";

    /// <summary>Endereço da internet (não loopback, não rede privada/link-local/CGNAT/ULA).</summary>
    public static bool EhPublico(IPAddress ip)
    {
        if (ip.IsIPv4MappedToIPv6) ip = ip.MapToIPv4();
        if (IPAddress.IsLoopback(ip) || ip.Equals(IPAddress.Any) || ip.Equals(IPAddress.IPv6Any)) return false;
        if (ip.AddressFamily == AddressFamily.InterNetwork)
        {
            var b = ip.GetAddressBytes();
            return !(b[0] == 10 || (b[0] == 172 && b[1] >= 16 && b[1] <= 31) || (b[0] == 192 && b[1] == 168)
                     || (b[0] == 169 && b[1] == 254) || (b[0] == 100 && b[1] >= 64 && b[1] <= 127) || b[0] == 0 || b[0] >= 224);
        }
        return !(ip.IsIPv6LinkLocal || ip.IsIPv6SiteLocal || ip.IsIPv6Multicast || (ip.GetAddressBytes()[0] & 0xFE) == 0xFC);
    }

    public static bool EhLocal(IPAddress ip)
    {
        if (ip.IsIPv4MappedToIPv6) ip = ip.MapToIPv4();
        return IPAddress.IsLoopback(ip) || ip.Equals(IPAddress.Any) || ip.Equals(IPAddress.IPv6Any);
    }

    public static string Texto(IPAddress ip) => (ip.IsIPv4MappedToIPv6 ? ip.MapToIPv4() : ip).ToString();
}

/// <summary>Processos e serviços via WMI, com cache (consultar a cada amostra pesaria).</summary>
public static class CatalogoProcessos
{
    private static readonly object Trava = new();
    private static Dictionary<int, ProcessoInfo> _processos = new();
    private static DateTime _atualizadoEm = DateTime.MinValue;

    /// <summary>forcar = relê agora (retrato e telas: processo encerrado não pode continuar aparecendo).</summary>
    public static Dictionary<int, ProcessoInfo> Obter(IEnumerable<int> pidsNecessarios, bool forcar = false)
    {
        lock (Trava)
        {
            var faltando = pidsNecessarios.Any(p => p > 4 && !_processos.ContainsKey(p));
            var idade = DateTime.Now - _atualizadoEm;
            if (forcar || idade > TimeSpan.FromMinutes(5) || (faltando && idade > TimeSpan.FromSeconds(15)))
            {
                _processos = Consultar();
                _atualizadoEm = DateTime.Now;
            }
            return _processos;
        }
    }

    private static Dictionary<int, ProcessoInfo> Consultar()
    {
        var processos = new Dictionary<int, ProcessoInfo>();
        try
        {
            using var busca = new ManagementObjectSearcher("SELECT ProcessId, ParentProcessId, Name, ExecutablePath, CommandLine, CreationDate FROM Win32_Process");
            foreach (ManagementObject p in busca.Get())
            {
                using (p)
                {
                    var pid = Convert.ToInt32(p["ProcessId"]);
                    DateTime? criado = null;
                    try { if (p["CreationDate"] is string d) criado = ManagementDateTimeConverter.ToDateTime(d); } catch { }
                    processos[pid] = new ProcessoInfo
                    {
                        Pid = pid,
                        PaiPid = Convert.ToInt32(p["ParentProcessId"] ?? 0),
                        Nome = p["Name"] as string ?? "",
                        Caminho = p["ExecutablePath"] as string,
                        LinhaComando = p["CommandLine"] as string,
                        CriadoEm = criado
                    };
                }
            }

            using var servicos = new ManagementObjectSearcher("SELECT Name, ProcessId, StartName FROM Win32_Service WHERE ProcessId <> 0");
            foreach (ManagementObject s in servicos.Get())
            {
                using (s)
                {
                    var pid = Convert.ToInt32(s["ProcessId"]);
                    if (!processos.TryGetValue(pid, out var info)) continue;
                    var nome = s["Name"] as string ?? "";
                    info.Servico = info.Servico == null ? nome : (info.Servico.Length < 120 ? info.Servico + ", " + nome : info.Servico);
                    if (string.Equals(s["StartName"] as string, "LocalSystem", StringComparison.OrdinalIgnoreCase)) info.ComoSystem = true;
                }
            }
        }
        catch
        {
            // WMI indisponível: segue sem nomes (as conexões ainda saem com PID)
        }

        return processos;
    }
}

/// <summary>
/// Monitor de conexões, processos e inicialização (agente 1.0.56+).
/// Roda no intervalo configurado no portal (Central de Segurança, padrão
/// 30 s; pode ser sobrescrito por máquina) e:
///  - acumula as tentativas de conexão (SYN_SENT) por programa numa janela
///    de minutos e acusa VARREDURA quando um programa tenta IPs demais
///    (internet ou rede local) -- caso ARIIS: 3.205 IPs nas portas 3380-3398;
///  - acusa programa suspeito CONVERSANDO com a internet (provável servidor
///    de comando -- caso ARIIS: 77.90.185.12:54884);
///  - acusa processo crítico rodando, mesmo sem rede ("Runtime Broker.exe");
///  - manda ao portal um retrato (conexões por programa + firewall) e, de
///    tempos em tempos, o inventário do que o Windows inicia sozinho.
/// Só detecta e mostra -- bloquear/encerrar é ação explícita do portal.
/// </summary>
public sealed class MonitorRedeService : IDisposable
{
    private readonly Func<Config> _config;
    private readonly Func<string?> _machineGuid;
    private readonly SegurancaService _seguranca;
    private readonly System.Threading.Timer _timer;
    private readonly object _trava = new();

    private bool _ativo;
    private int _intervaloSegundos = 30;
    private int _executando;
    private DateTime _proximoRetrato = DateTime.MinValue;
    private DateTime _proximoInventario = DateTime.MinValue;
    private bool _forcarRetrato;
    private bool _forcarInventario;

    // pid:criação -> ip -> (porta, visto em)
    private readonly Dictionary<string, Dictionary<string, (int porta, DateTime visto)>> _tentativas = new();
    private EstadoPersistido _persistido;

    public DateTime? UltimaAmostraEm { get; private set; }
    public int UltimaQuantidadeConexoes { get; private set; }

    public MonitorRedeService(Func<Config> config, Func<string?> machineGuid, SegurancaService seguranca)
    {
        _config = config;
        _machineGuid = machineGuid;
        _seguranca = seguranca;
        _persistido = EstadoPersistido.Carregar();
        _timer = new System.Threading.Timer(_ => Ciclo(), null, Timeout.Infinite, Timeout.Infinite);
    }

    public bool Ativo => _ativo;
    public int IntervaloSegundos => _intervaloSegundos;

    /// <summary>Vem do heartbeat (bloco "monitor_rede"): liga/desliga e muda o intervalo na hora.</summary>
    public void Aplicar(bool ativo, int intervaloSegundos)
    {
        intervaloSegundos = Math.Clamp(intervaloSegundos, 5, 3600);
        lock (_trava)
        {
            if (ativo == _ativo && intervaloSegundos == _intervaloSegundos) return;

            var ligou = ativo && !_ativo;
            _ativo = ativo;
            _intervaloSegundos = intervaloSegundos;
            _timer.Change(ativo ? (ligou ? 5000 : intervaloSegundos * 1000) : Timeout.Infinite, ativo ? intervaloSegundos * 1000 : Timeout.Infinite);

            LogAtividade.Registrar(NivelAtividade.Seguranca, "SEGURANÇA", ativo
                ? $"Monitor de conexões/processos {(ligou ? "ativado" : "ajustado")} pelo servidor: verificação a cada {intervaloSegundos} s."
                : "Monitor de conexões/processos desativado pelo servidor.");
        }
    }

    /// <summary>"Atualizar agora" do portal: retrato e inventário no próximo ciclo (em segundos).</summary>
    public void ForcarAtualizacao()
    {
        _forcarRetrato = true;
        _forcarInventario = true;
        _ = Task.Run(Ciclo);
    }

    /// <summary>Só a parte de detecção (sem retrato/inventário/envio) -- usado pelo modo de teste.</summary>
    internal void CicloDeDeteccao()
    {
        var regras = _seguranca.Regras;
        var conexoes = TabelaTcp.Ler();
        var processos = CatalogoProcessos.Obter(conexoes.Select(c => c.Pid).Distinct());
        RegistrarTentativas(conexoes, processos, regras);
        DetectarVarredura(processos, regras);
        DetectarConversaSuspeita(conexoes, processos);
    }

    private void Ciclo()
    {
        if (Interlocked.Exchange(ref _executando, 1) == 1) return;
        try
        {
            if (!_ativo && !_forcarRetrato) return;

            var regras = _seguranca.Regras;
            var conexoes = TabelaTcp.Ler();
            var processos = CatalogoProcessos.Obter(conexoes.Select(c => c.Pid).Distinct());
            UltimaAmostraEm = DateTime.Now;
            UltimaQuantidadeConexoes = conexoes.Count;

            RegistrarTentativas(conexoes, processos, regras);
            DetectarVarredura(processos, regras);
            DetectarConversaSuspeita(conexoes, processos);

            if (_forcarRetrato || DateTime.Now >= _proximoRetrato)
            {
                _forcarRetrato = false;
                var retrato = MontarRetrato(conexoes, CatalogoProcessos.Obter(Array.Empty<int>(), forcar: true), regras);
                DetectarProcessosCriticos(retrato.suspeitos);
                // Falhou (servidor fora ou ainda sem a versão nova): tenta de novo em 1 min.
                _proximoRetrato = DateTime.Now + (Enviar("rede", retrato.dados) ? TimeSpan.FromMinutes(regras.RedeRetratoMinutos) : TimeSpan.FromMinutes(1));
            }

            if (_forcarInventario || DateTime.Now >= _proximoInventario)
            {
                _forcarInventario = false;
                var itens = InicializacaoService.Coletar();
                DetectarInicializacaoNova(itens);
                var enviado = Enviar("inicializacao", new { coletado_em = DateTimeOffset.Now.ToString("o"), itens = itens.Select(i => i.ParaEnvio()).ToList() });
                _proximoInventario = DateTime.Now + (enviado ? TimeSpan.FromHours(regras.InicializacaoHoras) : TimeSpan.FromMinutes(10));
            }

            _persistido.Salvar();
        }
        catch (Exception ex)
        {
            LogAtividade.Registrar(NivelAtividade.Aviso, "SEGURANÇA", $"Monitor de conexões: falha num ciclo ({ex.Message}).");
        }
        finally
        {
            Interlocked.Exchange(ref _executando, 0);
        }
    }

    // ------------------------------------------------------------------
    // Detecção
    // ------------------------------------------------------------------

    private static string Chave(int pid, Dictionary<int, ProcessoInfo> processos) =>
        $"{pid}:{(processos.TryGetValue(pid, out var p) ? p.CriadoEm?.Ticks ?? 0 : 0)}";

    private void RegistrarTentativas(List<ConexaoTcp> conexoes, Dictionary<int, ProcessoInfo> processos, RegrasSeguranca regras)
    {
        var agora = DateTime.Now;
        foreach (var c in conexoes.Where(c => c.Estado == "SYN_SENT" && !TabelaTcp.EhLocal(c.Remoto)))
        {
            var chave = Chave(c.Pid, processos);
            if (!_tentativas.TryGetValue(chave, out var ips)) _tentativas[chave] = ips = new();
            ips[TabelaTcp.Texto(c.Remoto)] = (c.PortaRemota, agora);
        }

        var corte = agora.AddMinutes(-regras.RedeJanelaMinutos);
        foreach (var chave in _tentativas.Keys.ToList())
        {
            var ips = _tentativas[chave];
            foreach (var ip in ips.Where(kv => kv.Value.visto < corte).Select(kv => kv.Key).ToList()) ips.Remove(ip);
            if (ips.Count == 0) _tentativas.Remove(chave);
        }
    }

    private void DetectarVarredura(Dictionary<int, ProcessoInfo> processos, RegrasSeguranca regras)
    {
        foreach (var (chave, ips) in _tentativas)
        {
            var pid = int.Parse(chave.Split(':')[0]);
            processos.TryGetValue(pid, out var proc);
            var internet = ips.Keys.Count(ip => IPAddress.TryParse(ip, out var a) && TabelaTcp.EhPublico(a));
            var lan = ips.Count - internet;
            var ehLan = lan >= regras.RedeVarreduraLanMinimo;
            if (internet < regras.RedeVarreduraIpsMinimo && !ehLan) continue;

            var caminho = proc?.Caminho ?? proc?.Nome ?? $"PID {pid}";
            if (regras.RedeProcessosIgnorados.Any(t => t.Length > 2 && caminho.Contains(t, StringComparison.OrdinalIgnoreCase))) continue;

            var tipo = ehLan ? "NETWORK_SCAN_LAN" : "NETWORK_SCAN";
            if (!_persistido.PodeAlertar($"{tipo}|{caminho}", TimeSpan.FromHours(1))) continue;

            var avaliacao = Avaliar(proc);
            var portas = ips.Values.GroupBy(v => v.porta).Select(g => new { porta = g.Key, qtd = g.Count() }).OrderByDescending(x => x.qtd).ToList();
            var sensiveis = ips.Values.Count(v => regras.RedePortasSensiveis.Contains(v.porta));
            var critico = avaliacao.Pontos >= 35 || sensiveis >= ips.Count / 2;

            var detalhes = DetalhesProcesso(proc, pid, avaliacao);
            detalhes["ips_distintos"] = ips.Count;
            detalhes["internet"] = internet;
            detalhes["lan"] = lan;
            detalhes["janela_minutos"] = regras.RedeJanelaMinutos;
            detalhes["portas"] = portas.Take(12).ToList();
            detalhes["portas_sensiveis"] = sensiveis;
            detalhes["faixas"] = Faixas(ips.Keys).Take(10).ToList();
            detalhes["amostra_ips"] = ips.Keys.Take(15).ToList();

            _seguranca.RegistrarExterno(new EventoSeguranca
            {
                Tipo = tipo,
                Severidade = critico ? "CRITICAL" : "WARNING",
                Resumo = $"{proc?.Nome ?? "PID " + pid} tentou conectar em {ips.Count} IPs em {regras.RedeJanelaMinutos} min ({(ehLan ? "rede local" : "internet")}), portas {string.Join(", ", portas.Take(5).Select(p => p.porta))}",
                Detalhes = detalhes
            });
        }
    }

    private void DetectarConversaSuspeita(List<ConexaoTcp> conexoes, Dictionary<int, ProcessoInfo> processos)
    {
        foreach (var grupo in conexoes.Where(c => c.Estado == "ESTABLISHED" && TabelaTcp.EhPublico(c.Remoto)).GroupBy(c => c.Pid))
        {
            if (!processos.TryGetValue(grupo.Key, out var proc)) continue;
            var avaliacao = Avaliar(proc);
            if (avaliacao.Pontos < 35) continue;

            var destinos = grupo.Select(c => $"{TabelaTcp.Texto(c.Remoto)}:{c.PortaRemota}").Distinct().ToList();
            var novos = destinos.Where(d => _persistido.PodeAlertar($"CONVERSA|{proc.Caminho ?? proc.Nome}|{d.Split(':')[0]}", TimeSpan.FromHours(6))).ToList();
            if (novos.Count == 0) continue;

            var detalhes = DetalhesProcesso(proc, proc.Pid, avaliacao);
            detalhes["destinos"] = destinos.Take(20).ToList();
            _seguranca.RegistrarExterno(new EventoSeguranca
            {
                Tipo = "SUSPICIOUS_CONNECTION",
                Severidade = avaliacao.Pontos >= 60 ? "CRITICAL" : "WARNING",
                Resumo = $"Programa suspeito {proc.Nome} conversando com {string.Join(", ", novos.Take(3))}",
                Detalhes = detalhes
            });
        }
    }

    private void DetectarProcessosCriticos(List<Dictionary<string, object?>> suspeitos)
    {
        foreach (var s in suspeitos.Where(s => (string?)s["nivel"] == "critico"))
        {
            var caminho = (string?)s["caminho"] ?? (string?)s["nome"] ?? "";
            if (!_persistido.PodeAlertar($"PROCESSO|{caminho}", TimeSpan.FromHours(24))) continue;

            _seguranca.RegistrarExterno(new EventoSeguranca
            {
                Tipo = "SUSPICIOUS_PROCESS",
                Severidade = "CRITICAL",
                Resumo = $"Processo suspeito em execução: {s["nome"]} ({string.Join("; ", ((List<string>)s["motivos"]!).Take(3))})",
                Detalhes = s
            });
        }
    }

    private void DetectarInicializacaoNova(List<ItemInicializacao> itens)
    {
        // Avisa o que é suspeito/crítico e ainda não foi avisado NESSE nível:
        // item novo, item que piorou, e tudo na primeira vez (inclusive ao
        // atualizar de uma versão que não guardava os níveis).
        var niveis = _persistido.NiveisInicializacao;
        var primeiraVez = niveis == null;
        static int Peso(string? n) => n switch { "critico" => 3, "suspeito" => 2, "atencao" => 1, _ => 0 };
        var alertar = itens
            .Where(i => Peso(i.Avaliacao.Nivel) >= 2)
            .Where(i => primeiraVez || !niveis!.TryGetValue(i.Chave, out var antes) || Peso(i.Avaliacao.Nivel) > Peso(antes))
            .Where(i => _persistido.PodeAlertar($"INICIO|{i.Chave}|{i.Avaliacao.Nivel}", TimeSpan.FromDays(7)))
            .ToList();
        _persistido.NiveisInicializacao = itens.GroupBy(i => i.Chave).ToDictionary(g => g.Key, g => g.First().Avaliacao.Nivel);

        if (alertar.Count == 0) return;

        _seguranca.RegistrarExterno(new EventoSeguranca
        {
            Tipo = "SUSPICIOUS_STARTUP",
            Severidade = alertar.Any(i => i.Avaliacao.Nivel == "critico") ? "CRITICAL" : "WARNING",
            Resumo = $"{alertar.Count} item(ns) {(primeiraVez ? "suspeito(s)" : "novo(s) ou que pioraram")} na inicialização do Windows: {string.Join(", ", alertar.Take(3).Select(i => i.Nome))}{(alertar.Count > 3 ? $" e mais {alertar.Count - 3}" : "")} -- veja todos em Segurança > Eventos",
            Detalhes = new Dictionary<string, object?>
            {
                ["novos"] = !primeiraVez,
                ["itens"] = alertar.Take(10).Select(i => i.ParaEnvio()).ToList()
            }
        });
    }

    // ------------------------------------------------------------------
    // Retrato pro portal
    // ------------------------------------------------------------------

    private (object dados, List<Dictionary<string, object?>> suspeitos) MontarRetrato(List<ConexaoTcp> conexoes, Dictionary<int, ProcessoInfo> processos, RegrasSeguranca regras)
    {
        var porPid = conexoes.Where(c => !(TabelaTcp.EhLocal(c.Remoto) && c.Estado != "LISTEN")).GroupBy(c => c.Pid).ToDictionary(g => g.Key, g => g.ToList());
        var listaProcessos = new List<Dictionary<string, object?>>();

        foreach (var (pid, lista) in porPid)
        {
            processos.TryGetValue(pid, out var proc);
            var estabelecidas = lista.Where(c => c.Estado == "ESTABLISHED" && !TabelaTcp.EhLocal(c.Remoto)).ToList();
            var escutando = lista.Where(c => c.Estado == "LISTEN" && !TabelaTcp.EhLocal(c.Local)).Select(c => c.PortaLocal).Distinct().OrderBy(p => p).ToList();
            _tentativas.TryGetValue(Chave(pid, processos), out var tentativas);
            if (estabelecidas.Count == 0 && escutando.Count == 0 && (tentativas == null || tentativas.Count == 0)) continue;

            var avaliacao = Avaliar(proc);
            var item = DetalhesProcesso(proc, pid, avaliacao);
            item["estabelecidas_total"] = estabelecidas.Count;
            item["estabelecidas"] = estabelecidas
                .GroupBy(c => (TabelaTcp.Texto(c.Remoto), c.PortaRemota))
                .Select(g => new { ip = g.Key.Item1, porta = g.Key.PortaRemota, qtd = g.Count(), publico = TabelaTcp.EhPublico(g.First().Remoto), porta_local = g.First().PortaLocal })
                .OrderByDescending(x => x.publico).ThenByDescending(x => x.qtd).Take(30).ToList();
            item["escutando"] = escutando.Take(30).ToList();
            if (tentativas is { Count: > 0 })
            {
                item["tentativas"] = new
                {
                    ips_distintos = tentativas.Count,
                    internet = tentativas.Keys.Count(ip => IPAddress.TryParse(ip, out var a) && TabelaTcp.EhPublico(a)),
                    portas = tentativas.Values.GroupBy(v => v.porta).Select(g => new { porta = g.Key, qtd = g.Count() }).OrderByDescending(x => x.qtd).Take(10).ToList(),
                    faixas = Faixas(tentativas.Keys).Take(8).ToList(),
                    amostra = tentativas.Keys.Take(10).ToList()
                };
            }
            listaProcessos.Add(item);
        }

        // Processos suspeitos mesmo sem rede (vigias, lançadores) -- com pai e filhos.
        var suspeitos = new List<Dictionary<string, object?>>();
        foreach (var proc in processos.Values.Where(p => p.Pid > 4 && !string.IsNullOrEmpty(p.Caminho)))
        {
            var avaliacao = Avaliar(proc);
            if (avaliacao.Pontos < 35) continue;
            var item = DetalhesProcesso(proc, proc.Pid, avaliacao);
            item["filhos"] = processos.Values.Where(f => f.PaiPid == proc.Pid).Select(f => $"{f.Nome} ({f.Pid})").Take(10).ToList();
            item["sha256"] = AnaliseExecutavel.Sha256(proc.Caminho!);
            suspeitos.Add(item);
        }

        var dados = new
        {
            coletado_em = DateTimeOffset.Now.ToString("o"),
            intervalo_segundos = _intervaloSegundos,
            janela_minutos = regras.RedeJanelaMinutos,
            totais = new
            {
                conexoes = conexoes.Count,
                estabelecidas_internet = conexoes.Count(c => c.Estado == "ESTABLISHED" && TabelaTcp.EhPublico(c.Remoto)),
                tentativas_em_andamento = conexoes.Count(c => c.Estado == "SYN_SENT"),
                programas_com_rede = listaProcessos.Count
            },
            processos = listaProcessos
                .OrderByDescending(p => (int)p["pontos"]!)
                .ThenByDescending(p => p.ContainsKey("tentativas") ? 1 : 0)
                .ThenByDescending(p => (int)p["estabelecidas_total"]!)
                .Take(120).ToList(),
            processos_suspeitos = suspeitos.OrderByDescending(s => (int)s["pontos"]!).Take(40).ToList(),
            firewall = FirewallService.Estado(),
            confiaveis = ConfiaveisService.ParaEnvio()
        };

        return (dados, suspeitos);
    }

    private static Avaliacao Avaliar(ProcessoInfo? proc)
    {
        if (proc == null || string.IsNullOrEmpty(proc.Caminho)) return new Avaliacao();
        return AnaliseExecutavel.Avaliar(AnaliseExecutavel.Analisar(proc.Caminho), proc.LinhaComando, proc.ComoSystem, proc.Nome);
    }

    private static Dictionary<string, object?> DetalhesProcesso(ProcessoInfo? proc, int pid, Avaliacao avaliacao)
    {
        var exe = proc?.Caminho != null ? AnaliseExecutavel.Analisar(proc.Caminho) : null;
        ProcessoInfo? pai = null;
        if (proc != null) CatalogoProcessos.Obter(Array.Empty<int>()).TryGetValue(proc.PaiPid, out pai);

        var detalhes = new Dictionary<string, object?>
        {
            ["pid"] = pid,
            ["nome"] = proc?.Nome ?? (pid == 4 ? "System" : $"PID {pid}"),
            ["caminho"] = proc?.Caminho,
            ["linha_comando"] = proc?.LinhaComando is { Length: > 400 } l ? l[..400] + "…" : proc?.LinhaComando,
            ["servico"] = proc?.Servico,
            ["como_system"] = proc?.ComoSystem ?? false,
            ["pai_pid"] = proc?.PaiPid,
            ["pai_nome"] = pai?.Nome,
            ["iniciado_em"] = proc?.CriadoEm?.ToString("o"),
            ["assinatura"] = exe?.Assinatura,
            ["assinante"] = exe?.Assinante,
            ["oculto"] = exe?.Oculto ?? false,
            ["arquivo_criado_em"] = exe?.CriadoEm?.ToString("o"),
            ["pontos"] = avaliacao.Pontos,
            ["nivel"] = avaliacao.Nivel,
            ["motivos"] = avaliacao.Motivos
        };
        if (AnaliseExecutavel.ScriptExecutado(proc?.Caminho, proc?.LinhaComando) is { } script)
        {
            foreach (var (chave, valor) in script) detalhes[chave] = valor;
        }
        return detalhes;
    }

    /// <summary>Faixas /24 (IPv4) mais tentadas -- mostra o "varrer faixa inteira".</summary>
    private static IEnumerable<object> Faixas(IEnumerable<string> ips) => ips
        .Where(ip => ip.Count(ch => ch == '.') == 3)
        .GroupBy(ip => ip[..ip.LastIndexOf('.')] + ".0/24")
        .Select(g => new { faixa = g.Key, qtd = g.Count() })
        .OrderByDescending(x => x.qtd);

    private bool Enviar(string tipo, object dados)
    {
        var guid = _machineGuid();
        return guid != null && new SegurancaEventoClient(_config()).EnviarRetratoAsync(guid, tipo, dados).GetAwaiter().GetResult();
    }

    public void Dispose()
    {
        _timer.Dispose();
    }

    /// <summary>Quando cada alerta saiu e o último inventário -- sobrevive a reinício do agente (sem re-alertar o mesmo item).</summary>
    private sealed class EstadoPersistido
    {
        private static readonly string Arquivo = Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData), "RDIntranetAgent", "monitor-rede.json");

        public Dictionary<string, DateTime> UltimoAlerta { get; set; } = new();
        public HashSet<string>? ChavesInicializacao { get; set; }
        public Dictionary<string, string>? NiveisInicializacao { get; set; }
        private bool _mudou;

        public bool PodeAlertar(string chave, TimeSpan intervalo)
        {
            if (UltimoAlerta.TryGetValue(chave, out var quando) && DateTime.Now - quando < intervalo) return false;
            UltimoAlerta[chave] = DateTime.Now;
            _mudou = true;
            return true;
        }

        public static EstadoPersistido Carregar()
        {
            try
            {
                if (File.Exists(Arquivo)) return JsonSerializer.Deserialize<EstadoPersistido>(File.ReadAllText(Arquivo)) ?? new();
            }
            catch { }
            return new();
        }

        public void Salvar()
        {
            if (!_mudou && File.Exists(Arquivo) && NiveisInicializacao != null) return;
            try
            {
                foreach (var velho in UltimoAlerta.Where(kv => DateTime.Now - kv.Value > TimeSpan.FromDays(8)).Select(kv => kv.Key).ToList()) UltimoAlerta.Remove(velho);
                Directory.CreateDirectory(Path.GetDirectoryName(Arquivo)!);
                File.WriteAllText(Arquivo, JsonSerializer.Serialize(this));
                _mudou = false;
            }
            catch { }
        }
    }
}
