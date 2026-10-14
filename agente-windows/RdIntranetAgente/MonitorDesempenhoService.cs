using System.Diagnostics;
using System.Net;
using System.Net.NetworkInformation;
using System.Runtime.InteropServices;
using System.Text.Json;
using System.Text.Json.Serialization;

namespace RdIntranetAgente;

/// <summary>
/// Tráfego por conexão TCP (IPv4) pela estatística estendida do Windows
/// (EStats): habilita a coleta em cada conexão estabelecida e lê os bytes
/// enviados/recebidos -- a diferença entre leituras dá quanto cada programa
/// e cada IP remoto trafegou. Precisa do agente elevado (o normal).
/// IPv6 não entra (a estatística é outra chamada; fica pra depois).
/// </summary>
public sealed class MedidorTrafegoConexoes
{
    public sealed record Trafego(int Pid, string IpRemoto, int PortaRemota, long Recebido, long Enviado);

    [StructLayout(LayoutKind.Sequential)]
    private struct MibTcpRow { public uint State, LocalAddr, LocalPort, RemoteAddr, RemotePort; }

    [DllImport("iphlpapi.dll")] private static extern uint GetExtendedTcpTable(IntPtr tabela, ref int tamanho, bool ordenar, int familia, int classe, uint reservado);
    [DllImport("iphlpapi.dll")] private static extern uint SetPerTcpConnectionEStats(ref MibTcpRow linha, int tipo, byte[] rw, uint versao, uint tamanho, uint deslocamento);
    [DllImport("iphlpapi.dll")] private static extern uint GetPerTcpConnectionEStats(ref MibTcpRow linha, int tipo, IntPtr rw, uint rwVersao, uint rwTamanho, IntPtr ros, uint rosVersao, uint rosTamanho, IntPtr rod, uint rodVersao, uint rodTamanho);

    private const int TcpConnectionEstatsData = 1;
    private readonly Dictionary<string, (long entrada, long saida)> _anterior = new();
    private readonly HashSet<string> _habilitadas = new();
    private uint _tamanhoRod;

    /// <summary>Bytes trocados por conexão desde a leitura anterior.</summary>
    public List<Trafego> Ler()
    {
        var resultado = new List<Trafego>();
        var tamanho = 0;
        GetExtendedTcpTable(IntPtr.Zero, ref tamanho, false, 2, 5, 0);
        if (tamanho <= 0) return resultado;
        tamanho += 32 * 1024;
        var buffer = Marshal.AllocHGlobal(tamanho);
        var rod = Marshal.AllocHGlobal(256);
        var vistas = new HashSet<string>();
        try
        {
            if (GetExtendedTcpTable(buffer, ref tamanho, false, 2, 5, 0) != 0) return resultado;
            var quantidade = Math.Min(Marshal.ReadInt32(buffer), 4000);
            for (var i = 0; i < quantidade; i++)
            {
                var p = buffer + 4 + i * 24;
                var linha = new MibTcpRow
                {
                    State = (uint)Marshal.ReadInt32(p), LocalAddr = (uint)Marshal.ReadInt32(p, 4), LocalPort = (uint)Marshal.ReadInt32(p, 8),
                    RemoteAddr = (uint)Marshal.ReadInt32(p, 12), RemotePort = (uint)Marshal.ReadInt32(p, 16)
                };
                if (linha.State != 5) continue; // só ESTABLISHED
                var remoto = new IPAddress(linha.RemoteAddr);
                if (IPAddress.IsLoopback(remoto)) continue;
                var pid = Marshal.ReadInt32(p, 20);
                var chave = $"{linha.LocalAddr}:{linha.LocalPort}-{linha.RemoteAddr}:{linha.RemotePort}";
                vistas.Add(chave);

                // Só conta conexão em que a estatística foi HABILITADA (sem isso a leitura
                // devolve lixo -- visto sem elevação: "petabytes" em segundos).
                if (!_habilitadas.Contains(chave))
                {
                    if (SetPerTcpConnectionEStats(ref linha, TcpConnectionEstatsData, new byte[] { 1 }, 0, 1, 0) != 0) continue;
                    _habilitadas.Add(chave);
                }

                if (!LerBytes(ref linha, rod, out var entrada, out var saida)) continue;
                if (_anterior.TryGetValue(chave, out var antes))
                {
                    var de = Math.Max(0, entrada - antes.entrada);
                    var ds = Math.Max(0, saida - antes.saida);
                    if (de + ds > 0 && de + ds < 20L * 1024 * 1024 * 1024) // salto impossível = leitura inválida
                    {
                        var porta = (Marshal.ReadByte(p, 16) << 8) | Marshal.ReadByte(p, 17);
                        resultado.Add(new Trafego(pid, remoto.ToString(), porta, de, ds));
                    }
                }
                _anterior[chave] = (entrada, saida);
            }
        }
        finally
        {
            Marshal.FreeHGlobal(buffer);
            Marshal.FreeHGlobal(rod);
        }

        foreach (var fechada in _anterior.Keys.Where(k => !vistas.Contains(k)).ToList()) _anterior.Remove(fechada);
        _habilitadas.IntersectWith(vistas);
        return resultado;
    }

    /// <summary>TCP_ESTATS_DATA_ROD_v0: DataBytesOut no início, DataBytesIn 16 bytes depois. O tamanho exato varia; acha o aceito uma vez.</summary>
    private bool LerBytes(ref MibTcpRow linha, IntPtr rod, out long entrada, out long saida)
    {
        entrada = saida = 0;
        foreach (var tamanho in _tamanhoRod != 0 ? new[] { _tamanhoRod } : new uint[] { 96, 88, 104, 112 })
        {
            for (var b = 0; b < 256; b += 8) Marshal.WriteInt64(rod, b, 0);
            if (GetPerTcpConnectionEStats(ref linha, TcpConnectionEstatsData, IntPtr.Zero, 0, 0, IntPtr.Zero, 0, 0, rod, 0, tamanho) == 0)
            {
                _tamanhoRod = tamanho;
                saida = Marshal.ReadInt64(rod, 0);
                entrada = Marshal.ReadInt64(rod, 16);
                return true;
            }
        }
        return false;
    }
}

/// <summary>Limiares de pico (regras do servidor, config/seguranca-regras.json -> "desempenho").</summary>
public sealed class RegrasDesempenho
{
    public double CpuPico { get; init; } = 90;
    public double MemoriaPico { get; init; } = 90;
    public double DiscoPico { get; init; } = 90;
    /// <summary>% da velocidade do adaptador mais rápido.</summary>
    public double RedePicoPercentual { get; init; } = 80;
    public int DuracaoMinimaSegundos { get; init; } = 60;
    public int RetratoMinutos { get; init; } = 15;
}

/// <summary>
/// Coleta CONTÍNUA de desempenho (agente 1.0.61+) -- pra servidor e máquina
/// que o administrador quer acompanhar. Ligada pelo portal (padrão global e
/// por máquina) ou pela chave da própria tela Desempenho. A cada N segundos:
/// CPU, memória, disco, rede, processos (CPU/memória/disco) e tráfego por
/// conexão (por programa e por IP). Guarda:
///  - minuto a minuto por 7 dias (média e máximo) -> gráficos de 24 h e 7 dias;
///  - consumo por programa por hora (tempo de CPU, memória máxima, disco, rede);
///  - tráfego por IP remoto por hora (bytes e conexões, com os programas);
///  - PICOS: CPU/memória/disco/rede acima do limite por um tempo mínimo viram
///    um episódio com início, duração, máximo e QUEM causou (processo,
///    software/fabricante, e na rede os IPs).
/// Tudo fica num arquivo local (sobrevive a reinício) e vai pro portal como
/// retrato "desempenho".
/// </summary>
public sealed class MonitorDesempenhoService : IDisposable
{
    // ---------- dados guardados ----------
    public sealed class Minuto
    {
        [JsonPropertyName("t")] public long T { get; set; }            // unix (início do minuto)
        [JsonPropertyName("c")] public float Cpu { get; set; }          // média
        [JsonPropertyName("cx")] public float CpuMax { get; set; }
        [JsonPropertyName("m")] public float Memoria { get; set; }      // % média
        [JsonPropertyName("mx")] public float MemoriaMax { get; set; }
        [JsonPropertyName("d")] public float Disco { get; set; }        // % ativo média
        [JsonPropertyName("dx")] public float DiscoMax { get; set; }
        [JsonPropertyName("dl")] public float DiscoLeitura { get; set; } // MB/s média
        [JsonPropertyName("de")] public float DiscoEscrita { get; set; }
        [JsonPropertyName("rr")] public float RedeRecebe { get; set; }   // Mbps média
        [JsonPropertyName("re")] public float RedeEnvia { get; set; }
        [JsonPropertyName("rx")] public float RedeMax { get; set; }     // Mbps (rx+tx) máximo
        [JsonPropertyName("n")] public int Amostras { get; set; }
    }

    public sealed class ConsumoPrograma
    {
        [JsonPropertyName("nome")] public string Nome { get; set; } = "";
        [JsonPropertyName("caminho")] public string? Caminho { get; set; }
        [JsonPropertyName("software")] public string? Software { get; set; }
        [JsonPropertyName("cpu_s")] public double CpuSegundos { get; set; }
        [JsonPropertyName("mem_max_mb")] public double MemoriaMaxMb { get; set; }
        [JsonPropertyName("disco_mb")] public double DiscoMb { get; set; }
        [JsonPropertyName("rede_mb")] public double RedeMb { get; set; }
    }

    public sealed class ConsumoIp
    {
        [JsonPropertyName("ip")] public string Ip { get; set; } = "";
        [JsonPropertyName("recebido_mb")] public double RecebidoMb { get; set; }
        [JsonPropertyName("enviado_mb")] public double EnviadoMb { get; set; }
        [JsonPropertyName("conexoes_max")] public int ConexoesMax { get; set; }
        [JsonPropertyName("portas")] public List<int> Portas { get; set; } = new();
        [JsonPropertyName("programas")] public List<string> Programas { get; set; } = new();
    }

    public sealed class Hora
    {
        [JsonPropertyName("t")] public long T { get; set; }
        [JsonPropertyName("programas")] public Dictionary<string, ConsumoPrograma> Programas { get; set; } = new();
        [JsonPropertyName("ips")] public Dictionary<string, ConsumoIp> Ips { get; set; } = new();
    }

    public sealed class Responsavel
    {
        [JsonPropertyName("nome")] public string Nome { get; set; } = "";
        [JsonPropertyName("pid")] public int Pid { get; set; }
        [JsonPropertyName("caminho")] public string? Caminho { get; set; }
        [JsonPropertyName("software")] public string? Software { get; set; }
        [JsonPropertyName("valor")] public double Valor { get; set; }
        [JsonPropertyName("unidade")] public string Unidade { get; set; } = "";
    }

    public sealed class Pico
    {
        [JsonPropertyName("metrica")] public string Metrica { get; set; } = "";   // cpu | memoria | disco | rede
        [JsonPropertyName("inicio")] public DateTime Inicio { get; set; }
        [JsonPropertyName("fim")] public DateTime? Fim { get; set; }
        [JsonPropertyName("maximo")] public double Maximo { get; set; }
        [JsonPropertyName("media")] public double Media { get; set; }
        [JsonPropertyName("unidade")] public string Unidade { get; set; } = "%";
        [JsonPropertyName("responsaveis")] public List<Responsavel> Responsaveis { get; set; } = new();
        [JsonPropertyName("ips")] public List<ConsumoIp> Ips { get; set; } = new();
        [JsonIgnore] public int Amostras { get; set; }
        [JsonIgnore] public double Soma { get; set; }
        [JsonPropertyName("duracao_s")] public int DuracaoSegundos => (int)((Fim ?? DateTime.Now) - Inicio).TotalSeconds;
    }

    private sealed class Arquivo
    {
        public List<Minuto> Minutos { get; set; } = new();
        public List<Hora> Horas { get; set; } = new();
        public List<Pico> Picos { get; set; } = new();
        public bool LigadoLocal { get; set; }
    }

    private static readonly string CaminhoArquivo = Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData), "RDIntranetAgent", "desempenho.json");

    private readonly Func<Config> _config;
    private readonly Func<string?> _machineGuid;
    private readonly object _trava = new();
    private readonly System.Threading.Timer _timer;
    private readonly Arquivo _dados;
    private MedidorDesempenho? _medidor;
    private readonly MedidorTrafegoConexoes _trafego = new();
    private readonly Dictionary<string, string?> _softwares = new(StringComparer.OrdinalIgnoreCase);
    private sealed class EstadoPico { public DateTime Desde; public DateTime AbaixoDesde; public Pico? Aberto; }
    private readonly Dictionary<string, EstadoPico> _estadoPico = new();
    private int _executando;
    private bool _servidor;
    private int _intervalo = 5;
    private DateTime _proximoRetrato = DateTime.MinValue;
    private DateTime _proximoSalvar = DateTime.MinValue;
    private Minuto? _minutoAtual;
    private Hora? _horaAtual;

    public RegrasDesempenho Regras { get; set; } = new();

    /// <summary>Última amostra (pra tela "Agora" quando a coleta contínua está ligada).</summary>
    public MedidorDesempenho.Amostra? UltimaAmostra { get; private set; }
    public List<MedidorDesempenho.LinhaProcesso>? UltimosProcessos { get; private set; }
    public List<MedidorTrafegoConexoes.Trafego>? UltimoTrafego { get; private set; }

    public event Action? Atualizado;

    public MonitorDesempenhoService(Func<Config> config, Func<string?> machineGuid)
    {
        _config = config;
        _machineGuid = machineGuid;
        _dados = Carregar();
        _timer = new System.Threading.Timer(_ => Ciclo(), null, Timeout.Infinite, Timeout.Infinite);
        Reprogramar();
    }

    public bool LigadoLocal => _dados.LigadoLocal;
    public bool LigadoServidor => _servidor;
    public bool Ativo => _servidor || _dados.LigadoLocal;
    public int IntervaloSegundos => _intervalo;

    /// <summary>Do heartbeat ("monitor_desempenho"): o servidor liga/desliga e define o intervalo.</summary>
    public void AplicarServidor(bool ativo, int intervalo)
    {
        intervalo = Math.Clamp(intervalo, 2, 60);
        if (ativo == _servidor && intervalo == _intervalo) return;
        _servidor = ativo;
        _intervalo = intervalo;
        Reprogramar();
        LogAtividade.Registrar(NivelAtividade.Info, "DESEMPENHO", ativo ? $"Coleta contínua ligada pelo servidor (a cada {intervalo} s)." : "Coleta contínua: servidor não pede mais.");
    }

    /// <summary>Chave da tela Desempenho (fica salva na máquina).</summary>
    public void DefinirLocal(bool ligado)
    {
        lock (_trava) { _dados.LigadoLocal = ligado; }
        Salvar();
        Reprogramar();
        LogAtividade.Registrar(NivelAtividade.Info, "DESEMPENHO", ligado ? "Coleta contínua ligada nesta máquina pela tela Desempenho." : "Coleta contínua desligada pela tela Desempenho.");
    }

    private void Reprogramar()
    {
        if (Ativo)
        {
            _medidor ??= new MedidorDesempenho();
            _timer.Change(1000, _intervalo * 1000);
        }
        else
        {
            _timer.Change(Timeout.Infinite, Timeout.Infinite);
        }
    }

    // ------------------------------------------------------------------

    private void Ciclo()
    {
        if (Interlocked.Exchange(ref _executando, 1) == 1) return;
        try
        {
            var medidor = _medidor ??= new MedidorDesempenho();
            var amostra = medidor.Medir();
            var processos = medidor.Processos();
            List<MedidorTrafegoConexoes.Trafego> trafego;
            try { trafego = _trafego.Ler(); } catch { trafego = new(); }
            var conexoes = TabelaTcp.Ler().Where(c => c.Estado == "ESTABLISHED" && !TabelaTcp.EhLocal(c.Remoto)).ToList();
            var agora = DateTime.Now;

            lock (_trava)
            {
                Acumular(amostra, processos, trafego, conexoes, agora);
                AvaliarPicos(amostra, processos, trafego, agora);
            }
            UltimaAmostra = amostra;
            UltimosProcessos = processos;
            UltimoTrafego = trafego;

            if (agora >= _proximoSalvar) { Salvar(); _proximoSalvar = agora.AddMinutes(5); }
            if (agora >= _proximoRetrato)
            {
                _proximoRetrato = agora + (EnviarRetrato() ? TimeSpan.FromMinutes(Math.Clamp(Regras.RetratoMinutos, 1, 120)) : TimeSpan.FromMinutes(2));
            }
            Atualizado?.Invoke();
        }
        catch (Exception ex)
        {
            LogAtividade.Registrar(NivelAtividade.Aviso, "DESEMPENHO", $"Falha numa coleta: {ex.Message}");
        }
        finally
        {
            Interlocked.Exchange(ref _executando, 0);
        }
    }

    private void Acumular(MedidorDesempenho.Amostra a, List<MedidorDesempenho.LinhaProcesso> processos, List<MedidorTrafegoConexoes.Trafego> trafego,
        List<ConexaoTcp> conexoes, DateTime agora)
    {
        var memoriaPct = a.MemoriaTotalGb > 0 ? a.MemoriaUsadaGb / a.MemoriaTotalGb * 100 : 0;
        var redeTotal = a.RedeRecebeMbps + a.RedeEnviaMbps;

        // Minuto
        var inicioMinuto = new DateTimeOffset(agora.Year, agora.Month, agora.Day, agora.Hour, agora.Minute, 0, DateTimeOffset.Now.Offset).ToUnixTimeSeconds();
        if (_minutoAtual == null || _minutoAtual.T != inicioMinuto)
        {
            _minutoAtual = new Minuto { T = inicioMinuto };
            _dados.Minutos.Add(_minutoAtual);
            var corte = DateTimeOffset.Now.AddDays(-7).ToUnixTimeSeconds();
            _dados.Minutos.RemoveAll(m => m.T < corte);
        }
        var mi = _minutoAtual;
        var n = mi.Amostras;
        float Media(float atual, double novo) => (float)((atual * n + novo) / (n + 1));
        mi.Cpu = Media(mi.Cpu, a.Cpu); mi.CpuMax = Math.Max(mi.CpuMax, (float)a.Cpu);
        mi.Memoria = Media(mi.Memoria, memoriaPct); mi.MemoriaMax = Math.Max(mi.MemoriaMax, (float)memoriaPct);
        mi.Disco = Media(mi.Disco, a.DiscoAtivo); mi.DiscoMax = Math.Max(mi.DiscoMax, (float)a.DiscoAtivo);
        mi.DiscoLeitura = Media(mi.DiscoLeitura, a.DiscoLeituraMBs); mi.DiscoEscrita = Media(mi.DiscoEscrita, a.DiscoEscritaMBs);
        mi.RedeRecebe = Media(mi.RedeRecebe, a.RedeRecebeMbps); mi.RedeEnvia = Media(mi.RedeEnvia, a.RedeEnviaMbps);
        mi.RedeMax = Math.Max(mi.RedeMax, (float)redeTotal);
        mi.Amostras = n + 1;

        // Hora: consumo por programa e por IP
        var inicioHora = new DateTimeOffset(agora.Year, agora.Month, agora.Day, agora.Hour, 0, 0, DateTimeOffset.Now.Offset).ToUnixTimeSeconds();
        if (_horaAtual == null || _horaAtual.T != inicioHora)
        {
            _horaAtual = _dados.Horas.FirstOrDefault(h => h.T == inicioHora) ?? new Hora { T = inicioHora };
            if (!_dados.Horas.Contains(_horaAtual)) _dados.Horas.Add(_horaAtual);
            var corte = DateTimeOffset.Now.AddDays(-7).ToUnixTimeSeconds();
            _dados.Horas.RemoveAll(h => h.T < corte);
            foreach (var h in _dados.Horas.Where(h => h != _horaAtual)) Enxugar(h);
        }
        var hora = _horaAtual;
        var segundos = _intervalo;
        var porPid = processos.ToDictionary(p => p.Pid);

        foreach (var p in processos.Where(p => p.Pid > 0))
        {
            var chave = (p.Caminho ?? p.Nome).ToLowerInvariant();
            if (!hora.Programas.TryGetValue(chave, out var c))
            {
                hora.Programas[chave] = c = new ConsumoPrograma { Nome = p.Nome, Caminho = p.Caminho, Software = Software(p.Caminho) };
            }
            c.CpuSegundos += p.Cpu / 100 * Environment.ProcessorCount * segundos;
            c.MemoriaMaxMb = Math.Max(c.MemoriaMaxMb, p.MemoriaMb);
            c.DiscoMb += p.DiscoKBs * segundos / 1024;
        }

        foreach (var t in trafego)
        {
            var mb = (t.Recebido + t.Enviado) / 1024d / 1024;
            if (porPid.TryGetValue(t.Pid, out var p))
            {
                var chave = (p.Caminho ?? p.Nome).ToLowerInvariant();
                if (hora.Programas.TryGetValue(chave, out var c)) c.RedeMb += mb;
            }
            if (!hora.Ips.TryGetValue(t.IpRemoto, out var ip)) hora.Ips[t.IpRemoto] = ip = new ConsumoIp { Ip = t.IpRemoto };
            ip.RecebidoMb += t.Recebido / 1024d / 1024;
            ip.EnviadoMb += t.Enviado / 1024d / 1024;
            if (ip.Portas.Count < 8 && !ip.Portas.Contains(t.PortaRemota)) ip.Portas.Add(t.PortaRemota);
            var nome = porPid.TryGetValue(t.Pid, out var dono) ? dono.Nome : $"PID {t.Pid}";
            if (ip.Programas.Count < 5 && !ip.Programas.Contains(nome)) ip.Programas.Add(nome);
        }

        foreach (var grupo in conexoes.GroupBy(c => TabelaTcp.Texto(c.Remoto)))
        {
            if (!hora.Ips.TryGetValue(grupo.Key, out var ip)) hora.Ips[grupo.Key] = ip = new ConsumoIp { Ip = grupo.Key };
            ip.ConexoesMax = Math.Max(ip.ConexoesMax, grupo.Count());
            foreach (var c in grupo.Take(3))
            {
                var nome = porPid.TryGetValue(c.Pid, out var dono) ? dono.Nome : $"PID {c.Pid}";
                if (ip.Programas.Count < 5 && !ip.Programas.Contains(nome)) ip.Programas.Add(nome);
            }
        }
    }

    /// <summary>Hora que já passou: fica só o que importa (top 25 programas por cada critério, top 40 IPs).</summary>
    private static void Enxugar(Hora h)
    {
        if (h.Programas.Count > 60)
        {
            var manter = h.Programas.Values.OrderByDescending(p => p.CpuSegundos).Take(25)
                .Concat(h.Programas.Values.OrderByDescending(p => p.MemoriaMaxMb).Take(25))
                .Concat(h.Programas.Values.OrderByDescending(p => p.DiscoMb).Take(25))
                .Concat(h.Programas.Values.OrderByDescending(p => p.RedeMb).Take(25)).ToHashSet();
            h.Programas = h.Programas.Where(kv => manter.Contains(kv.Value)).ToDictionary(kv => kv.Key, kv => kv.Value);
        }
        if (h.Ips.Count > 40)
        {
            var manter = h.Ips.Values.OrderByDescending(i => i.RecebidoMb + i.EnviadoMb).Take(30).Concat(h.Ips.Values.OrderByDescending(i => i.ConexoesMax).Take(15)).ToHashSet();
            h.Ips = h.Ips.Where(kv => manter.Contains(kv.Value)).ToDictionary(kv => kv.Key, kv => kv.Value);
        }
    }

    private void AvaliarPicos(MedidorDesempenho.Amostra a, List<MedidorDesempenho.LinhaProcesso> processos, List<MedidorTrafegoConexoes.Trafego> trafego, DateTime agora)
    {
        var memoriaPct = a.MemoriaTotalGb > 0 ? a.MemoriaUsadaGb / a.MemoriaTotalGb * 100 : 0;
        var velocidadeMbps = VelocidadeRedeMbps();
        var redePct = velocidadeMbps > 0 ? (a.RedeRecebeMbps + a.RedeEnviaMbps) / velocidadeMbps * 100 : 0;

        Avaliar("cpu", a.Cpu, Regras.CpuPico, "%", a.Cpu, () => processos.OrderByDescending(p => p.Cpu).Take(3)
            .Select(p => Resp(p, p.Cpu, "%")));
        Avaliar("memoria", memoriaPct, Regras.MemoriaPico, "%", memoriaPct, () => processos.OrderByDescending(p => p.MemoriaMb).Take(3)
            .Select(p => Resp(p, p.MemoriaMb, "MB")));
        Avaliar("disco", a.DiscoAtivo, Regras.DiscoPico, "%", a.DiscoAtivo, () => processos.OrderByDescending(p => p.DiscoKBs).Take(3)
            .Select(p => Resp(p, p.DiscoKBs / 1024, "MB/s")));
        Avaliar("rede", redePct, Regras.RedePicoPercentual, "Mbps", a.RedeRecebeMbps + a.RedeEnviaMbps, () =>
        {
            var porPid = processos.ToDictionary(p => p.Pid);
            return trafego.GroupBy(t => t.Pid).Select(g => (pid: g.Key, mb: g.Sum(t => t.Recebido + t.Enviado) / 1024d / 1024))
                .OrderByDescending(x => x.mb).Take(3)
                .Select(x => porPid.TryGetValue(x.pid, out var p)
                    ? Resp(p, x.mb * 8 / _intervalo, "Mbps")
                    : new Responsavel { Nome = $"PID {x.pid}", Pid = x.pid, Valor = x.mb * 8 / _intervalo, Unidade = "Mbps" });
        }, trafego);

        void Avaliar(string metrica, double percentual, double limite, string unidade, double valor, Func<IEnumerable<Responsavel>> responsaveis, List<MedidorTrafegoConexoes.Trafego>? trafegoIps = null)
        {
            if (!_estadoPico.TryGetValue(metrica, out var estado)) _estadoPico[metrica] = estado = new EstadoPico();
            if (percentual >= limite)
            {
                if (estado.Desde == default) estado.Desde = agora;
                estado.AbaixoDesde = default;
                if (estado.Aberto == null && (agora - estado.Desde).TotalSeconds >= Regras.DuracaoMinimaSegundos)
                {
                    estado.Aberto = new Pico { Metrica = metrica, Inicio = estado.Desde, Unidade = unidade };
                    _dados.Picos.Add(estado.Aberto);
                    if (_dados.Picos.Count > 300) _dados.Picos.RemoveAt(0);
                    LogAtividade.Registrar(NivelAtividade.Aviso, "DESEMPENHO", $"Pico de {Rotulo(metrica)} desde {estado.Desde:HH:mm:ss} (acima de {limite:0}%).");
                }
                if (estado.Aberto is { } pico)
                {
                    pico.Fim = null; // ainda acontecendo
                    pico.Amostras++;
                    pico.Soma += valor;
                    pico.Media = Math.Round(pico.Soma / pico.Amostras, 1);
                    if (valor >= pico.Maximo)
                    {
                        pico.Maximo = Math.Round(valor, 1);
                        pico.Responsaveis = responsaveis().ToList();
                        if (trafegoIps != null)
                        {
                            pico.Ips = trafegoIps.GroupBy(t => t.IpRemoto)
                                .Select(g => new ConsumoIp { Ip = g.Key, RecebidoMb = g.Sum(t => t.Recebido) / 1024d / 1024, EnviadoMb = g.Sum(t => t.Enviado) / 1024d / 1024, Portas = g.Select(t => t.PortaRemota).Distinct().Take(5).ToList() })
                                .OrderByDescending(i => i.RecebidoMb + i.EnviadoMb).Take(5).ToList();
                        }
                    }
                }
            }
            else if (estado.Aberto is { } pico)
            {
                // Fecha só depois de 30 s abaixo do limite: uma oscilação não vira dois episódios.
                if (estado.AbaixoDesde == default) { estado.AbaixoDesde = agora; pico.Fim = agora; }
                else if ((agora - estado.AbaixoDesde).TotalSeconds >= 30) { estado.Aberto = null; estado.Desde = default; estado.AbaixoDesde = default; }
            }
            else
            {
                estado.Desde = default;
            }
        }
    }

    private Responsavel Resp(MedidorDesempenho.LinhaProcesso p, double valor, string unidade) => new()
    {
        Nome = p.Nome, Pid = p.Pid, Caminho = p.Caminho, Software = Software(p.Caminho), Valor = Math.Round(valor, 1), Unidade = unidade
    };

    /// <summary>"Produto (Fabricante)" do arquivo -- responde "que software fez isso".</summary>
    public string? Software(string? caminho)
    {
        if (string.IsNullOrEmpty(caminho)) return null;
        lock (_softwares)
        {
            if (_softwares.TryGetValue(caminho, out var s)) return s;
            try
            {
                var info = FileVersionInfo.GetVersionInfo(caminho);
                var produto = string.IsNullOrWhiteSpace(info.ProductName) ? info.FileDescription : info.ProductName;
                s = string.Join(" · ", new[] { produto?.Trim(), info.CompanyName?.Trim() }.Where(x => !string.IsNullOrEmpty(x)).Distinct());
                if (s == "") s = null;
            }
            catch { s = null; }
            _softwares[caminho] = s;
            return s;
        }
    }

    private static double VelocidadeRedeMbps()
    {
        try
        {
            return NetworkInterface.GetAllNetworkInterfaces()
                .Where(a => a.OperationalStatus == OperationalStatus.Up && a.NetworkInterfaceType != NetworkInterfaceType.Loopback && a.Speed > 0)
                .Select(a => a.Speed / 1_000_000d).DefaultIfEmpty(0).Max();
        }
        catch { return 0; }
    }

    public static string Rotulo(string metrica) => metrica switch { "cpu" => "CPU", "memoria" => "memória", "disco" => "disco", "rede" => "rede", _ => metrica };

    // ------------------------------------------------------------------
    // Consultas pra tela e pro portal
    // ------------------------------------------------------------------

    public List<Minuto> Minutos(TimeSpan periodo)
    {
        var corte = DateTimeOffset.Now.Add(-periodo).ToUnixTimeSeconds();
        lock (_trava) { return _dados.Minutos.Where(m => m.T >= corte).ToList(); }
    }

    public List<Pico> Picos()
    {
        lock (_trava) { return _dados.Picos.OrderByDescending(p => p.Inicio).ToList(); }
    }

    /// <summary>Consumo somado no período (programas e IPs).</summary>
    public (List<ConsumoPrograma> programas, List<ConsumoIp> ips) Consumo(TimeSpan periodo)
    {
        var corte = DateTimeOffset.Now.Add(-periodo).ToUnixTimeSeconds();
        lock (_trava)
        {
            var horas = _dados.Horas.Where(h => h.T >= corte - 3600).ToList();
            var programas = horas.SelectMany(h => h.Programas)
                .GroupBy(kv => kv.Key)
                .Select(g => new ConsumoPrograma
                {
                    Nome = g.First().Value.Nome, Caminho = g.First().Value.Caminho, Software = g.First().Value.Software,
                    CpuSegundos = g.Sum(x => x.Value.CpuSegundos), MemoriaMaxMb = g.Max(x => x.Value.MemoriaMaxMb),
                    DiscoMb = g.Sum(x => x.Value.DiscoMb), RedeMb = g.Sum(x => x.Value.RedeMb)
                }).ToList();
            var ips = horas.SelectMany(h => h.Ips.Values)
                .GroupBy(i => i.Ip)
                .Select(g => new ConsumoIp
                {
                    Ip = g.Key, RecebidoMb = g.Sum(i => i.RecebidoMb), EnviadoMb = g.Sum(i => i.EnviadoMb), ConexoesMax = g.Max(i => i.ConexoesMax),
                    Portas = g.SelectMany(i => i.Portas).Distinct().Take(8).ToList(), Programas = g.SelectMany(i => i.Programas).Distinct().Take(5).ToList()
                }).ToList();
            return (programas, ips);
        }
    }

    private bool EnviarRetrato()
    {
        var guid = _machineGuid();
        if (guid == null) return false;

        // 24 h em blocos de 5 min; 7 dias por hora.
        object Serie(TimeSpan periodo, int blocoMin)
        {
            return Minutos(periodo)
                .GroupBy(m => m.T / (blocoMin * 60) * (blocoMin * 60))
                .OrderBy(g => g.Key)
                .Select(g => new
                {
                    t = g.Key,
                    cpu = Math.Round(g.Average(m => m.Cpu), 1), cpu_max = Math.Round(g.Max(m => m.CpuMax), 1),
                    mem = Math.Round(g.Average(m => m.Memoria), 1), mem_max = Math.Round(g.Max(m => m.MemoriaMax), 1),
                    disco = Math.Round(g.Average(m => m.Disco), 1), disco_max = Math.Round(g.Max(m => m.DiscoMax), 1),
                    rede_rx = Math.Round(g.Average(m => m.RedeRecebe), 2), rede_tx = Math.Round(g.Average(m => m.RedeEnvia), 2), rede_max = Math.Round(g.Max(m => m.RedeMax), 2)
                }).ToList();
        }
        var (programas, ips) = Consumo(TimeSpan.FromHours(24));
        var dados = new
        {
            coletado_em = DateTimeOffset.Now.ToString("o"),
            intervalo_segundos = _intervalo,
            ligado_por = _servidor ? "servidor" : "maquina",
            maquina = new { processadores = Environment.ProcessorCount, memoria_gb = Math.Round(UltimaAmostra?.MemoriaTotalGb ?? 0, 1), rede_mbps = VelocidadeRedeMbps() },
            limites = Regras,
            serie_24h = Serie(TimeSpan.FromHours(24), 5),
            serie_7d = Serie(TimeSpan.FromDays(7), 60),
            picos = Picos().Take(60).ToList(),
            top_cpu = programas.OrderByDescending(p => p.CpuSegundos).Take(15).ToList(),
            top_memoria = programas.OrderByDescending(p => p.MemoriaMaxMb).Take(15).ToList(),
            top_disco = programas.OrderByDescending(p => p.DiscoMb).Take(15).ToList(),
            top_rede = programas.OrderByDescending(p => p.RedeMb).Where(p => p.RedeMb > 0).Take(15).ToList(),
            top_ips = ips.OrderByDescending(i => i.RecebidoMb + i.EnviadoMb).Take(20).ToList(),
            top_ips_conexoes = ips.OrderByDescending(i => i.ConexoesMax).Take(15).ToList()
        };
        return new SegurancaEventoClient(_config()).EnviarRetratoAsync(guid, "desempenho", dados).GetAwaiter().GetResult();
    }

    public void ForcarRetrato() => _proximoRetrato = DateTime.MinValue;

    // ------------------------------------------------------------------

    private static Arquivo Carregar()
    {
        try
        {
            if (File.Exists(CaminhoArquivo)) return JsonSerializer.Deserialize<Arquivo>(File.ReadAllText(CaminhoArquivo)) ?? new();
        }
        catch { }
        return new();
    }

    private void Salvar()
    {
        try
        {
            string json;
            lock (_trava) { json = JsonSerializer.Serialize(_dados); }
            Directory.CreateDirectory(Path.GetDirectoryName(CaminhoArquivo)!);
            File.WriteAllText(CaminhoArquivo + ".novo", json);
            File.Move(CaminhoArquivo + ".novo", CaminhoArquivo, overwrite: true);
        }
        catch { }
    }

    public void Dispose()
    {
        _timer.Dispose();
        if (Ativo) Salvar();
        _medidor?.Dispose();
    }
}
