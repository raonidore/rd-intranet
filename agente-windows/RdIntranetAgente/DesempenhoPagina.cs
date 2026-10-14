using System.Net;
using System.Diagnostics;
using System.Drawing;
using System.Drawing.Drawing2D;
using System.Net.NetworkInformation;
using System.Runtime.InteropServices;
using System.Windows.Forms;

namespace RdIntranetAgente;

/// <summary>
/// Leitura dos contadores do Windows. CPU e disco pela PDH com nomes em
/// INGLÊS (PdhAddEnglishCounter) -- o Windows em português traduz os nomes
/// dos contadores e ferramenta que usa o nome local quebra; memória pela
/// GlobalMemoryStatusEx; rede pela soma dos adaptadores; processos por
/// OpenProcess com o mínimo de acesso (funciona até pra processo do SYSTEM).
/// </summary>
public sealed class MedidorDesempenho : IDisposable
{
    public sealed record Amostra(double Cpu, double MemoriaUsadaGb, double MemoriaTotalGb, double DiscoLeituraMBs, double DiscoEscritaMBs,
        double DiscoAtivo, double RedeRecebeMbps, double RedeEnviaMbps, int Processos, int Threads);

    public sealed record LinhaProcesso(int Pid, string Nome, string? Caminho, double Cpu, double MemoriaMb, double DiscoKBs);

    private IntPtr _consulta;
    private IntPtr _contadorCpu, _contadorLeitura, _contadorEscrita, _contadorOcioso;
    private long _redeRecebido, _redeEnviado;
    private DateTime _redeEm;
    private readonly Dictionary<int, (long cpu100ns, ulong io, DateTime quando, DateTime inicio)> _anterior = new();
    private readonly Dictionary<int, string?> _caminhos = new();

    public MedidorDesempenho()
    {
        if (PdhOpenQuery(null, IntPtr.Zero, out _consulta) != 0) _consulta = IntPtr.Zero;
        if (_consulta == IntPtr.Zero) return;
        // "Processor Utility" é o mesmo número do Gerenciador de Tarefas; servidor antigo sem ele usa "% Processor Time".
        if (PdhAddEnglishCounter(_consulta, @"\Processor Information(_Total)\% Processor Utility", IntPtr.Zero, out _contadorCpu) != 0)
            PdhAddEnglishCounter(_consulta, @"\Processor(_Total)\% Processor Time", IntPtr.Zero, out _contadorCpu);
        PdhAddEnglishCounter(_consulta, @"\PhysicalDisk(_Total)\Disk Read Bytes/sec", IntPtr.Zero, out _contadorLeitura);
        PdhAddEnglishCounter(_consulta, @"\PhysicalDisk(_Total)\Disk Write Bytes/sec", IntPtr.Zero, out _contadorEscrita);
        PdhAddEnglishCounter(_consulta, @"\PhysicalDisk(_Total)\% Idle Time", IntPtr.Zero, out _contadorOcioso);
        PdhCollectQueryData(_consulta); // primeira coleta: taxas precisam de dois pontos
    }

    public Amostra Medir()
    {
        if (_consulta != IntPtr.Zero) PdhCollectQueryData(_consulta);
        var cpu = Math.Clamp(Valor(_contadorCpu), 0, 100);
        var ocioso = Valor(_contadorOcioso);

        var memoria = new MemoryStatusEx { dwLength = (uint)Marshal.SizeOf<MemoryStatusEx>() };
        GlobalMemoryStatusEx(ref memoria);
        var totalGb = memoria.ullTotalPhys / 1024d / 1024 / 1024;
        var usadaGb = (memoria.ullTotalPhys - memoria.ullAvailPhys) / 1024d / 1024 / 1024;

        long recebido = 0, enviado = 0;
        try
        {
            foreach (var adaptador in NetworkInterface.GetAllNetworkInterfaces().Where(a => a.OperationalStatus == OperationalStatus.Up && a.NetworkInterfaceType != NetworkInterfaceType.Loopback))
            {
                var e = adaptador.GetIPStatistics();
                recebido += e.BytesReceived;
                enviado += e.BytesSent;
            }
        }
        catch { }
        var agora = DateTime.UtcNow;
        double recebeMbps = 0, enviaMbps = 0;
        if (_redeEm != default)
        {
            var segundos = Math.Max(0.1, (agora - _redeEm).TotalSeconds);
            recebeMbps = Math.Max(0, recebido - _redeRecebido) * 8 / 1_000_000d / segundos;
            enviaMbps = Math.Max(0, enviado - _redeEnviado) * 8 / 1_000_000d / segundos;
        }
        (_redeRecebido, _redeEnviado, _redeEm) = (recebido, enviado, agora);

        var processos = Process.GetProcesses();
        var threads = 0;
        foreach (var p in processos) { try { threads += p.Threads.Count; } catch { } p.Dispose(); }

        return new Amostra(cpu, usadaGb, totalGb, Valor(_contadorLeitura) / 1024 / 1024, Valor(_contadorEscrita) / 1024 / 1024,
            ocioso > 0 ? Math.Clamp(100 - ocioso, 0, 100) : 0, recebeMbps, enviaMbps, processos.Length, threads);
    }

    /// <summary>CPU (% do total da máquina), memória e disco de cada processo desde a última chamada.</summary>
    public List<LinhaProcesso> Processos()
    {
        var nucleos = Environment.ProcessorCount;
        var agora = DateTime.UtcNow;
        var lista = new List<LinhaProcesso>();
        var vistos = new HashSet<int>();

        foreach (var p in Process.GetProcesses())
        {
            using (p)
            {
                var pid = p.Id;
                vistos.Add(pid);
                double memoriaMb;
                try { memoriaMb = p.WorkingSet64 / 1024d / 1024; } catch { memoriaMb = 0; }

                double cpu = 0, discoKBs = 0;
                var handle = OpenProcess(0x1000 /* QUERY_LIMITED_INFORMATION */, false, pid);
                if (handle != IntPtr.Zero)
                {
                    try
                    {
                        if (GetProcessTimes(handle, out var criacao, out _, out var kernel, out var usuario))
                        {
                            var inicio = DateTime.FromFileTimeUtc(criacao);
                            var tempoCpu = kernel + usuario;
                            GetProcessIoCounters(handle, out var io);
                            var bytesIo = io.ReadTransferCount + io.WriteTransferCount;
                            if (_anterior.TryGetValue(pid, out var antes) && antes.inicio == inicio)
                            {
                                var segundos = Math.Max(0.1, (agora - antes.quando).TotalSeconds);
                                cpu = Math.Clamp((tempoCpu - antes.cpu100ns) / 10_000_000d / segundos / nucleos * 100, 0, 100);
                                discoKBs = bytesIo >= antes.io ? (bytesIo - antes.io) / 1024d / segundos : 0;
                            }
                            _anterior[pid] = (tempoCpu, bytesIo, agora, inicio);
                        }
                        if (!_caminhos.ContainsKey(pid))
                        {
                            var texto = new System.Text.StringBuilder(1024);
                            var tamanho = (uint)texto.Capacity;
                            _caminhos[pid] = QueryFullProcessImageName(handle, 0, texto, ref tamanho) ? texto.ToString() : null;
                        }
                    }
                    finally
                    {
                        CloseHandle(handle);
                    }
                }

                lista.Add(new LinhaProcesso(pid, pid == 0 ? "Ocioso do sistema" : p.ProcessName, _caminhos.GetValueOrDefault(pid), cpu, memoriaMb, discoKBs));
            }
        }

        foreach (var morto in _anterior.Keys.Where(k => !vistos.Contains(k)).ToList()) { _anterior.Remove(morto); _caminhos.Remove(morto); }
        return lista;
    }

    private static double Valor(IntPtr contador)
    {
        if (contador == IntPtr.Zero) return 0;
        return PdhGetFormattedCounterValue(contador, 0x00000200 | 0x00008000 /* DOUBLE | NOCAP100 */, IntPtr.Zero, out var v) == 0 && v.Status == 0 ? v.Valor : 0;
    }

    public void Dispose()
    {
        if (_consulta != IntPtr.Zero) PdhCloseQuery(_consulta);
        _consulta = IntPtr.Zero;
    }

    [StructLayout(LayoutKind.Explicit)]
    private struct PdhValor
    {
        [FieldOffset(0)] public uint Status;
        [FieldOffset(8)] public double Valor;
    }

    [StructLayout(LayoutKind.Sequential)]
    private struct MemoryStatusEx
    {
        public uint dwLength, dwMemoryLoad;
        public ulong ullTotalPhys, ullAvailPhys, ullTotalPageFile, ullAvailPageFile, ullTotalVirtual, ullAvailVirtual, ullAvailExtendedVirtual;
    }

    [StructLayout(LayoutKind.Sequential)]
    private struct IoCounters
    {
        public ulong ReadOperationCount, WriteOperationCount, OtherOperationCount, ReadTransferCount, WriteTransferCount, OtherTransferCount;
    }

    [DllImport("pdh.dll", CharSet = CharSet.Unicode)] private static extern int PdhOpenQuery(string? fonte, IntPtr usuario, out IntPtr consulta);
    [DllImport("pdh.dll", CharSet = CharSet.Unicode)] private static extern int PdhAddEnglishCounter(IntPtr consulta, string caminho, IntPtr usuario, out IntPtr contador);
    [DllImport("pdh.dll")] private static extern int PdhCollectQueryData(IntPtr consulta);
    [DllImport("pdh.dll")] private static extern int PdhGetFormattedCounterValue(IntPtr contador, uint formato, IntPtr tipo, out PdhValor valor);
    [DllImport("pdh.dll")] private static extern int PdhCloseQuery(IntPtr consulta);
    [DllImport("kernel32.dll")] private static extern bool GlobalMemoryStatusEx(ref MemoryStatusEx estado);
    [DllImport("kernel32.dll")] private static extern IntPtr OpenProcess(uint acesso, bool herdar, int pid);
    [DllImport("kernel32.dll")] private static extern bool CloseHandle(IntPtr handle);
    [DllImport("kernel32.dll")] private static extern bool GetProcessTimes(IntPtr handle, out long criacao, out long saida, out long kernel, out long usuario);
    [DllImport("kernel32.dll")] private static extern bool GetProcessIoCounters(IntPtr handle, out IoCounters io);
    [DllImport("kernel32.dll", CharSet = CharSet.Unicode)] private static extern bool QueryFullProcessImageName(IntPtr handle, uint flags, System.Text.StringBuilder texto, ref uint tamanho);
}

/// <summary>Gráfico de linha (últimos N pontos) no estilo do Gerenciador de Tarefas, com até duas séries.</summary>
public sealed class GraficoDesempenho : Control
{
    private const int Pontos = 60;
    private readonly Queue<double> _serieA = new();
    private readonly Queue<double> _serieB = new();
    private readonly Color _corA;
    private readonly Color? _corB;
    private readonly string _titulo;
    private string _valor = "--";
    private string _detalhe = "";
    private double _maximoFixo;

    public GraficoDesempenho(string titulo, Color corA, Color? corB = null, double maximoFixo = 0)
    {
        _titulo = titulo;
        _corA = corA;
        _corB = corB;
        _maximoFixo = maximoFixo;
        DoubleBuffered = true;
        SetStyle(ControlStyles.AllPaintingInWmPaint | ControlStyles.UserPaint | ControlStyles.OptimizedDoubleBuffer | ControlStyles.ResizeRedraw, true);
        BackColor = Tema.Fundo;
        Margin = Padding.Empty;
    }

    public void Adicionar(double a, double b, string valor, string detalhe)
    {
        _serieA.Enqueue(a);
        if (_corB != null) _serieB.Enqueue(b);
        while (_serieA.Count > Pontos) _serieA.Dequeue();
        while (_serieB.Count > Pontos) _serieB.Dequeue();
        _valor = valor;
        _detalhe = detalhe;
        Invalidate();
    }

    protected override void OnPaint(PaintEventArgs e)
    {
        var g = e.Graphics;
        g.SmoothingMode = SmoothingMode.AntiAlias;
        g.TextRenderingHint = System.Drawing.Text.TextRenderingHint.ClearTypeGridFit;
        g.Clear(Tema.Fundo);

        // Cartão: vidro escuro arredondado, borda com brilho da cor da série.
        var cartao = new Rectangle(0, 0, Width - 9, Height - 9);
        using (var forma = Tema.Arredondado(cartao, 12))
        {
            using var fundo = new LinearGradientBrush(cartao, Color.FromArgb(30, 37, 49), Color.FromArgb(17, 21, 28), LinearGradientMode.Vertical);
            g.FillPath(fundo, forma);
            using var borda = new LinearGradientBrush(cartao, Color.FromArgb(150, _corA), Color.FromArgb(25, _corA), LinearGradientMode.ForwardDiagonal);
            using var caneta = new Pen(borda, 1.2F);
            g.DrawPath(caneta, forma);
        }
        // Filete de luz no topo, como painel de instrumento.
        using (var filete = new LinearGradientBrush(new Rectangle(14, 0, Math.Max(1, cartao.Width - 28), 2), Color.FromArgb(0, _corA), Color.FromArgb(220, _corA), LinearGradientMode.Horizontal))
        {
            filete.SetBlendTriangularShape(0.5F);
            g.FillRectangle(filete, 14, 1, cartao.Width - 28, 2);
        }

        using var fonteTitulo = Tema.FonteSemibold(8F);
        using var fonteValor = new Font("Segoe UI Light", 22F);
        using var fonteDetalhe = Tema.Fonte(8F);
        // Indicador "ao vivo" ao lado do título.
        using (var ponto = new SolidBrush(_corA)) g.FillEllipse(ponto, 14, 14, 7, 7);
        using (var halo = new SolidBrush(Color.FromArgb(60, _corA))) g.FillEllipse(halo, 11, 11, 13, 13);
        TextRenderer.DrawText(g, _titulo, fonteTitulo, new Point(30, 9), Color.FromArgb(170, 182, 196));
        TextRenderer.DrawText(g, _valor, fonteValor, new Point(10, 24), Color.FromArgb(240, 246, 252));
        TextRenderer.DrawText(g, _detalhe, fonteDetalhe, new Rectangle(14, 64, cartao.Width - 28, 16), Tema.TextoSecundario, TextFormatFlags.EndEllipsis);

        var area = new Rectangle(14, 86, cartao.Width - 28, Math.Max(10, cartao.Height - 98));
        using (var grade = new Pen(Color.FromArgb(28, 255, 255, 255)) { DashStyle = DashStyle.Dot })
        {
            for (var i = 0; i <= 4; i++) g.DrawLine(grade, area.Left, area.Top + area.Height * i / 4, area.Right, area.Top + area.Height * i / 4);
            for (var i = 1; i < 6; i++) g.DrawLine(grade, area.Left + area.Width * i / 6, area.Top, area.Left + area.Width * i / 6, area.Bottom);
        }

        var maximo = _maximoFixo > 0 ? _maximoFixo : Math.Max(1, Math.Max(_serieA.DefaultIfEmpty().Max(), _serieB.DefaultIfEmpty().Max()) * 1.2);
        if (_corB is { } corB) Desenhar(g, area, _serieB, maximo, corB, false);
        Desenhar(g, area, _serieA, maximo, _corA, true);
        if (_maximoFixo <= 0)
        {
            TextRenderer.DrawText(g, Escala(maximo), fonteDetalhe, new Rectangle(area.Right - 90, area.Top - 15, 90, 14), Tema.TextoApagado, TextFormatFlags.Right);
        }
    }

    private string Escala(double maximo) => _titulo.StartsWith("REDE") ? $"{maximo:0.#} Mbps" : _titulo.StartsWith("DISCO") ? $"{maximo:0.#} MB/s" : $"{maximo:0}";

    /// <summary>Linha "neon": halo largo translúcido + linha fina; área com degradê que some até a base; ponto atual brilhando.</summary>
    private static void Desenhar(Graphics g, Rectangle area, Queue<double> serie, double maximo, Color cor, bool preencher)
    {
        if (serie.Count < 2) return;
        var valores = serie.ToArray();
        var passo = area.Width / (float)(Pontos - 1);
        var inicioX = area.Right - passo * (valores.Length - 1);
        var pontos = valores.Select((v, i) => new PointF(inicioX + i * passo, area.Bottom - (float)(Math.Min(v, maximo) / maximo * area.Height))).ToArray();

        if (preencher)
        {
            using var caminho = new GraphicsPath();
            caminho.AddCurve(pontos, 0.35F);
            caminho.AddLine(pontos[^1], new PointF(pontos[^1].X, area.Bottom));
            caminho.AddLine(new PointF(pontos[^1].X, area.Bottom), new PointF(pontos[0].X, area.Bottom));
            caminho.CloseFigure();
            using var degrade = new LinearGradientBrush(new RectangleF(area.X, area.Y - 1, area.Width, area.Height + 2), Color.FromArgb(95, cor), Color.FromArgb(0, cor), LinearGradientMode.Vertical);
            g.FillPath(degrade, caminho);
        }

        using (var halo = new Pen(Color.FromArgb(preencher ? 55 : 35, cor), 6F) { LineJoin = LineJoin.Round })
            g.DrawCurve(halo, pontos, 0.35F);
        using (var linha = new Pen(cor, preencher ? 2F : 1.5F) { LineJoin = LineJoin.Round })
            g.DrawCurve(linha, pontos, 0.35F);

        var atual = pontos[^1];
        using (var brilho = new SolidBrush(Color.FromArgb(70, cor))) g.FillEllipse(brilho, atual.X - 7, atual.Y - 7, 14, 14);
        using (var centro = new SolidBrush(Color.FromArgb(245, 250, 255))) g.FillEllipse(centro, atual.X - 2.5F, atual.Y - 2.5F, 5, 5);
    }
}

/// <summary>Histórico (24 h / 7 dias) com eixo de horário -- várias séries no mesmo gráfico, estilo painel.</summary>
public sealed class GraficoHistorico : Control
{
    public sealed record Serie(string Nome, Color Cor, List<(DateTime t, double v)> Pontos, bool Preencher);

    private List<Serie> _series = new();
    private double _maximo = 100;
    private string _unidade = "%";
    private string _vazio = "";

    public GraficoHistorico()
    {
        DoubleBuffered = true;
        SetStyle(ControlStyles.AllPaintingInWmPaint | ControlStyles.UserPaint | ControlStyles.OptimizedDoubleBuffer | ControlStyles.ResizeRedraw, true);
    }

    public void Definir(List<Serie> series, double maximo, string unidade, string vazio)
    {
        _series = series;
        _maximo = Math.Max(1, maximo);
        _unidade = unidade;
        _vazio = vazio;
        Invalidate();
    }

    protected override void OnPaint(PaintEventArgs e)
    {
        var g = e.Graphics;
        g.SmoothingMode = SmoothingMode.AntiAlias;
        g.Clear(Tema.Fundo);
        var cartao = new Rectangle(0, 0, Width - 1, Height - 1);
        using (var forma = Tema.Arredondado(cartao, 12))
        {
            using var fundo = new LinearGradientBrush(cartao, Color.FromArgb(28, 34, 46), Color.FromArgb(16, 20, 27), LinearGradientMode.Vertical);
            g.FillPath(fundo, forma);
            using var borda = new Pen(Color.FromArgb(60, Tema.Acento));
            g.DrawPath(borda, forma);
        }

        using var fonte = Tema.Fonte(8F);
        var area = new Rectangle(54, 34, Width - 78, Height - 66);
        // Legenda
        var x = area.Left;
        foreach (var s in _series)
        {
            using var b = new SolidBrush(s.Cor);
            g.FillEllipse(b, x, 13, 8, 8);
            TextRenderer.DrawText(g, s.Nome, fonte, new Point(x + 12, 9), Tema.TextoSecundario);
            x += TextRenderer.MeasureText(s.Nome, fonte).Width + 30;
        }

        var todos = _series.SelectMany(s => s.Pontos).ToList();
        if (todos.Count < 2)
        {
            TextRenderer.DrawText(g, _vazio, Tema.Fonte(10F), area, Tema.TextoSecundario, TextFormatFlags.HorizontalCenter | TextFormatFlags.VerticalCenter | TextFormatFlags.WordBreak);
            return;
        }
        var inicio = todos.Min(p => p.t);
        var fim = todos.Max(p => p.t);
        var largura = Math.Max(1, (fim - inicio).TotalSeconds);

        using (var grade = new Pen(Color.FromArgb(26, 255, 255, 255)) { DashStyle = DashStyle.Dot })
        {
            for (var i = 0; i <= 4; i++)
            {
                var y = area.Top + area.Height * i / 4;
                g.DrawLine(grade, area.Left, y, area.Right, y);
                TextRenderer.DrawText(g, $"{_maximo * (4 - i) / 4:0.#}{_unidade}", fonte, new Rectangle(0, y - 8, 50, 16), Tema.TextoApagado, TextFormatFlags.Right);
            }
            var marcas = 6;
            for (var i = 0; i <= marcas; i++)
            {
                var px = area.Left + area.Width * i / marcas;
                g.DrawLine(grade, px, area.Top, px, area.Bottom);
                var t = inicio.AddSeconds(largura * i / marcas);
                var texto = largura > 2 * 86400 ? t.ToString("dd/MM HH'h'") : t.ToString("HH:mm");
                TextRenderer.DrawText(g, texto, fonte, new Rectangle(px - 40, area.Bottom + 4, 80, 16), Tema.TextoApagado, TextFormatFlags.HorizontalCenter);
            }
        }

        foreach (var s in _series)
        {
            if (s.Pontos.Count < 2) continue;
            var pontos = s.Pontos.Select(p => new PointF(area.Left + (float)((p.t - inicio).TotalSeconds / largura * area.Width),
                area.Bottom - (float)(Math.Min(p.v, _maximo) / _maximo * area.Height))).ToArray();
            if (s.Preencher)
            {
                using var caminho = new GraphicsPath();
                caminho.AddLines(pontos);
                caminho.AddLine(pontos[^1], new PointF(pontos[^1].X, area.Bottom));
                caminho.AddLine(new PointF(pontos[^1].X, area.Bottom), new PointF(pontos[0].X, area.Bottom));
                caminho.CloseFigure();
                using var degrade = new LinearGradientBrush(new RectangleF(area.X, area.Y - 1, area.Width, area.Height + 2), Color.FromArgb(70, s.Cor), Color.FromArgb(0, s.Cor), LinearGradientMode.Vertical);
                g.FillPath(degrade, caminho);
            }
            using var halo = new Pen(Color.FromArgb(45, s.Cor), 5F) { LineJoin = LineJoin.Round };
            g.DrawLines(halo, pontos);
            using var linha = new Pen(s.Cor, 1.6F) { LineJoin = LineJoin.Round };
            g.DrawLines(linha, pontos);
        }
    }
}

/// <summary>
/// Página "Desempenho": como o Gerenciador de Tarefas/Monitor de
/// Desempenho, mais o que eles não têm -- histórico e quem causou cada pico.
/// Abas: Agora (gráficos ao vivo + processos), Picos (episódios com
/// responsável e software), Histórico (24 h / 7 dias), Programas (quem mais
/// consumiu CPU, memória, disco e rede) e Rede (tráfego por IP).
/// "Agora" só mede com a página aberta; o resto vem da coleta CONTÍNUA
/// (chave no topo, ou ligada pelo portal), que roda em segundo plano.
/// </summary>
public sealed class DesempenhoPagina : Panel
{
    private readonly MonitorDesempenhoService _monitor;
    private readonly GraficoDesempenho _cpu, _memoria, _disco, _rede;
    private readonly Label _resumo;
    private readonly SeletorSegmentado _ordem;
    private readonly TextBox _filtro;
    private readonly ListView _lista;
    private readonly System.Windows.Forms.Timer _timer;
    private readonly InterruptorModerno _chave;
    private readonly Label _estadoColeta;
    private readonly SeletorSegmentado _abas;
    private readonly Panel _painelAgora, _painelOutros;
    private readonly FlowLayoutPanel _barraOutros;
    private readonly Label _statusOutros;
    private readonly ListView _listaOutros;
    private readonly TextBox _detalheOutros;
    private readonly GraficoHistorico _historico;
    private MedidorDesempenho? _medidor;
    private bool _medindo;
    private int _tick;
    private string _aba = "agora";
    private string _periodo = "24h";
    private bool _ajustandoChave;

    public DesempenhoPagina(MonitorDesempenhoService monitor)
    {
        _monitor = monitor;
        Dock = DockStyle.Fill;
        BackColor = Tema.Fundo;
        Visible = false;

        var titulo = new Panel { Height = 68, BackColor = Tema.Fundo };
        titulo.Controls.Add(new Label { Text = "Desempenho", Location = new Point(0, 0), Size = new Size(400, 34), ForeColor = Tema.Texto, Font = Tema.FonteSemibold(19F) });
        titulo.Controls.Add(new Label { Text = "Ao vivo, histórico e quem causou cada pico de CPU, memória, disco e rede.", Location = new Point(0, 38), Size = new Size(620, 24), ForeColor = Tema.TextoSecundario, Font = Tema.Fonte(9F) });
        _chave = new InterruptorModerno("Coleta contínua", monitor.Ativo) { Width = 190, Anchor = AnchorStyles.Top | AnchorStyles.Right };
        _estadoColeta = new Label { Width = 300, Height = 20, ForeColor = Tema.TextoSecundario, TextAlign = ContentAlignment.MiddleRight, Anchor = AnchorStyles.Top | AnchorStyles.Right };
        titulo.Controls.Add(_chave);
        titulo.Controls.Add(_estadoColeta);
        titulo.Resize += (s, e) =>
        {
            _chave.Location = new Point(titulo.Width - _chave.Width, 0);
            _estadoColeta.Location = new Point(titulo.Width - _estadoColeta.Width, 42);
        };
        _chave.Alterado += (s, e) =>
        {
            if (_ajustandoChave) return;
            if (!_chave.Ligado && _monitor.LigadoServidor)
            {
                MessageBox.Show(FindForm(), "A coleta contínua desta máquina foi ligada pelo portal (Ativos > máquina > Desempenho). Para desligar, desligue lá.", "RD Intranet - Desempenho", MessageBoxButtons.OK, MessageBoxIcon.Information);
                _ajustandoChave = true; _chave.Ligado = true; _ajustandoChave = false;
                return;
            }
            _monitor.DefinirLocal(_chave.Ligado);
            AtualizarEstadoColeta();
            if (_aba != "agora") MontarAba();
        };

        _abas = new SeletorSegmentado { Height = 40 };
        _abas.DefinirOpcoes(new[]
        {
            ("agora", "Agora", Tema.Acento), ("picos", "Picos", Tema.Acento), ("historico", "Histórico", Tema.Acento),
            ("programas", "Programas", Tema.Acento), ("rede", "Rede (IPs)", Tema.Acento)
        }, "agora");
        _abas.SelecaoAlterada += (s, e) => { _aba = _abas.ChaveSelecionada ?? "agora"; MontarAba(); };

        // ---------- Agora ----------
        _painelAgora = new Panel { Dock = DockStyle.Fill, BackColor = Tema.Fundo };
        var grade = new TableLayoutPanel { Height = 196, ColumnCount = 4, RowCount = 1, BackColor = Tema.Fundo };
        for (var i = 0; i < 4; i++) grade.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 25F));
        _cpu = new GraficoDesempenho("CPU", Tema.Acento, maximoFixo: 100) { Dock = DockStyle.Fill };
        _memoria = new GraficoDesempenho("MEMÓRIA", Color.FromArgb(188, 140, 255), maximoFixo: 100) { Dock = DockStyle.Fill };
        _disco = new GraficoDesempenho("DISCO (leitura / gravação)", Tema.Sucesso, Tema.Alerta) { Dock = DockStyle.Fill };
        _rede = new GraficoDesempenho("REDE (recebe / envia)", Tema.Ciano, Color.FromArgb(240, 136, 62)) { Dock = DockStyle.Fill };
        grade.Controls.Add(_cpu, 0, 0);
        grade.Controls.Add(_memoria, 1, 0);
        grade.Controls.Add(_disco, 2, 0);
        grade.Controls.Add(_rede, 3, 0);
        _resumo = new Label { Height = 26, ForeColor = Tema.TextoSecundario, TextAlign = ContentAlignment.MiddleLeft, AutoEllipsis = true };
        var barra = new FlowLayoutPanel { Height = 46, BackColor = Tema.Fundo, WrapContents = false, Padding = new Padding(0, 4, 0, 0) };
        _filtro = new TextBox { Width = 180, BorderStyle = BorderStyle.FixedSingle, BackColor = Tema.Campo, ForeColor = Tema.Texto, PlaceholderText = "Filtrar processo...", Margin = new Padding(0, 8, 10, 0) };
        _ordem = new SeletorSegmentado { Width = 330, Height = 38, Margin = new Padding(0, 0, 10, 0) };
        _ordem.DefinirOpcoes(new[] { ("cpu", "CPU", Tema.Acento), ("memoria", "Memória", Tema.Acento), ("disco", "Disco", Tema.Acento) }, "cpu");
        _ordem.SelecaoAlterada += (s, e) => _lista.ListViewItemSorter = null; // o seletor volta a mandar na ordem
        barra.Controls.AddRange(new Control[] { _filtro, _ordem, Botao("Encerrar processo", BotaoTema.Variante.Perigo, () => Encerrar(_lista)), Botao("Abrir local", BotaoTema.Variante.Secundario, () => AbrirLocal(_lista)) });
        _lista = MainForm.CriarLista(new[] { ("Processo", 220), ("PID", 70), ("CPU", 75), ("Memória", 95), ("Disco", 95), ("Software", 220), ("Caminho", 360) });
        _lista.MultiSelect = false;
        _lista.Dock = DockStyle.Fill;
        _painelAgora.Controls.Add(_lista);
        foreach (var bloco in new Control[] { barra, _resumo, grade }) { bloco.Dock = DockStyle.Top; _painelAgora.Controls.Add(bloco); }

        // ---------- Picos / Histórico / Programas / Rede ----------
        _painelOutros = new Panel { Dock = DockStyle.Fill, BackColor = Tema.Fundo, Visible = false };
        _barraOutros = new FlowLayoutPanel { Dock = DockStyle.Top, Height = 46, BackColor = Tema.Fundo, WrapContents = false, Padding = new Padding(0, 6, 0, 0) };
        _statusOutros = new Label { Dock = DockStyle.Top, Height = 24, ForeColor = Tema.TextoSecundario, TextAlign = ContentAlignment.MiddleLeft, AutoEllipsis = true };
        _listaOutros = MainForm.CriarLista(new[] { ("", 10) });
        _listaOutros.MultiSelect = false;
        _listaOutros.Dock = DockStyle.Fill;
        _listaOutros.SelectedIndexChanged += (s, e) => DetalheOutros();
        _detalheOutros = new TextBox { Dock = DockStyle.Bottom, Height = 150, Multiline = true, ReadOnly = true, ScrollBars = ScrollBars.Vertical, BackColor = Tema.Superficie, ForeColor = Tema.Texto, BorderStyle = BorderStyle.None, Font = Tema.FonteMono(9F) };
        _historico = new GraficoHistorico { Dock = DockStyle.Fill, Visible = false };
        _painelOutros.Controls.Add(_listaOutros);
        _painelOutros.Controls.Add(_historico);
        _painelOutros.Controls.Add(_detalheOutros);
        _painelOutros.Controls.Add(_statusOutros);
        _painelOutros.Controls.Add(_barraOutros);

        Controls.Add(_painelAgora);
        Controls.Add(_painelOutros);
        foreach (var bloco in new Control[] { Espaco(8), _abas, Espaco(8), titulo })
        {
            bloco.Dock = DockStyle.Top;
            Controls.Add(bloco);
        }

        _timer = new System.Windows.Forms.Timer { Interval = 2000 };
        _timer.Tick += (s, e) => { if (_aba == "agora") Medir(); AtualizarEstadoColeta(); };
        VisibleChanged += (s, e) =>
        {
            if (Visible && FindForm()?.Visible == true) { _medidor ??= new MedidorDesempenho(); _timer.Start(); AtualizarEstadoColeta(); if (_aba == "agora") Medir(); else MontarAba(); }
            else _timer.Stop();
        };
    }

    private static Panel Espaco(int altura) => new() { Height = altura, BackColor = Tema.Fundo };

    /// <summary>Troca de aba por código (modo de teste com prints).</summary>
    internal void SelecionarAba(string aba)
    {
        _abas.DefinirOpcoes(new[]
        {
            ("agora", "Agora", Tema.Acento), ("picos", "Picos", Tema.Acento), ("historico", "Histórico", Tema.Acento),
            ("programas", "Programas", Tema.Acento), ("rede", "Rede (IPs)", Tema.Acento)
        }, aba);
        _aba = aba;
        MontarAba();
        AtualizarEstadoColeta();
    }

    private BotaoTema Botao(string texto, BotaoTema.Variante variante, Action acao)
    {
        var botao = new BotaoTema(texto, variante) { Width = TextRenderer.MeasureText(texto, Tema.FonteSemibold(9.5F)).Width + 28, Margin = new Padding(0, 2, 6, 0) };
        botao.Click += (s, e) => acao();
        return botao;
    }

    private void AtualizarEstadoColeta()
    {
        _ajustandoChave = true;
        _chave.Ligado = _monitor.Ativo;
        _ajustandoChave = false;
        _estadoColeta.Text = !_monitor.Ativo ? "desligada · histórico e picos parados"
            : $"{(_monitor.LigadoServidor ? "ligada pelo portal" : "ligada nesta máquina")} · a cada {_monitor.IntervaloSegundos} s";
        _estadoColeta.ForeColor = _monitor.Ativo ? Tema.Sucesso : Tema.TextoSecundario;
    }

    // ------------------------------------------------------------------
    // Agora
    // ------------------------------------------------------------------

    private void Medir()
    {
        if (_medindo || _medidor == null || !Visible) return;
        _medindo = true;
        var medidor = _medidor;
        var comProcessos = _tick++ % 2 == 0 || _lista.Items.Count == 0;
        Task.Run(() => (medidor.Medir(), comProcessos ? medidor.Processos() : null)).ContinueWith(t =>
        {
            if (IsDisposed || !IsHandleCreated) return;
            BeginInvoke(() =>
            {
                _medindo = false;
                if (t.IsFaulted) return;
                var (a, processos) = t.Result;
                var memoriaPct = a.MemoriaTotalGb > 0 ? a.MemoriaUsadaGb / a.MemoriaTotalGb * 100 : 0;
                _cpu.Adicionar(a.Cpu, 0, $"{a.Cpu:0}%", $"{Environment.ProcessorCount} processadores lógicos");
                _memoria.Adicionar(memoriaPct, 0, $"{memoriaPct:0}%", $"{a.MemoriaUsadaGb:0.0} de {a.MemoriaTotalGb:0.0} GB em uso");
                _disco.Adicionar(a.DiscoLeituraMBs, a.DiscoEscritaMBs, $"{a.DiscoAtivo:0}% ativo", $"lê {a.DiscoLeituraMBs:0.0} MB/s · grava {a.DiscoEscritaMBs:0.0} MB/s");
                _rede.Adicionar(a.RedeRecebeMbps, a.RedeEnviaMbps, $"{a.RedeRecebeMbps + a.RedeEnviaMbps:0.0} Mbps", $"recebe {a.RedeRecebeMbps:0.0} · envia {a.RedeEnviaMbps:0.0} Mbps");
                var ligado = TimeSpan.FromMilliseconds(Environment.TickCount64);
                _resumo.Text = $"{a.Processos} processos  ·  {a.Threads:N0} threads  ·  ligado há {(int)ligado.TotalDays}d {ligado.Hours:00}h{ligado.Minutes:00}  ·  atualiza a cada 2 s enquanto esta aba está aberta";
                if (processos != null) PreencherProcessos(processos);
            });
        });
    }

    private void PreencherProcessos(List<MedidorDesempenho.LinhaProcesso> processos)
    {
        var filtro = _filtro.Text.Trim();
        var ordenados = (_ordem.ChaveSelecionada switch
        {
            "memoria" => processos.OrderByDescending(p => p.MemoriaMb),
            "disco" => processos.OrderByDescending(p => p.DiscoKBs),
            _ => processos.OrderByDescending(p => p.Cpu)
        }).Where(p => p.Pid != 0 && (filtro == "" || p.Nome.Contains(filtro, StringComparison.OrdinalIgnoreCase) || (p.Caminho?.Contains(filtro, StringComparison.OrdinalIgnoreCase) ?? false)))
          .Take(80).ToList();

        var selecionado = _lista.SelectedItems.Count > 0 ? (_lista.SelectedItems[0].Tag as MedidorDesempenho.LinhaProcesso)?.Pid : null;
        _lista.BeginUpdate();
        _lista.Items.Clear();
        foreach (var p in ordenados)
        {
            var item = new ListViewItem(p.Nome) { Tag = p, ForeColor = p.Cpu >= 50 ? Tema.Perigo : p.Cpu >= 15 ? Tema.Alerta : Tema.Texto };
            item.SubItems.Add(p.Pid.ToString());
            item.SubItems.Add($"{p.Cpu:0.0}%");
            item.SubItems.Add(p.MemoriaMb >= 1024 ? $"{p.MemoriaMb / 1024:0.00} GB" : $"{p.MemoriaMb:0} MB");
            item.SubItems.Add(p.DiscoKBs >= 1024 ? $"{p.DiscoKBs / 1024:0.0} MB/s" : $"{p.DiscoKBs:0} KB/s");
            item.SubItems.Add(_monitor.Software(p.Caminho) ?? "");
            item.SubItems.Add(p.Caminho ?? "");
            _lista.Items.Add(item);
            if (p.Pid == selecionado) item.Selected = true;
        }
        _lista.EndUpdate();
        _lista.PerformLayout();
    }

    // ------------------------------------------------------------------
    // Picos / Histórico / Programas / Rede
    // ------------------------------------------------------------------

    private void MontarAba()
    {
        _painelAgora.Visible = _aba == "agora";
        _painelOutros.Visible = _aba != "agora";
        if (_aba == "agora") { Medir(); return; }

        _barraOutros.Controls.Clear();
        _listaOutros.Items.Clear();
        _listaOutros.Columns.Clear();
        _listaOutros.ListViewItemSorter = null;
        _detalheOutros.Text = "";
        var historico = _aba == "historico";
        _historico.Visible = historico;
        _listaOutros.Visible = !historico;
        _detalheOutros.Visible = !historico;

        if (!_monitor.Ativo && _monitor.Picos().Count == 0 && _monitor.Minutos(TimeSpan.FromDays(7)).Count == 0)
        {
            _statusOutros.Text = "Ligue a \"Coleta contínua\" (no topo, ou no portal) para registrar histórico, picos e quem consumiu. Ela roda em segundo plano, mesmo com esta janela fechada.";
            _statusOutros.ForeColor = Tema.Alerta;
            return;
        }
        _statusOutros.ForeColor = Tema.TextoSecundario;
        var periodo = _periodo == "7d" ? TimeSpan.FromDays(7) : TimeSpan.FromHours(24);

        if (_aba != "picos")
        {
            var seletor = new SeletorSegmentado { Width = 200, Height = 36, Margin = new Padding(0, 0, 10, 0) };
            seletor.DefinirOpcoes(new[] { ("24h", "24 horas", Tema.Acento), ("7d", "7 dias", Tema.Acento) }, _periodo);
            seletor.SelecaoAlterada += (s, e) => { _periodo = seletor.ChaveSelecionada ?? "24h"; MontarAba(); };
            _barraOutros.Controls.Add(seletor);
        }
        _barraOutros.Controls.Add(Botao("Atualizar", BotaoTema.Variante.Secundario, MontarAba));

        switch (_aba)
        {
            case "picos":
                Colunas(("Quando", 120), ("Métrica", 80), ("Duração", 80), ("Máximo", 85), ("Média", 75), ("Principal responsável", 200), ("Software", 260));
                var picos = _monitor.Picos();
                foreach (var p in picos)
                {
                    var r = p.Responsaveis.FirstOrDefault();
                    var item = new ListViewItem(p.Inicio.ToString("dd/MM HH:mm:ss")) { Tag = p, ForeColor = p.Fim == null ? Tema.Perigo : Tema.Alerta };
                    item.SubItems.Add(MonitorDesempenhoService.Rotulo(p.Metrica).ToUpperInvariant());
                    item.SubItems.Add(Duracao(p.DuracaoSegundos) + (p.Fim == null ? " (agora)" : ""));
                    item.SubItems.Add($"{p.Maximo:0.#} {p.Unidade}");
                    item.SubItems.Add($"{p.Media:0.#} {p.Unidade}");
                    item.SubItems.Add(r != null ? $"{r.Nome} ({r.Valor:0.#} {r.Unidade})" : "—");
                    item.SubItems.Add(r?.Software ?? "");
                    _listaOutros.Items.Add(item);
                }
                var r0 = _monitor.Regras;
                _statusOutros.Text = $"{picos.Count} pico(s) registrado(s). Pico = CPU ≥ {r0.CpuPico:0}%, memória ≥ {r0.MemoriaPico:0}%, disco ≥ {r0.DiscoPico:0}% ativo ou rede ≥ {r0.RedePicoPercentual:0}% do link, por {r0.DuracaoMinimaSegundos} s ou mais. Clique para ver quem causou.";
                _barraOutros.Controls.Add(Botao("Encerrar responsável", BotaoTema.Variante.Perigo, () =>
                {
                    if (_listaOutros.SelectedItems.Count > 0 && _listaOutros.SelectedItems[0].Tag is MonitorDesempenhoService.Pico p && p.Responsaveis.FirstOrDefault() is { } resp)
                        EncerrarPid(resp.Pid, resp.Nome);
                }));
                break;

            case "historico":
                var minutos = _monitor.Minutos(periodo);
                var extensao = minutos.Count > 0 ? minutos.Max(m => m.T) - minutos.Min(m => m.T) : 0;
                var bloco = extensao < 2 * 3600 ? 60L : _periodo == "7d" ? 3600L : 300L; // pouco dado: ponto por minuto
                var agregados = minutos.GroupBy(m => m.T / bloco * bloco).OrderBy(g => g.Key).Select(g => (t: DateTimeOffset.FromUnixTimeSeconds(g.Key).LocalDateTime, g)).ToList();
                List<(DateTime, double)> Serie(Func<IGrouping<long, MonitorDesempenhoService.Minuto>, double> f) => agregados.Select(x => (x.t, f(x.g))).ToList();
                _historico.Definir(new List<GraficoHistorico.Serie>
                {
                    new("CPU média", Tema.Acento, Serie(g => g.Average(m => m.Cpu)), true),
                    new("CPU máxima", Color.FromArgb(120, Tema.Acento), Serie(g => g.Max(m => m.CpuMax)), false),
                    new("Memória", Color.FromArgb(188, 140, 255), Serie(g => g.Average(m => m.Memoria)), false),
                    new("Disco ativo", Tema.Sucesso, Serie(g => g.Average(m => m.Disco)), false)
                }, 100, "%", "Ainda sem histórico: a coleta contínua começou agora. Volte em alguns minutos.");
                _statusOutros.Text = minutos.Count == 0 ? "" : $"CPU média {minutos.Average(m => m.Cpu):0}% (máx {minutos.Max(m => m.CpuMax):0}%) · memória média {minutos.Average(m => m.Memoria):0}% (máx {minutos.Max(m => m.MemoriaMax):0}%) · disco médio {minutos.Average(m => m.Disco):0}% · rede máx {minutos.Max(m => m.RedeMax):0.0} Mbps";
                break;

            case "programas":
                Colunas(("Programa", 180), ("Software", 230), ("Tempo de CPU", 105), ("Memória máx.", 105), ("Disco", 95), ("Rede", 95), ("Caminho", 360));
                var (programas, _) = _monitor.Consumo(periodo);
                foreach (var p in programas.OrderByDescending(p => p.CpuSegundos).Take(200))
                {
                    var item = new ListViewItem(p.Nome) { Tag = p };
                    item.SubItems.Add(p.Software ?? "");
                    item.SubItems.Add(p.CpuSegundos >= 3600 ? $"{p.CpuSegundos / 3600:0.0} h" : p.CpuSegundos >= 60 ? $"{p.CpuSegundos / 60:0.0} min" : $"{p.CpuSegundos:0} s");
                    item.SubItems.Add(p.MemoriaMaxMb >= 1024 ? $"{p.MemoriaMaxMb / 1024:0.00} GB" : $"{p.MemoriaMaxMb:0} MB");
                    item.SubItems.Add(Tamanho(p.DiscoMb));
                    item.SubItems.Add(Tamanho(p.RedeMb));
                    item.SubItems.Add(p.Caminho ?? "");
                    _listaOutros.Items.Add(item);
                }
                _statusOutros.Text = $"Consumo somado {(_periodo == "7d" ? "nos últimos 7 dias" : "nas últimas 24 horas")}. Clique no cabeçalho para ordenar por memória, disco ou rede.";
                break;

            case "rede":
                Colunas(("IP remoto", 140), ("Recebido", 95), ("Enviado", 95), ("Conexões máx.", 105), ("Portas", 120), ("Programas", 380));
                var (_, ips) = _monitor.Consumo(periodo);
                foreach (var i in ips.OrderByDescending(i => i.RecebidoMb + i.EnviadoMb).Take(200))
                {
                    var item = new ListViewItem(i.Ip) { Tag = i, ForeColor = TabelaTcp.EhPublico(IPAddress.TryParse(i.Ip, out var a) ? a : IPAddress.Loopback) ? Tema.Texto : Tema.TextoSecundario };
                    item.SubItems.Add(Tamanho(i.RecebidoMb));
                    item.SubItems.Add(Tamanho(i.EnviadoMb));
                    item.SubItems.Add(i.ConexoesMax.ToString());
                    item.SubItems.Add(string.Join(", ", i.Portas));
                    item.SubItems.Add(string.Join(", ", i.Programas));
                    _listaOutros.Items.Add(item);
                }
                _statusOutros.Text = "Tráfego por IP medido nas conexões TCP IPv4 (estatística do próprio Windows). IPs da rede local em cinza.";
                _barraOutros.Controls.Add(Botao("Consultar IP", BotaoTema.Variante.Secundario, () =>
                {
                    if (_listaOutros.SelectedItems.Count > 0 && _listaOutros.SelectedItems[0].Tag is MonitorDesempenhoService.ConsumoIp ip)
                        try { Process.Start(new ProcessStartInfo($"https://www.abuseipdb.com/check/{ip.Ip}") { UseShellExecute = true }); } catch { }
                }));
                break;
        }
        _listaOutros.PerformLayout();
    }

    private void Colunas(params (string titulo, int largura)[] colunas)
    {
        foreach (var (t, l) in colunas) _listaOutros.Columns.Add(t, l);
    }

    private void DetalheOutros()
    {
        if (_listaOutros.SelectedItems.Count == 0) { _detalheOutros.Text = ""; return; }
        var t = new System.Text.StringBuilder();
        switch (_listaOutros.SelectedItems[0].Tag)
        {
            case MonitorDesempenhoService.Pico p:
                t.AppendLine($"Pico de {MonitorDesempenhoService.Rotulo(p.Metrica)}: {p.Inicio:dd/MM/yyyy HH:mm:ss} até {(p.Fim is { } f ? f.ToString("HH:mm:ss") : "agora")} ({Duracao(p.DuracaoSegundos)}) · máximo {p.Maximo:0.#} {p.Unidade} · média {p.Media:0.#} {p.Unidade}");
                t.AppendLine();
                t.AppendLine("Quem mais consumia no momento do máximo:");
                foreach (var r in p.Responsaveis) t.AppendLine($"  • {r.Nome} (PID {r.Pid}) -- {r.Valor:0.#} {r.Unidade}{(r.Software != null ? $" -- {r.Software}" : "")}\n    {r.Caminho}");
                if (p.Ips.Count > 0)
                {
                    t.AppendLine();
                    t.AppendLine("IPs com mais tráfego no momento:");
                    foreach (var i in p.Ips) t.AppendLine($"  • {i.Ip}  recebido {Tamanho(i.RecebidoMb)} · enviado {Tamanho(i.EnviadoMb)} · portas {string.Join(", ", i.Portas)}");
                }
                break;
            case MonitorDesempenhoService.ConsumoPrograma c:
                t.AppendLine($"{c.Nome} -- {c.Software}");
                t.AppendLine(c.Caminho);
                t.AppendLine($"Tempo de CPU {c.CpuSegundos / 60:0.0} min · memória máxima {c.MemoriaMaxMb:0} MB · disco {Tamanho(c.DiscoMb)} · rede {Tamanho(c.RedeMb)}");
                break;
            case MonitorDesempenhoService.ConsumoIp i:
                t.AppendLine($"{i.Ip} -- recebido {Tamanho(i.RecebidoMb)} · enviado {Tamanho(i.EnviadoMb)} · até {i.ConexoesMax} conexões ao mesmo tempo");
                t.AppendLine($"Portas: {string.Join(", ", i.Portas)}");
                t.AppendLine($"Programas: {string.Join(", ", i.Programas)}");
                t.AppendLine();
                t.AppendLine("\"Consultar IP\" abre a reputação no AbuseIPDB.");
                break;
        }
        _detalheOutros.Text = t.ToString();
    }

    private static string Tamanho(double mb) => mb >= 1024 ? $"{mb / 1024:0.00} GB" : mb >= 1 ? $"{mb:0.0} MB" : $"{mb * 1024:0} KB";
    private static string Duracao(int segundos) => segundos >= 3600 ? $"{segundos / 3600}h{segundos % 3600 / 60:00}min" : segundos >= 60 ? $"{segundos / 60} min {segundos % 60:00} s" : $"{segundos} s";

    // ------------------------------------------------------------------

    private void Encerrar(ListView lista)
    {
        if (lista.SelectedItems.Count == 0 || lista.SelectedItems[0].Tag is not MedidorDesempenho.LinhaProcesso p) return;
        EncerrarPid(p.Pid, p.Nome);
    }

    private void EncerrarPid(int pid, string nome)
    {
        if (pid <= 4 || pid == Environment.ProcessId) return;
        if (MessageBox.Show(FindForm(), $"Encerrar {nome} (PID {pid})?\n\nO que não foi salvo nesse programa se perde.", "RD Intranet - Desempenho",
                MessageBoxButtons.YesNo, MessageBoxIcon.Warning, MessageBoxDefaultButton.Button2) != DialogResult.Yes) return;
        try
        {
            Process.GetProcessById(pid).Kill(true);
            LogAtividade.Registrar(NivelAtividade.Aviso, "DESEMPENHO", $"Processo encerrado pela tela Desempenho: {nome} (PID {pid})");
        }
        catch (Exception ex)
        {
            MessageBox.Show(FindForm(), "Não foi possível encerrar: " + ex.Message, "RD Intranet", MessageBoxButtons.OK, MessageBoxIcon.Error);
        }
    }

    private static void AbrirLocal(ListView lista)
    {
        if (lista.SelectedItems.Count == 0 || lista.SelectedItems[0].Tag is not MedidorDesempenho.LinhaProcesso { Caminho: { } caminho }) return;
        if (File.Exists(caminho)) Process.Start("explorer.exe", $"/select,\"{caminho}\"");
    }

    protected override void Dispose(bool disposing)
    {
        if (disposing)
        {
            _timer.Dispose();
            _medidor?.Dispose();
        }
        base.Dispose(disposing);
    }
}
