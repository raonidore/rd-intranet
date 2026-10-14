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

/// <summary>
/// Página "Desempenho" do painel: como o Gerenciador de Tarefas/Monitor de
/// Desempenho do Windows -- gráficos ao vivo (CPU, memória, disco, rede) dos
/// últimos 2 minutos e os processos que mais consomem, com encerrar e abrir
/// local. Só mede enquanto a página está aberta (não pesa na máquina).
/// </summary>
public sealed class DesempenhoPagina : Panel
{
    private readonly GraficoDesempenho _cpu, _memoria, _disco, _rede;
    private readonly Label _resumo;
    private readonly SeletorSegmentado _ordem;
    private readonly TextBox _filtro;
    private readonly ListView _lista;
    private readonly System.Windows.Forms.Timer _timer;
    private MedidorDesempenho? _medidor;
    private bool _medindo;
    private int _tick;

    public DesempenhoPagina()
    {
        Dock = DockStyle.Fill;
        BackColor = Tema.Fundo;
        Visible = false;

        var titulo = new Panel { Height = 68, BackColor = Tema.Fundo };
        titulo.Controls.Add(new Label { Text = "Desempenho", Location = new Point(0, 0), Size = new Size(600, 34), ForeColor = Tema.Texto, Font = Tema.FonteSemibold(19F) });
        titulo.Controls.Add(new Label { Text = "CPU, memória, disco e rede ao vivo (últimos 2 minutos) e os processos que mais consomem.", Location = new Point(0, 38), Size = new Size(760, 24), ForeColor = Tema.TextoSecundario, Font = Tema.Fonte(9F) });

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
        var encerrar = new BotaoTema("Encerrar processo", BotaoTema.Variante.Perigo) { Width = 150, Margin = new Padding(0, 2, 6, 0) };
        encerrar.Click += (s, e) => Encerrar();
        var abrir = new BotaoTema("Abrir local") { Width = 110, Margin = new Padding(0, 2, 6, 0) };
        abrir.Click += (s, e) => AbrirLocal();
        barra.Controls.AddRange(new Control[] { _filtro, _ordem, encerrar, abrir });

        _lista = MainForm.CriarLista(new[] { ("Processo", 220), ("PID", 70), ("CPU", 75), ("Memória", 95), ("Disco", 95), ("Caminho", 400) });
        _lista.MultiSelect = false;

        foreach (var bloco in new Control[] { titulo, grade, _resumo, barra })
        {
            bloco.Dock = DockStyle.Top;
            Controls.Add(bloco);
            bloco.BringToFront();
        }
        _lista.Dock = DockStyle.Fill;
        Controls.Add(_lista);
        _lista.BringToFront();

        _timer = new System.Windows.Forms.Timer { Interval = 2000 };
        _timer.Tick += (s, e) => Medir();
        VisibleChanged += (s, e) =>
        {
            if (Visible && FindForm()?.Visible == true) { _medidor ??= new MedidorDesempenho(); _timer.Start(); Medir(); }
            else _timer.Stop();
        };
    }

    private void Medir()
    {
        if (_medindo || _medidor == null || !Visible) return;
        _medindo = true;
        var medidor = _medidor;
        var comProcessos = _tick++ % 2 == 0 || _lista.Items.Count == 0; // lista a cada 4 s, gráficos a cada 2 s
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
                _resumo.Text = $"{a.Processos} processos  ·  {a.Threads:N0} threads  ·  ligado há {(int)ligado.TotalDays}d {ligado.Hours:00}h{ligado.Minutes:00}  ·  atualiza a cada 2 s enquanto esta página está aberta";
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
            item.SubItems.Add(p.Caminho ?? "");
            _lista.Items.Add(item);
            if (p.Pid == selecionado) item.Selected = true;
        }
        _lista.EndUpdate();
        _lista.PerformLayout();
    }

    private void Encerrar()
    {
        if (_lista.SelectedItems.Count == 0 || _lista.SelectedItems[0].Tag is not MedidorDesempenho.LinhaProcesso p) return;
        if (MessageBox.Show(FindForm(), $"Encerrar {p.Nome} (PID {p.Pid})?\n\nO que não foi salvo nesse programa se perde.", "RD Intranet - Desempenho",
                MessageBoxButtons.YesNo, MessageBoxIcon.Warning, MessageBoxDefaultButton.Button2) != DialogResult.Yes) return;
        try
        {
            if (p.Pid == Environment.ProcessId) return;
            Process.GetProcessById(p.Pid).Kill(true);
            LogAtividade.Registrar(NivelAtividade.Aviso, "DESEMPENHO", $"Processo encerrado pela tela Desempenho: {p.Nome} (PID {p.Pid}) {p.Caminho}");
        }
        catch (Exception ex)
        {
            MessageBox.Show(FindForm(), "Não foi possível encerrar: " + ex.Message, "RD Intranet", MessageBoxButtons.OK, MessageBoxIcon.Error);
        }
    }

    private void AbrirLocal()
    {
        if (_lista.SelectedItems.Count == 0 || _lista.SelectedItems[0].Tag is not MedidorDesempenho.LinhaProcesso { Caminho: { } caminho }) return;
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
