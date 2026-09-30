using System.Drawing;
using System.Drawing.Drawing2D;
using System.ServiceProcess;
using System.Text.Json;
using System.Windows.Forms;

namespace RdIntranetAgente;

public sealed class MainForm : Form
{
    private readonly AppState _estado;
    private readonly SegurancaService _seguranca;
    private readonly Func<int> _heartbeatIntervalo;
    private readonly Func<Task> _coletar;
    private readonly Func<Task> _atualizar;
    private readonly Func<Config> _configAtual;
    private readonly Action<Config> _aplicarConfig;
    private readonly Func<Task> _alternarServico;
    private readonly System.Windows.Forms.Timer _timer;
    private readonly Panel _conteudo;
    private readonly Panel _navegacao;
    private readonly Panel _paginaVisao;
    private readonly Panel _paginaSeguranca;
    private readonly Panel _paginaAtividade;
    private readonly Panel _paginaNetwork;
    private readonly Panel _paginaConfig;
    private readonly ChamadosPagina _paginaChamados;
    private readonly Button _botaoChamados;
    private readonly Panel _hostConfig;
    private readonly Button _botaoConfig;
    private ConfigForm? _formConfig;
    private readonly PilulaStatus _statusConexao;
    private readonly CartaoInfo _cartaoAtivo;
    private readonly CartaoInfo _cartaoCheckin;
    private readonly CartaoInfo _cartaoHeartbeat;
    private readonly CartaoInfo _cartaoTrafego;
    private readonly CartaoInfo _cartaoServico;
    private readonly Label _avisoIsolamento;
    private readonly Label _statusCanary;
    private readonly Label _statusFim;
    private readonly Label _statusShadow;
    private readonly ListView _listaEventos;
    private readonly ListView _listaAtividade;
    private readonly ListView _listaAdaptadoresRede;
    private readonly CheckBox _redeDhcpLocal;
    private readonly TextBox _redeIpLocal;
    private readonly TextBox _redeMascaraLocal;
    private readonly TextBox _redeGatewayLocal;
    private readonly TextBox _redeDnsLocal;
    private readonly TextBox _redePingLocal;
    private readonly TextBox _redeTraceLocal;
    private readonly Label _redeStatusLocal;
    private readonly RichTextBox _redePingResultado;
    private readonly RichTextBox _redeSpeedResultado;
    private readonly RichTextBox _redeTraceResultado;
    private readonly Button _redeBotaoReverter;
    private string _assinaturaEventos = "";

    public MainForm(
        AppState estado,
        SegurancaService seguranca,
        Func<int> heartbeatIntervalo,
        Func<Task> coletar,
        Func<Task> atualizar,
        Func<Config> configAtual,
        Action<Config> aplicarConfig,
        Func<Task> alternarServico)
    {
        _estado = estado;
        _seguranca = seguranca;
        _heartbeatIntervalo = heartbeatIntervalo;
        _coletar = coletar;
        _atualizar = atualizar;
        _configAtual = configAtual;
        _aplicarConfig = aplicarConfig;
        _alternarServico = alternarServico;

        SuspendLayout();
        AutoScaleDimensions = new SizeF(96F, 96F);
        AutoScaleMode = AutoScaleMode.Dpi;

        Text = "RD Intranet - Agente";
        StartPosition = FormStartPosition.CenterScreen;
        MinimumSize = new Size(780, 540);
        Size = new Size(980, 680);
        BackColor = Tema.Fundo;
        ForeColor = Tema.Texto;
        Font = Tema.Fonte(9F);
        try { Icon = Icon.ExtractAssociatedIcon(Application.ExecutablePath); } catch { }
        Tema.BarraTituloEscura(this);

        _navegacao = new Panel { Dock = DockStyle.Left, Width = 176, BackColor = Tema.Superficie };
        var marca = new Label
        {
            Text = "RD INTRANET\nAGENTE",
            Left = 20,
            Top = 23,
            Width = 140,
            Height = 52,
            ForeColor = Tema.Texto,
            Font = Tema.FonteSemibold(12F)
        };
        _navegacao.Controls.Add(marca);

        var botaoVisao = CriarBotaoNavegacao("Visão geral", 100);
        _botaoChamados = CriarBotaoNavegacao("Chamados", 145);
        var botaoSeguranca = CriarBotaoNavegacao("Segurança", 190);
        var botaoAtividade = CriarBotaoNavegacao("Atividade", 235);
        _botaoConfig = CriarBotaoNavegacao("Configurações", 280);
        var botaoNetwork = CriarBotaoNavegacao("Network", 325);
        botaoVisao.Click += (s, e) => MostrarPagina(_paginaVisao!, botaoVisao);
        _botaoChamados.Click += async (s, e) => await AbrirChamadosAsync();
        botaoSeguranca.Click += (s, e) => MostrarPagina(_paginaSeguranca!, botaoSeguranca);
        botaoAtividade.Click += (s, e) => MostrarPagina(_paginaAtividade!, botaoAtividade);
        _botaoConfig.Click += (s, e) => AbrirConfiguracoes();
        botaoNetwork.Click += async (s, e) =>
        {
            MostrarPagina(_paginaNetwork!, botaoNetwork);
            await AtualizarAdaptadoresRedeAsync();
        };
        _navegacao.Controls.AddRange(new Control[] { botaoVisao, _botaoChamados, botaoSeguranca, botaoAtividade, _botaoConfig, botaoNetwork });

        var versao = new Label
        {
            Text = $"Versão {Tema.Versao()}",
            Dock = DockStyle.Bottom,
            Height = 36,
            Padding = new Padding(20, 0, 0, 12),
            ForeColor = Tema.TextoSecundario
        };
        _navegacao.Controls.Add(versao);

        _conteudo = new Panel { Dock = DockStyle.Fill, BackColor = Tema.Fundo, Padding = new Padding(24) };
        Controls.Add(_conteudo);
        Controls.Add(_navegacao);

        _paginaVisao = CriarPagina();
        _paginaVisao.AutoScroll = true; // janela pequena: os botões de ação descem e continuam alcançáveis
        var tituloVisao = CriarTitulo("Visão geral", "Condição atual do agente nesta máquina.");
        _statusConexao = new PilulaStatus { Location = new Point(0, 6) };
        var linhaStatus = new Panel { Height = 44, BackColor = Tema.Fundo };
        linhaStatus.Controls.Add(_statusConexao);
        _cartaoAtivo = new CartaoInfo { Height = 84 };
        var grade = new TableLayoutPanel
        {
            Location = new Point(0, 122),
            Size = new Size(720, 208),
            Anchor = AnchorStyles.Top | AnchorStyles.Left | AnchorStyles.Right,
            ColumnCount = 2,
            RowCount = 2,
            BackColor = Tema.Fundo,
            Padding = Padding.Empty
        };
        grade.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 50));
        grade.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 50));
        grade.RowStyles.Add(new RowStyle(SizeType.Absolute, 100));
        grade.RowStyles.Add(new RowStyle(SizeType.Absolute, 100));
        _cartaoCheckin = new CartaoInfo { Dock = DockStyle.Fill };
        _cartaoHeartbeat = new CartaoInfo { Dock = DockStyle.Fill };
        _cartaoTrafego = new CartaoInfo { Dock = DockStyle.Fill };
        _cartaoServico = new CartaoInfo { Dock = DockStyle.Fill };
        grade.Controls.Add(_cartaoCheckin, 0, 0);
        grade.Controls.Add(_cartaoHeartbeat, 1, 0);
        grade.Controls.Add(_cartaoTrafego, 0, 1);
        grade.Controls.Add(_cartaoServico, 1, 1);

        var acoes = new FlowLayoutPanel
        {
            Location = new Point(0, 354),
            Size = new Size(720, 42),
            Anchor = AnchorStyles.Top | AnchorStyles.Left | AnchorStyles.Right,
            BackColor = Tema.Fundo,
            WrapContents = true,
            AutoSize = true,
            AutoSizeMode = AutoSizeMode.GrowAndShrink
        };
        acoes.Controls.Add(CriarBotaoAcao("Pedir ajuda", BotaoTema.Variante.Primario, async () => await PedidoAjuda.AbrirAsync(this, _configAtual())));
        acoes.Controls.Add(CriarBotaoAcao("Abrir chamado", BotaoTema.Variante.Primario, async () => await AbrirChamadosAsync()));
        acoes.Controls.Add(CriarBotaoAcao("Coletar agora", BotaoTema.Variante.Secundario, async () => await _coletar()));
        acoes.Controls.Add(CriarBotaoAcao("Atualizar agora", BotaoTema.Variante.Secundario, async () => await _atualizar()));
        acoes.Controls.Add(CriarBotaoAcao("Configurações", BotaoTema.Variante.Secundario, () => AbrirConfiguracoes()));
        acoes.Controls.Add(CriarBotaoAcao("Serviço do Windows", BotaoTema.Variante.Secundario, async () => await _alternarServico()));
        Empilhar(_paginaVisao, null, tituloVisao, linhaStatus, _cartaoAtivo, Espaco(10), grade, Espaco(16), acoes);

        _paginaSeguranca = CriarPagina();
        var tituloSeguranca = CriarTitulo("Segurança", "Detectores locais e eventos reportados ao servidor.");
        _avisoIsolamento = new Label
        {
            Location = new Point(0, 80),
            Size = new Size(720, 42),
            Anchor = AnchorStyles.Top | AnchorStyles.Left | AnchorStyles.Right,
            BackColor = Color.FromArgb(54, Tema.Perigo),
            ForeColor = Tema.Perigo,
            Font = Tema.FonteSemibold(9F),
            TextAlign = ContentAlignment.MiddleLeft,
            Padding = new Padding(12),
            Visible = false
        };
        var detectores = new TableLayoutPanel
        {
            Location = new Point(0, 132),
            Size = new Size(720, 80),
            Anchor = AnchorStyles.Top | AnchorStyles.Left | AnchorStyles.Right,
            ColumnCount = 3,
            RowCount = 1,
            BackColor = Tema.Fundo
        };
        for (var coluna = 0; coluna < 3; coluna++) detectores.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 33.333F));
        _statusCanary = CriarStatusDetector();
        _statusFim = CriarStatusDetector();
        _statusShadow = CriarStatusDetector();
        detectores.Controls.Add(_statusCanary, 0, 0);
        detectores.Controls.Add(_statusFim, 1, 0);
        detectores.Controls.Add(_statusShadow, 2, 0);
        var rotuloEventos = CriarLegenda("EVENTOS DETECTADOS", 224);
        _listaEventos = CriarLista(new[] { ("Quando", 145), ("Severidade", 95), ("Detecção", 450) });
        _listaEventos.Location = new Point(0, 250);
        _listaEventos.Size = new Size(720, 340);
        _listaEventos.Anchor = AnchorStyles.Top | AnchorStyles.Bottom | AnchorStyles.Left | AnchorStyles.Right;
        Empilhar(_paginaSeguranca, _listaEventos, tituloSeguranca, _avisoIsolamento, Espaco(10), detectores, Espaco(14), rotuloEventos);

        _paginaAtividade = CriarPagina();
        var tituloAtividade = CriarTitulo("Atividade", "Registro recente do que o agente executou.");
        _listaAtividade = CriarLista(new[] { ("Quando", 145), ("Nível", 85), ("Categoria", 115), ("Registro", 430) });
        _listaAtividade.Location = new Point(0, 86);
        _listaAtividade.Size = new Size(720, 500);
        _listaAtividade.Anchor = AnchorStyles.Top | AnchorStyles.Bottom | AnchorStyles.Left | AnchorStyles.Right;
        Empilhar(_paginaAtividade, _listaAtividade, tituloAtividade, Espaco(8));

        _paginaNetwork = CriarPagina();
        var tituloNetwork = CriarTitulo("Network", "Adaptadores, TCP/IP e diagnósticos desta máquina.");
        var networkContent = new Panel { Dock = DockStyle.Fill, AutoScroll = true, BackColor = Tema.Fundo };
        var networkStack = new FlowLayoutPanel
        {
            FlowDirection = FlowDirection.TopDown,
            WrapContents = false,
            AutoSize = true,
            AutoSizeMode = AutoSizeMode.GrowAndShrink,
            Location = Point.Empty,
            Width = 720,
            BackColor = Tema.Fundo,
            Margin = Padding.Empty,
            Padding = Padding.Empty
        };
        networkContent.Controls.Add(networkStack);

        _redeStatusLocal = new Label
        {
            Text = "Selecione um adaptador para consultar os valores atuais.",
            ForeColor = Tema.TextoSecundario,
            Height = 28,
            AutoEllipsis = true,
            TextAlign = ContentAlignment.MiddleLeft
        };
        var acoesNetwork = new FlowLayoutPanel { AutoSize = true, WrapContents = true, BackColor = Tema.Fundo, Margin = new Padding(0, 0, 0, 8) };
        acoesNetwork.Controls.Add(CriarBotaoAcao("Atualizar adaptadores", BotaoTema.Variante.Secundario, async () => await AtualizarAdaptadoresRedeAsync()));
        _redeBotaoReverter = CriarBotaoAcao("Reverter última alteração", BotaoTema.Variante.Perigo, async () => await ReverterRedeLocalAsync());
        _redeBotaoReverter.Enabled = false;
        acoesNetwork.Controls.Add(_redeBotaoReverter);

        _listaAdaptadoresRede = CriarLista(new[]
        {
            ("Adaptador", 145), ("Link", 58), ("Ativo", 54), ("MAC", 125), ("IPv4", 105), ("Modo", 68), ("Wi-Fi", 110)
        });
        Tema.TemaEscuroNativo(_listaAdaptadoresRede);
        _listaAdaptadoresRede.Height = 148;
        _listaAdaptadoresRede.Margin = new Padding(0, 0, 0, 10);
        _listaAdaptadoresRede.MultiSelect = false;
        _listaAdaptadoresRede.SelectedIndexChanged += (s, e) => SelecionarAdaptadorRedeLocal();
        _listaAdaptadoresRede.KeyDown += (s, e) =>
        {
            if (e.Control && e.KeyCode == Keys.C)
            {
                CopiarAdaptadorSelecionado();
                e.Handled = true;
            }
        };
        var menuAdaptador = new ContextMenuStrip { Renderer = new RenderizadorMenuEscuro() };
        menuAdaptador.Items.Add("Copiar linha", null, (s, e) => CopiarAdaptadorSelecionado());
        _listaAdaptadoresRede.ContextMenuStrip = menuAdaptador;

        var cartaoConfiguracaoRede = new CartaoRedePanel { Height = 228, Margin = new Padding(0, 0, 0, 10) };
        var formularioRede = new TableLayoutPanel
        {
            Dock = DockStyle.Fill,
            ColumnCount = 2,
            RowCount = 4,
            BackColor = Tema.Superficie,
            Padding = new Padding(4)
        };
        formularioRede.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 50));
        formularioRede.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 50));
        formularioRede.RowStyles.Add(new RowStyle(SizeType.Absolute, 30));
        formularioRede.RowStyles.Add(new RowStyle(SizeType.Absolute, 58));
        formularioRede.RowStyles.Add(new RowStyle(SizeType.Absolute, 58));
        formularioRede.RowStyles.Add(new RowStyle(SizeType.Percent, 100));
        _redeDhcpLocal = new CheckBox { Text = "Usar DHCP", ForeColor = Tema.Texto, BackColor = Tema.Superficie, AutoSize = true, Anchor = AnchorStyles.Left };
        _redeIpLocal = CriarCampoRede();
        _redeMascaraLocal = CriarCampoRede();
        _redeGatewayLocal = CriarCampoRede();
        _redeDnsLocal = CriarCampoRede();
        formularioRede.Controls.Add(_redeDhcpLocal, 0, 0);
        formularioRede.SetColumnSpan(_redeDhcpLocal, 2);
        formularioRede.Controls.Add(CriarCampoComRotulo("Endereço IPv4", _redeIpLocal), 0, 1);
        formularioRede.Controls.Add(CriarCampoComRotulo("Máscara", _redeMascaraLocal), 1, 1);
        formularioRede.Controls.Add(CriarCampoComRotulo("Gateway", _redeGatewayLocal), 0, 2);
        formularioRede.Controls.Add(CriarCampoComRotulo("DNS (até 2, separados por vírgula)", _redeDnsLocal), 1, 2);
        var botaoAplicarNetwork = CriarBotaoAcao("Salvar e aplicar", BotaoTema.Variante.Primario, async () => await AplicarRedeLocalAsync());
        botaoAplicarNetwork.Dock = DockStyle.Left;
        formularioRede.Controls.Add(botaoAplicarNetwork, 0, 3);
        formularioRede.SetColumnSpan(botaoAplicarNetwork, 2);
        cartaoConfiguracaoRede.Controls.Add(formularioRede);
        _redeDhcpLocal.CheckedChanged += (s, e) => AtualizarCamposRedeLocal();

        var cartoesDiagnostico = new TableLayoutPanel
        {
            ColumnCount = 2,
            RowCount = 2,
            Height = 430,
            BackColor = Tema.Fundo,
            Margin = Padding.Empty,
            Padding = Padding.Empty
        };
        cartoesDiagnostico.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 50));
        cartoesDiagnostico.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 50));
        cartoesDiagnostico.RowStyles.Add(new RowStyle(SizeType.Absolute, 186));
        cartoesDiagnostico.RowStyles.Add(new RowStyle(SizeType.Absolute, 236));
        _redePingLocal = CriarCampoRede();
        _redePingLocal.PlaceholderText = "192.168.0.1";
        _redePingResultado = CriarResultadoRede(62);
        var linhaPing = CriarLinhaDiagnostico(_redePingLocal, CriarBotaoAcao("Ping", BotaoTema.Variante.Secundario, async () => await TestarPingLocalAsync()));
        cartoesDiagnostico.Controls.Add(CriarCartaoDiagnostico("Ping", "Quatro pacotes para um endereço IPv4.", linhaPing, _redePingResultado), 0, 0);

        _redeSpeedResultado = CriarResultadoRede(62);
        var botaoSpeed = CriarBotaoAcao("Testar velocidade", BotaoTema.Variante.Secundario, async () => await TestarVelocidadeLocalAsync());
        botaoSpeed.Dock = DockStyle.Fill;
        cartoesDiagnostico.Controls.Add(CriarCartaoDiagnostico("Velocidade", "Teste Cloudflare · transfere até 9 MB.", botaoSpeed, _redeSpeedResultado), 1, 0);

        _redeTraceLocal = CriarCampoRede();
        _redeTraceLocal.PlaceholderText = "203.0.113.1";
        _redeTraceResultado = CriarResultadoRede(120);
        var linhaTrace = CriarLinhaDiagnostico(_redeTraceLocal, CriarBotaoAcao("Trace route", BotaoTema.Variante.Secundario, async () => await TestarTraceRouteLocalAsync()));
        var cartaoTrace = CriarCartaoDiagnostico("Trace route", "Mostra os saltos até o IP de destino (máximo 12).", linhaTrace, _redeTraceResultado);
        cartoesDiagnostico.Controls.Add(cartaoTrace, 0, 1);
        cartoesDiagnostico.SetColumnSpan(cartaoTrace, 2);

        networkStack.Controls.Add(tituloNetwork);
        networkStack.Controls.Add(_redeStatusLocal);
        networkStack.Controls.Add(acoesNetwork);
        networkStack.Controls.Add(cartaoConfiguracaoRede);
        networkStack.Controls.Add(_listaAdaptadoresRede);
        networkStack.Controls.Add(cartoesDiagnostico);
        void AjustarLargurasNetwork()
        {
            var largura = Math.Max(360, networkContent.ClientSize.Width - SystemInformation.VerticalScrollBarWidth - 8);
            networkStack.Width = largura;
            tituloNetwork.Width = largura;
            _redeStatusLocal.Width = largura;
            acoesNetwork.Width = largura;
            cartaoConfiguracaoRede.Width = largura;
            _listaAdaptadoresRede.Width = largura;
            cartoesDiagnostico.Width = largura;
        }
        networkContent.Resize += (s, e) => AjustarLargurasNetwork();
        AjustarLargurasNetwork();
        _paginaNetwork.Controls.Add(networkContent);

        // Configurações dentro do próprio painel (antes era uma janela à parte):
        // a mesma tela de sempre, embutida -- validação e busca de unidades iguais.
        _paginaConfig = CriarPagina();
        var tituloConfig = CriarTitulo("Configurações", "Conexão com o RD Intranet e preferências desta máquina.");
        _hostConfig = new Panel { BackColor = Tema.Fundo, AutoScroll = true };
        Tema.TemaEscuroNativo(_hostConfig);
        Empilhar(_paginaConfig, _hostConfig, tituloConfig);

        _paginaChamados = new ChamadosPagina(_configAtual);

        _conteudo.Controls.AddRange(new Control[] { _paginaVisao, _paginaChamados, _paginaSeguranca, _paginaAtividade, _paginaNetwork, _paginaConfig });
        MostrarPagina(_paginaVisao, botaoVisao);
        ResumeLayout(false);
        Tema.AplicarRolagemEscura(this);
        PerformLayout();

        _timer = new System.Windows.Forms.Timer { Interval = 1500 };
        _timer.Tick += (s, e) => AtualizarDados();
        _timer.Start();
        LogAtividade.Adicionada += AoAdicionarAtividade;
        FormClosing += AoFecharJanela;
        AtualizarDados();
        AtualizarAtividade();
    }

    public void Abrir()
    {
        if (WindowState == FormWindowState.Minimized) WindowState = FormWindowState.Normal;
        Show();
        Activate();
        AtualizarDados();
        AtualizarAtividade();
    }

    /// <summary>
    /// Empilha os blocos de cima pra baixo ocupando a largura toda (Dock
    /// Top) e deixa "preencher" com o resto da altura. Antes cada bloco
    /// tinha posição/largura fixas com âncora à direita, calculadas contra
    /// o tamanho inicial da página (menor que a janela) -- o conteúdo
    /// passava da borda e aparecia cortado.
    /// </summary>
    private static void Empilhar(Panel pagina, Control? preencher, params Control[] deCimaPraBaixo)
    {
        foreach (var bloco in deCimaPraBaixo)
        {
            bloco.Dock = DockStyle.Top;
            pagina.Controls.Add(bloco);
            bloco.BringToFront();
        }

        if (preencher != null)
        {
            preencher.Dock = DockStyle.Fill;
            pagina.Controls.Add(preencher);
            preencher.BringToFront();
        }
    }

    private static Panel Espaco(int altura) => new() { Height = altura, BackColor = Tema.Fundo };

    /// <summary>Mostra o painel já na página Chamados, no formulário de abertura (menu da bandeja e botão da Visão geral).</summary>
    public async Task AbrirChamadosAsync()
    {
        Abrir();
        MostrarPagina(_paginaChamados, _botaoChamados);
        await _paginaChamados.AoMostrarAsync();
    }

    /// <summary>Abre a área Configurações com os valores atuais (recria a tela a cada abertura).</summary>
    public void AbrirConfiguracoes()
    {
        _formConfig?.Dispose();
        _formConfig = new ConfigForm(_configAtual())
        {
            TopLevel = false,
            FormBorderStyle = FormBorderStyle.None,
            Location = new Point(0, 0)
        };
        _formConfig.Salvo += config =>
        {
            _aplicarConfig(config);
            LogAtividade.Registrar(NivelAtividade.Sucesso, "CONFIG", "Configurações salvas pelo painel.");
            MostrarPagina(_paginaVisao, (Button)_navegacao.Controls[1]);
        };
        _formConfig.Cancelado += () => MostrarPagina(_paginaVisao, (Button)_navegacao.Controls[1]);
        _hostConfig.Controls.Clear();
        _hostConfig.Controls.Add(_formConfig);
        _formConfig.Show();

        Abrir();
        MostrarPagina(_paginaConfig, _botaoConfig);
    }

    private static Panel CriarPagina() => new()
    {
        Dock = DockStyle.Fill,
        BackColor = Tema.Fundo,
        Visible = false
    };

    private static Panel CriarTitulo(string titulo, string subtitulo)
    {
        var painel = new Panel { Height = 68, BackColor = Tema.Fundo };
        painel.Controls.Add(new Label
        {
            Text = titulo,
            Location = new Point(0, 0),
            Size = new Size(600, 34),
            ForeColor = Tema.Texto,
            Font = Tema.FonteSemibold(19F)
        });
        painel.Controls.Add(new Label
        {
            Text = subtitulo,
            Location = new Point(0, 38),
            Size = new Size(690, 24),
            ForeColor = Tema.TextoSecundario,
            Font = Tema.Fonte(9F)
        });
        return painel;
    }

    private static Button CriarBotaoNavegacao(string texto, int top) => new()
    {
        Text = texto,
        TextAlign = ContentAlignment.MiddleLeft,
        FlatStyle = FlatStyle.Flat,
        FlatAppearance = { BorderSize = 0 },
        BackColor = Tema.Superficie,
        ForeColor = Tema.TextoSecundario,
        Font = Tema.FonteSemibold(9F),
        Location = new Point(12, top),
        Size = new Size(152, 36),
        Cursor = Cursors.Hand
    };

    private static BotaoTema CriarBotaoAcao(string texto, BotaoTema.Variante variante, Action executar)
    {
        var botao = new BotaoTema(texto, variante) { Width = 150, Margin = new Padding(0, 0, 8, 8) };
        botao.Click += (s, e) => executar();
        return botao;
    }

    private static TextBox CriarCampoRede()
    {
        var campo = new TextBox { Dock = DockStyle.Fill, Margin = new Padding(3), MinimumSize = new Size(90, 28) };
        Tema.EstilizarCampo(campo);
        return campo;
    }

    private static Panel CriarCampoComRotulo(string texto, Control campo)
    {
        var painel = new Panel { Dock = DockStyle.Fill, Height = 56, BackColor = Tema.Superficie, Padding = new Padding(3) };
        painel.Controls.Add(campo);
        painel.Controls.Add(new Label
        {
            Text = texto,
            Dock = DockStyle.Top,
            Height = 20,
            ForeColor = Tema.TextoSecundario,
            BackColor = Tema.Superficie,
            Font = Tema.Fonte(8F)
        });
        return painel;
    }

    private static CartaoRedePanel CriarCartaoDiagnostico(string titulo, string descricao, Control linhaAcao, RichTextBox resultado)
    {
        var cartao = new CartaoRedePanel { Dock = DockStyle.Fill, Margin = new Padding(6), Padding = new Padding(10) };
        var layout = new TableLayoutPanel
        {
            Dock = DockStyle.Fill,
            ColumnCount = 1,
            RowCount = 4,
            BackColor = Tema.Superficie,
            Margin = Padding.Empty,
            Padding = Padding.Empty
        };
        layout.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100));
        layout.RowStyles.Add(new RowStyle(SizeType.Absolute, 22));
        layout.RowStyles.Add(new RowStyle(SizeType.Absolute, 21));
        layout.RowStyles.Add(new RowStyle(SizeType.Absolute, 38));
        layout.RowStyles.Add(new RowStyle(SizeType.Percent, 100));
        layout.Controls.Add(new Label
        {
            Text = titulo,
            Dock = DockStyle.Fill,
            ForeColor = Tema.Texto,
            BackColor = Tema.Superficie,
            Font = Tema.FonteSemibold(9.5F),
            TextAlign = ContentAlignment.MiddleLeft
        }, 0, 0);
        layout.Controls.Add(new Label
        {
            Text = descricao,
            Dock = DockStyle.Fill,
            ForeColor = Tema.TextoSecundario,
            BackColor = Tema.Superficie,
            Font = Tema.Fonte(8F),
            TextAlign = ContentAlignment.MiddleLeft,
            AutoEllipsis = true
        }, 0, 1);
        linhaAcao.Dock = DockStyle.Fill;
        layout.Controls.Add(linhaAcao, 0, 2);
        resultado.Dock = DockStyle.Fill;
        resultado.Margin = new Padding(0, 5, 0, 0);
        layout.Controls.Add(resultado, 0, 3);
        cartao.Controls.Add(layout);
        return cartao;
    }

    private static TableLayoutPanel CriarLinhaDiagnostico(Control? entrada, BotaoTema botao)
    {
        var linha = new TableLayoutPanel
        {
            Dock = DockStyle.Fill,
            ColumnCount = 2,
            RowCount = 1,
            BackColor = Tema.Superficie,
            Margin = Padding.Empty,
            Padding = Padding.Empty
        };
        linha.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100));
        linha.ColumnStyles.Add(new ColumnStyle(SizeType.Absolute, 112));
        linha.RowStyles.Add(new RowStyle(SizeType.Percent, 100));
        if (entrada != null)
        {
            entrada.Dock = DockStyle.Fill;
            entrada.Margin = new Padding(0, 2, 6, 2);
            linha.Controls.Add(entrada, 0, 0);
        }
        else
        {
            linha.Controls.Add(new Panel { BackColor = Tema.Superficie }, 0, 0);
        }
        botao.Dock = DockStyle.Fill;
        botao.Margin = new Padding(0, 2, 0, 2);
        linha.Controls.Add(botao, 1, 0);
        return linha;
    }

    private static RichTextBox CriarResultadoRede(int alturaMinima) => new()
    {
        ReadOnly = true,
        DetectUrls = false,
        BorderStyle = BorderStyle.None,
        ScrollBars = RichTextBoxScrollBars.Vertical,
        BackColor = Tema.SuperficieElevada,
        ForeColor = Tema.TextoSecundario,
        Font = Tema.FonteMono(8.5F),
        MinimumSize = new Size(0, alturaMinima),
        Text = "Aguardando teste..."
    };

    private void CopiarAdaptadorSelecionado()
    {
        if (_listaAdaptadoresRede.SelectedItems.Count == 0) return;
        try
        {
            var linha = _listaAdaptadoresRede.SelectedItems[0];
            Clipboard.SetText(string.Join("\t", linha.SubItems.Cast<ListViewItem.ListViewSubItem>().Select(subitem => subitem.Text)));
            _redeStatusLocal.Text = "Dados do adaptador copiados para a área de transferência.";
        }
        catch (Exception ex)
        {
            _redeStatusLocal.Text = $"Não foi possível copiar os dados: {ex.Message}";
        }
    }

    private async Task AtualizarAdaptadoresRedeAsync()
    {
        var idSelecionado = _listaAdaptadoresRede.SelectedItems.Count > 0
            ? (_listaAdaptadoresRede.SelectedItems[0].Tag as AdaptadorRedeInfo)?.AdapterId
            : (int?)null;
        _redeStatusLocal.Text = "Consultando adaptadores...";
        try
        {
            var adaptadores = await Task.Run(NetworkService.ListarAdaptadores);
            _listaAdaptadoresRede.BeginUpdate();
            _listaAdaptadoresRede.Items.Clear();
            foreach (var adaptador in adaptadores)
            {
                var item = new ListViewItem(adaptador.Nome) { Tag = adaptador };
                var linkDown = adaptador.Status.Equals("Down", StringComparison.OrdinalIgnoreCase);
                var estado = adaptador.Ativo ? "UP" : linkDown ? "DOWN" : "DESCONHECIDO";
                var corEstado = adaptador.Ativo ? Tema.Sucesso : linkDown ? Tema.Perigo : Tema.TextoSecundario;
                var celulaEstado = item.SubItems.Add(estado);
                celulaEstado.ForeColor = corEstado;
                var celulaAtivo = item.SubItems.Add(adaptador.Ativo ? "Sim" : "Não");
                celulaAtivo.ForeColor = corEstado;
                item.SubItems.Add(adaptador.Mac);
                item.SubItems.Add(adaptador.Ip ?? "—");
                item.SubItems.Add(adaptador.Dhcp ? "DHCP" : "Manual");
                item.SubItems.Add(adaptador.WifiSsid ?? "—");
                _listaAdaptadoresRede.Items.Add(item);
            }
            _listaAdaptadoresRede.EndUpdate();
            var itemSelecionado = idSelecionado.HasValue
                ? _listaAdaptadoresRede.Items.Cast<ListViewItem>().FirstOrDefault(i => (i.Tag as AdaptadorRedeInfo)?.AdapterId == idSelecionado.Value)
                : null;
            if (itemSelecionado != null)
            {
                itemSelecionado.Selected = true;
            }
            else if (_listaAdaptadoresRede.Items.Count > 0)
            {
                _listaAdaptadoresRede.Items[0].Selected = true;
            }
            if (adaptadores.Count > 0) _redeStatusLocal.Text = $"{adaptadores.Count} adaptador(es) TCP/IP encontrado(s).";
            else
            {
                _redeStatusLocal.Text = "Nenhum adaptador com TCP/IP habilitado foi encontrado.";
            }
        }
        catch (Exception ex)
        {
            _redeStatusLocal.Text = $"Falha ao consultar adaptadores: {ex.Message}";
        }
    }

    private void SelecionarAdaptadorRedeLocal()
    {
        if (_listaAdaptadoresRede.SelectedItems.Count == 0 || _listaAdaptadoresRede.SelectedItems[0].Tag is not AdaptadorRedeInfo adaptador)
            return;
        _redeDhcpLocal.Checked = adaptador.Dhcp;
        _redeIpLocal.Text = adaptador.Ip ?? "";
        _redeMascaraLocal.Text = adaptador.Mascara ?? "";
        _redeGatewayLocal.Text = adaptador.Gateway ?? "";
        _redeDnsLocal.Text = string.Join(", ", adaptador.Dns);
        _redeBotaoReverter.Enabled = adaptador.ReversaoDisponivel;
        AtualizarCamposRedeLocal();
    }

    private void AtualizarCamposRedeLocal()
    {
        var habilitado = !_redeDhcpLocal.Checked;
        _redeIpLocal.Enabled = habilitado;
        _redeMascaraLocal.Enabled = habilitado;
        _redeGatewayLocal.Enabled = habilitado;
        _redeDnsLocal.Enabled = habilitado;
    }

    private async Task AplicarRedeLocalAsync()
    {
        if (_listaAdaptadoresRede.SelectedItems.Count == 0 || _listaAdaptadoresRede.SelectedItems[0].Tag is not AdaptadorRedeInfo adaptador)
        {
            _redeStatusLocal.Text = "Selecione um adaptador primeiro.";
            return;
        }
        if (!confirmarMudancaRede("Aplicar a configuração neste adaptador? A conexão pode cair se os dados estiverem incorretos."))
            return;

        var dns = _redeDnsLocal.Text.Split(',', StringSplitOptions.TrimEntries | StringSplitOptions.RemoveEmptyEntries);
        var parametro = JsonSerializer.Serialize(new
        {
            adapter_id = adaptador.AdapterId,
            dhcp = _redeDhcpLocal.Checked,
            ip = _redeIpLocal.Text.Trim(),
            mascara = _redeMascaraLocal.Text.Trim(),
            gateway = _redeGatewayLocal.Text.Trim(),
            dns
        });
        try
        {
            _redeStatusLocal.Text = "Aplicando configuração e salvando snapshot...";
            await Task.Run(() => NetworkService.Aplicar(parametro));
            _redeStatusLocal.Text = "Configuração aplicada. A anterior está salva para reversão.";
            await AtualizarAdaptadoresRedeAsync();
        }
        catch (Exception ex)
        {
            _redeStatusLocal.Text = $"Falha ao aplicar: {ex.Message}";
        }
    }

    private async Task ReverterRedeLocalAsync()
    {
        if (_listaAdaptadoresRede.SelectedItems.Count == 0 || _listaAdaptadoresRede.SelectedItems[0].Tag is not AdaptadorRedeInfo adaptador)
            return;
        if (!confirmarMudancaRede("Restaurar a configuração de rede salva antes da última alteração?")) return;
        try
        {
            _redeStatusLocal.Text = "Restaurando a configuração anterior...";
            await Task.Run(() => NetworkService.Reverter(adaptador.AdapterId.ToString()));
            _redeStatusLocal.Text = "Configuração anterior restaurada.";
            await AtualizarAdaptadoresRedeAsync();
        }
        catch (Exception ex)
        {
            _redeStatusLocal.Text = $"Falha ao reverter: {ex.Message}";
        }
    }

    private async Task TestarPingLocalAsync()
    {
        var ip = _redePingLocal.Text.Trim();
        try
        {
            _redePingResultado.Text = "Testando...";
            var parametro = JsonSerializer.Serialize(new { ip, quantidade = 4 });
            var json = JsonSerializer.Serialize(await Task.Run(() => NetworkService.PingAsync(parametro)));
            using var resultado = JsonDocument.Parse(json);
            var respostas = resultado.RootElement.GetProperty("respostas").EnumerateArray();
            var linhas = respostas.Select((resposta, indice) => resposta.GetProperty("sucesso").GetBoolean()
                ? $"Resposta {indice + 1}: {resposta.GetProperty("tempo_ms").GetInt64()} ms"
                : $"Pacote {indice + 1}: {resposta.GetProperty("status").GetString()}");
            _redePingResultado.Text = string.Join(Environment.NewLine, linhas);
            _redePingResultado.ForeColor = _redePingResultado.Text.Contains("TimedOut", StringComparison.OrdinalIgnoreCase) ? Tema.Alerta : Tema.Texto;
        }
        catch (Exception ex)
        {
            _redePingResultado.Text = ex.Message;
            _redePingResultado.ForeColor = Tema.Perigo;
        }
    }

    private async Task TestarVelocidadeLocalAsync()
    {
        try
        {
            _redeSpeedResultado.Text = "Testando Cloudflare...";
            var json = JsonSerializer.Serialize(await NetworkService.TestarVelocidadeAsync());
            using var resultado = JsonDocument.Parse(json);
            var dados = resultado.RootElement;
            _redeSpeedResultado.Text = $"↓ {dados.GetProperty("download_mbps").GetDouble():0.##} Mbps  ↑ {dados.GetProperty("upload_mbps").GetDouble():0.##} Mbps";
            _redeSpeedResultado.ForeColor = Tema.Sucesso;
        }
        catch (Exception ex)
        {
            _redeSpeedResultado.Text = ex.Message;
            _redeSpeedResultado.ForeColor = Tema.Perigo;
        }
    }

    private async Task TestarTraceRouteLocalAsync()
    {
        try
        {
            _redeTraceResultado.Text = "Rastreando rota...";
            _redeTraceResultado.ForeColor = Tema.TextoSecundario;
            var parametro = JsonSerializer.Serialize(new { ip = _redeTraceLocal.Text.Trim() });
            var json = JsonSerializer.Serialize(await Task.Run(() => NetworkService.TracerouteAsync(parametro)));
            using var resultado = JsonDocument.Parse(json);
            var dados = resultado.RootElement;
            _redeTraceResultado.Text = dados.GetProperty("saida").GetString() ?? "Sem saída do Windows.";
            _redeTraceResultado.ForeColor = dados.GetProperty("timeout").GetBoolean() ? Tema.Alerta : Tema.Texto;
        }
        catch (Exception ex)
        {
            _redeTraceResultado.Text = ex.Message;
            _redeTraceResultado.ForeColor = Tema.Perigo;
        }
    }

    private static bool confirmarMudancaRede(string mensagem) =>
        MessageBox.Show(mensagem, "RD Intranet - Rede", MessageBoxButtons.YesNo, MessageBoxIcon.Warning) == DialogResult.Yes;

    private static Label CriarStatusDetector() => new()
    {
        Dock = DockStyle.Fill,
        BackColor = Tema.Superficie,
        ForeColor = Tema.TextoSecundario,
        Font = Tema.FonteSemibold(9F),
        TextAlign = ContentAlignment.MiddleCenter,
        Margin = new Padding(0, 0, 8, 0)
    };

    private static Label CriarLegenda(string texto, int top) => new()
    {
        Text = texto,
        Location = new Point(0, top),
        Size = new Size(400, 20),
        ForeColor = Tema.Ciano,
        Font = Tema.FonteSemibold(8F)
    };

    private static ListView CriarLista((string titulo, int largura)[] colunas)
    {
        var lista = new ListView
        {
            View = View.Details,
            FullRowSelect = true,
            GridLines = false,
            OwnerDraw = true,
            HideSelection = false,
            BackColor = Tema.Superficie,
            ForeColor = Tema.Texto,
            BorderStyle = BorderStyle.None,
            Font = Tema.Fonte(9F)
        };
        // Sem tema nativo aqui: qualquer tema escuro do Windows faz a lista
        // desenhar uma grade vertical entre as colunas e barra horizontal.
        foreach (var coluna in colunas) lista.Columns.Add(coluna.titulo, coluna.largura);

        // Cabeçalho padrão do Windows é claro e não aceita cor -- desenha
        // escuro na mão; as linhas continuam no desenho padrão.
        lista.DrawColumnHeader += (s, e) =>
        {
            using var fundo = new SolidBrush(Tema.SuperficieElevada);
            using var borda = new Pen(Tema.Borda);
            e.Graphics.FillRectangle(fundo, e.Bounds);
            e.Graphics.DrawLine(borda, e.Bounds.Left, e.Bounds.Bottom - 1, e.Bounds.Right, e.Bounds.Bottom - 1);
            using var fonte = Tema.FonteSemibold(8.25F);
            var area = new Rectangle(e.Bounds.X + 6, e.Bounds.Y, e.Bounds.Width - 8, e.Bounds.Height);
            TextRenderer.DrawText(e.Graphics, e.Header?.Text ?? "", fonte, area, Tema.TextoSecundario,
                TextFormatFlags.Left | TextFormatFlags.VerticalCenter | TextFormatFlags.EndEllipsis);
        };
        // Linhas desenhadas aqui (fundo, seleção, cor do texto) -- o desenho
        // padrão com tema escuro traz uma grade vertical entre as colunas.
        lista.DrawItem += (s, e) => { };
        lista.DrawSubItem += (s, e) =>
        {
            if (e.Item == null || e.SubItem == null) return;
            var selecionado = e.Item.Selected;
            using (var fundo = new SolidBrush(selecionado ? Tema.SuperficieElevada : Tema.Superficie))
            {
                e.Graphics.FillRectangle(fundo, e.Bounds);
            }
            var area = new Rectangle(e.Bounds.X + 6, e.Bounds.Y, Math.Max(0, e.Bounds.Width - 10), e.Bounds.Height);
            var corTexto = e.SubItem.ForeColor.IsEmpty ? e.Item.ForeColor : e.SubItem.ForeColor;
            TextRenderer.DrawText(e.Graphics, e.SubItem.Text, lista.Font, area, corTexto,
                TextFormatFlags.Left | TextFormatFlags.VerticalCenter | TextFormatFlags.EndEllipsis | TextFormatFlags.NoPrefix);
        };

        // A última coluna ocupa o que sobrar -- sem isso, a sobra à direita
        // do cabeçalho ficava como uma "coluna" branca sem sentido.
        void AjustarUltimaColuna()
        {
            if (lista.Columns.Count == 0 || !lista.IsHandleCreated || !lista.Visible) return;
            var outras = 0;
            for (var i = 0; i < lista.Columns.Count - 1; i++) outras += lista.Columns[i].Width;
            // ClientSize já desconta a barra vertical quando ela existe.
            var largura = lista.ClientSize.Width - outras;
            if (largura > 60 && lista.Columns[^1].Width != largura) lista.Columns[^1].Width = largura;
        }
        // Layout cobre redimensionar e também a página aparecer (se a janela
        // mudou de tamanho com a página escondida, o Resize já tinha passado).
        lista.Layout += (s, e) => AjustarUltimaColuna();
        lista.VisibleChanged += (s, e) => AjustarUltimaColuna();
        return lista;
    }

    private void MostrarPagina(Panel pagina, Button botaoSelecionado)
    {
        _paginaVisao.Visible = false;
        _paginaSeguranca.Visible = false;
        _paginaAtividade.Visible = false;
        _paginaNetwork.Visible = false;
        _paginaConfig.Visible = false;
        _paginaChamados.Visible = false;
        pagina.Visible = true;
        pagina.BringToFront();
        foreach (Control controle in _navegacao.Controls)
        {
            if (controle is Button botao)
            {
                botao.BackColor = Tema.Superficie;
                botao.ForeColor = Tema.TextoSecundario;
            }
        }
        botaoSelecionado.BackColor = Tema.SuperficieElevada;
        botaoSelecionado.ForeColor = Tema.Acento;
        AtualizarDados();
    }

    private void AtualizarDados()
    {
        // Janela escondida na bandeja: nada pra desenhar, não gasta CPU
        // nem consulta o serviço do Windows à toa. Abrir() atualiza na hora.
        if (!Visible)
        {
            return;
        }

        var agora = DateTime.Now;
        var heartbeatRecente = _estado.UltimoHeartbeatEm.HasValue &&
            agora - _estado.UltimoHeartbeatEm.Value <= TimeSpan.FromSeconds(Math.Max(10, _heartbeatIntervalo() * 2 + 5));
        var conectado = _estado.UltimoHeartbeatSucesso && heartbeatRecente;
        _statusConexao.Definir(conectado ? "CONECTADO" : "SEM CONEXÃO", conectado ? Tema.Sucesso : Tema.Alerta);
        var codigoAtivo = string.IsNullOrWhiteSpace(_estado.CodigoAtivo) ? "Aguardando identificação do servidor" : _estado.CodigoAtivo;
        var detalheAtivo = string.Join("  ·  ", new[]
        {
            string.IsNullOrWhiteSpace(_estado.NomeAtivo) ? "Nome indisponível" : _estado.NomeAtivo,
            "IP local " + (string.IsNullOrWhiteSpace(_estado.IpAtivo) ? "não informado" : _estado.IpAtivo),
            "IP público " + (string.IsNullOrWhiteSpace(_estado.IpPublico) ? "indisponível" : _estado.IpPublico)
        });
        _cartaoAtivo.Definir("INFORMAÇÕES DO ATIVO", codigoAtivo, detalheAtivo, Tema.Acento);

        var checkin = _estado.UltimoCheckinEm.HasValue ? _estado.UltimoCheckinEm.Value.ToString("dd/MM/yyyy HH:mm:ss") : "Ainda não realizado";
        _cartaoCheckin.Definir("ÚLTIMO CHECKIN", checkin,
            _estado.UltimoCheckinEm.HasValue ? (_estado.UltimoCheckinSucesso ? _estado.UltimaMensagem : "Falha: " + _estado.UltimaMensagem) : "Aguardando primeira coleta",
            _estado.UltimoCheckinSucesso ? Tema.Sucesso : Tema.TextoApagado);
        var heartbeat = _estado.UltimoHeartbeatEm.HasValue ? _estado.UltimoHeartbeatEm.Value.ToString("dd/MM HH:mm:ss") : "Aguardando primeiro envio";
        _cartaoHeartbeat.Definir("HEARTBEAT", heartbeat, _estado.UltimoHeartbeatSucesso ? "Último envio confirmado" : "Último envio sem confirmação",
            _estado.UltimoHeartbeatSucesso ? Tema.Sucesso : Tema.Alerta);
        _cartaoTrafego.Definir("TRÁFEGO TOTAL", $"↑ {FormatarBytes(_estado.TotalBytesEnviados)}  ↓ {FormatarBytes(_estado.TotalBytesRecebidos)}",
            $"Último checkin: ↑ {FormatarBytes(_estado.UltimoEnvioBytes)}  ↓ {FormatarBytes(_estado.UltimoRecebimentoBytes)}", Tema.Ciano);
        var statusServico = ObterStatusServico();
        var servicoTexto = statusServico == null ? "Não instalado" : statusServico == ServiceControllerStatus.Running ? "Em execução" : statusServico.ToString() ?? "Desconhecido";
        _cartaoServico.Definir("SERVIÇO DO WINDOWS", servicoTexto, "RD Intranet - Agente", statusServico == ServiceControllerStatus.Running ? Tema.Sucesso : Tema.TextoSecundario);

        var status = _seguranca.ObterStatus();
        _statusCanary.Text = $"ARQUIVOS-ISCA\n{TextoEstado(status.CanaryAtivo)}  ·  {status.CanaryArquivos} arquivos";
        _statusCanary.ForeColor = status.CanaryAtivo ? Tema.Sucesso : Tema.TextoSecundario;
        _statusFim.Text = $"MUDANÇA EM MASSA\n{TextoEstado(status.FimAtivo)}  ·  {status.FimAlteracoesJanela} na janela";
        _statusFim.ForeColor = status.FimAtivo ? Tema.Sucesso : Tema.TextoSecundario;
        var shadowInfo = status.ShadowContagem.HasValue ? $"{status.ShadowContagem} cópias" : status.ShadowErro ?? "Aguardando leitura";
        _statusShadow.Text = $"SHADOW COPIES\n{TextoEstado(status.ShadowAtivo)}  ·  {shadowInfo}";
        _statusShadow.ForeColor = status.ShadowAtivo ? Tema.Sucesso : Tema.TextoSecundario;
        _avisoIsolamento.Text = "REDE ISOLADA PELA TI  ·  A conectividade desta máquina foi bloqueada.";
        _avisoIsolamento.Visible = status.Configuracao?.Isolado == true;
        AtualizarEventos(status.EventosRecentes);
    }

    private void AtualizarEventos(List<EventoSeguranca> eventos)
    {
        // Só refaz a lista quando algo mudou -- refazer a cada tick fazia
        // a lista piscar e perder seleção e rolagem.
        var assinatura = string.Join("|", eventos.Select(e => $"{e.OcorridoEm.Ticks}:{e.Enviado}"));
        if (assinatura == _assinaturaEventos)
        {
            return;
        }
        _assinaturaEventos = assinatura;

        _listaEventos.BeginUpdate();
        _listaEventos.Items.Clear();
        foreach (var evento in eventos)
        {
            var item = new ListViewItem(evento.OcorridoEm.LocalDateTime.ToString("dd/MM HH:mm:ss"));
            item.SubItems.Add(evento.Severidade);
            item.SubItems.Add(evento.Resumo);
            item.ForeColor = evento.Severidade == "CRITICAL" ? Tema.Perigo : Tema.Texto;
            _listaEventos.Items.Add(item);
        }
        _listaEventos.EndUpdate();
    }

    private void AtualizarAtividade()
    {
        var entradas = LogAtividade.Recentes(300);
        _listaAtividade.BeginUpdate();
        _listaAtividade.Items.Clear();
        foreach (var entrada in entradas)
        {
            var item = new ListViewItem(entrada.Quando.ToString("dd/MM HH:mm:ss"));
            item.SubItems.Add(entrada.Nivel.ToString().ToUpperInvariant());
            item.SubItems.Add(entrada.Categoria);
            item.SubItems.Add(entrada.Mensagem);
            item.ForeColor = entrada.Nivel == NivelAtividade.Erro ? Tema.Perigo : entrada.Nivel == NivelAtividade.Sucesso ? Tema.Sucesso : Tema.Texto;
            _listaAtividade.Items.Add(item);
        }
        _listaAtividade.EndUpdate();
    }

    private void AoAdicionarAtividade(EntradaAtividade entrada)
    {
        if (IsDisposed || !IsHandleCreated || !Visible) return;
        try { BeginInvoke((Action)AtualizarAtividade); } catch { }
    }

    private void AoFecharJanela(object? sender, FormClosingEventArgs e)
    {
        // Só o "X" do usuário vira "esconder". Desligamento/logoff do
        // Windows e Application.Exit precisam fechar de verdade -- cancelar
        // aqui faria o Windows acusar o agente de impedir o desligamento.
        if (e.CloseReason != CloseReason.UserClosing)
        {
            return;
        }

        e.Cancel = true;
        Hide();
    }

    protected override void Dispose(bool disposing)
    {
        if (disposing)
        {
            _timer.Stop();
            _timer.Dispose();
            LogAtividade.Adicionada -= AoAdicionarAtividade;
        }
        base.Dispose(disposing);
    }

    private static ServiceControllerStatus? ObterStatusServico()
    {
        try
        {
            using var controlador = new ServiceController(AgenteServico.NomeServico);
            return controlador.Status;
        }
        catch (InvalidOperationException)
        {
            return null;
        }
    }

    private static string TextoEstado(bool ativo) => ativo ? "ATIVO" : "DESATIVADO";

    private static string FormatarBytes(long bytes)
    {
        string[] unidades = { "B", "KB", "MB", "GB" };
        double valor = bytes;
        var indice = 0;
        while (valor >= 1024 && indice < unidades.Length - 1)
        {
            valor /= 1024;
            indice++;
        }
        return $"{valor:0.#} {unidades[indice]}";
    }

    private sealed class CartaoRedePanel : Panel
    {
        public CartaoRedePanel()
        {
            BackColor = Tema.Superficie;
            Padding = new Padding(12);
            SetStyle(ControlStyles.AllPaintingInWmPaint | ControlStyles.UserPaint | ControlStyles.OptimizedDoubleBuffer | ControlStyles.ResizeRedraw, true);
        }

        protected override void OnPaintBackground(PaintEventArgs e)
        {
            e.Graphics.SmoothingMode = SmoothingMode.AntiAlias;
            e.Graphics.Clear(Parent?.BackColor ?? Tema.Fundo);
            if (Width < 2 || Height < 2) return;
            using var caminho = Tema.Arredondado(new Rectangle(0, 0, Width - 1, Height - 1), 10);
            using var pincel = new SolidBrush(BackColor);
            e.Graphics.FillPath(pincel, caminho);
        }

        protected override void OnPaint(PaintEventArgs e)
        {
            base.OnPaint(e);
            if (Width < 2 || Height < 2) return;
            e.Graphics.SmoothingMode = SmoothingMode.AntiAlias;
            using var caminho = Tema.Arredondado(new Rectangle(0, 0, Width - 1, Height - 1), 10);
            using var borda = new Pen(Tema.BordaSuave);
            e.Graphics.DrawPath(borda, caminho);
        }
    }
}
