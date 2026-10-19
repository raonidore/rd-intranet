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
    private readonly SegurancaPagina _paginaSeguranca;
    private readonly Panel _paginaAtividade;
    private readonly NetworkPagina _paginaNetwork;
    private readonly DesempenhoPagina _paginaDesempenho;
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
    private readonly ListView _listaAtividade;
    private readonly SeletorSegmentado _abasAtividade;
    private readonly Label _resumoAtividade;
    private string _abaAtividade = "importante";

    public MainForm(
        AppState estado,
        SegurancaService seguranca,
        Func<int> heartbeatIntervalo,
        Func<Task> coletar,
        Func<Task> atualizar,
        Func<Config> configAtual,
        Action<Config> aplicarConfig,
        Func<Task> alternarServico,
        MonitorRedeService monitorRede,
        MonitorDesempenhoService monitorDesempenho)
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
        var botaoDesempenho = CriarBotaoNavegacao("Desempenho", 370);
        botaoDesempenho.Click += (s, e) => MostrarPagina(_paginaDesempenho!, botaoDesempenho);
        botaoVisao.Click += (s, e) => MostrarPagina(_paginaVisao!, botaoVisao);
        _botaoChamados.Click += async (s, e) => await AbrirChamadosAsync();
        botaoSeguranca.Click += (s, e) => MostrarPagina(_paginaSeguranca!, botaoSeguranca);
        botaoAtividade.Click += (s, e) => MostrarPagina(_paginaAtividade!, botaoAtividade);
        _botaoConfig.Click += (s, e) => AbrirConfiguracoes();
        botaoNetwork.Click += async (s, e) =>
        {
            MostrarPagina(_paginaNetwork!, botaoNetwork);
            await _paginaNetwork!.AtualizarAdaptadoresAsync();
        };
        _navegacao.Controls.AddRange(new Control[] { botaoVisao, _botaoChamados, botaoSeguranca, botaoAtividade, _botaoConfig, botaoNetwork, botaoDesempenho });

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

        // Segurança: página própria (abas Eventos/Processos/Inicialização/Conexões/Firewall/Quarentena).
        _paginaSeguranca = new SegurancaPagina(_seguranca, monitorRede);

        _paginaAtividade = CriarPagina();
        var tituloAtividade = CriarTitulo("Atividade", "Registro recente do que o agente executou.");
        // Importante (padrão) separa a rotina de check-in do que pede atenção: falhas, segurança, trilha, configuração.
        _abasAtividade = new SeletorSegmentado { Height = 40 };
        _abasAtividade.DefinirOpcoes(new[]
        {
            ("importante", "Importante", Tema.Acento), ("conexao", "Check-in e conexão", Tema.Acento), ("tudo", "Tudo", Tema.Acento)
        }, "importante");
        _abasAtividade.SelecaoAlterada += (s, e) => { _abaAtividade = _abasAtividade.ChaveSelecionada ?? "importante"; AtualizarAtividade(); };
        _resumoAtividade = new Label { Height = 28, ForeColor = Tema.TextoSecundario, TextAlign = ContentAlignment.MiddleLeft, AutoEllipsis = true };
        _listaAtividade = CriarLista(new[] { ("Quando", 145), ("Nível", 85), ("Categoria", 115), ("Registro", 430) });
        _listaAtividade.Location = new Point(0, 86);
        _listaAtividade.Size = new Size(720, 500);
        _listaAtividade.Anchor = AnchorStyles.Top | AnchorStyles.Bottom | AnchorStyles.Left | AnchorStyles.Right;
        Empilhar(_paginaAtividade, _listaAtividade, tituloAtividade, Espaco(4), _abasAtividade, _resumoAtividade);

        _paginaNetwork = new NetworkPagina();
        _paginaDesempenho = new DesempenhoPagina(monitorDesempenho);

        // Configurações dentro do próprio painel (antes era uma janela à parte):
        // a mesma tela de sempre, embutida -- validação e busca de unidades iguais.
        _paginaConfig = CriarPagina();
        var tituloConfig = CriarTitulo("Configurações", "Conexão com o RD Intranet e preferências desta máquina.");
        _hostConfig = new Panel { BackColor = Tema.Fundo, AutoScroll = true };
        Tema.TemaEscuroNativo(_hostConfig);
        Empilhar(_paginaConfig, _hostConfig, tituloConfig);

        _paginaChamados = new ChamadosPagina(_configAtual);

        _conteudo.Controls.AddRange(new Control[] { _paginaVisao, _paginaChamados, _paginaSeguranca, _paginaAtividade, _paginaNetwork, _paginaConfig, _paginaDesempenho });
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

    /// <summary>
    /// Tempo desde o último boot completo -- mesma conta do Gerenciador de
    /// Tarefas (com "inicialização rápida", desligar não zera; reiniciar zera).
    /// </summary>
    private static string UptimeTexto()
    {
        var ligadoHa = TimeSpan.FromMilliseconds(Environment.TickCount64);
        var dias = (int)ligadoHa.TotalDays;
        var horas = $"{ligadoHa.Hours:00}:{ligadoHa.Minutes:00}";
        return dias >= 1 ? $"{dias} dia{(dias == 1 ? "" : "s")}, {horas}" : $"{horas} h";
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
            Dock = DockStyle.Fill
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


    private static Label CriarLegenda(string texto, int top) => new()
    {
        Text = texto,
        Location = new Point(0, top),
        Size = new Size(400, 20),
        ForeColor = Tema.Ciano,
        Font = Tema.FonteSemibold(8F)
    };

    internal static ListView CriarLista((string titulo, int largura)[] colunas)
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
        // Tema escuro nativo só pelas barras de rolagem (sem ele ficavam brancas).
        // A grade vertical que o tema desenhava entre as colunas não aparece mais
        // porque as linhas são desenhadas à mão (DrawSubItem abaixo).
        Tema.TemaEscuroNativo(lista);
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
            var ordenada = lista.ListViewItemSorter is ComparadorLista c && c.Coluna == e.ColumnIndex;
            var seta = ordenada ? (((ComparadorLista)lista.ListViewItemSorter!).Crescente ? "  ▲" : "  ▼") : "";
            TextRenderer.DrawText(e.Graphics, (e.Header?.Text ?? "") + seta, fonte, area, ordenada ? Tema.Acento : Tema.TextoSecundario,
                TextFormatFlags.Left | TextFormatFlags.VerticalCenter | TextFormatFlags.EndEllipsis);
        };

        // Clique no cabeçalho ordena (de novo inverte). Fica valendo nas
        // atualizações automáticas: a lista se reordena sozinha ao receber itens.
        lista.ColumnClick += (s, e) =>
        {
            var atual = lista.ListViewItemSorter as ComparadorLista;
            lista.ListViewItemSorter = new ComparadorLista(e.Column, atual?.Coluna == e.Column ? !atual.Crescente : true);
            lista.Sort();
            lista.Invalidate();
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
        _paginaDesempenho.Visible = false;
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

        // Foco pra dentro da página (como Chamados já fazia): o Windows entrega a
        // rodinha ao controle com foco -- se ele ficar no botão do menu lateral,
        // a página não rola.
        if (IsHandleCreated)
        {
            BeginInvoke(() =>
            {
                if (pagina.Visible && !pagina.ContainsFocus) pagina.SelectNextControl(null, true, true, true, false);
            });
        }
    }

    /// <summary>
    /// Rede de segurança da rolagem: rodinha que subiu até a janela (ex.: veio do
    /// menu lateral, que não rola) vai pra área com rolagem da página visível.
    /// </summary>
    protected override void OnMouseWheel(MouseEventArgs e)
    {
        base.OnMouseWheel(e);
        if (e is HandledMouseEventArgs { Handled: true }) return;

        var pagina = _conteudo.Controls.OfType<Control>().FirstOrDefault(c => c.Visible);
        var rolavel = pagina == null ? null : AreasComRolagem(pagina).FirstOrDefault(c => c.Visible && c.VerticalScroll.Visible);
        if (rolavel == null) return;

        const int WmMouseWheel = 0x020A;
        SendMessage(rolavel.Handle, WmMouseWheel, new IntPtr(e.Delta << 16), IntPtr.Zero);
        if (e is HandledMouseEventArgs tratado) tratado.Handled = true;
    }

    private static IEnumerable<ScrollableControl> AreasComRolagem(Control raiz)
    {
        foreach (Control filho in raiz.Controls)
        {
            if (filho is ScrollableControl { AutoScroll: true } area) yield return area;
            foreach (var neta in AreasComRolagem(filho)) yield return neta;
        }
    }

    [System.Runtime.InteropServices.DllImport("user32.dll")]
    private static extern IntPtr SendMessage(IntPtr hwnd, int msg, IntPtr wParam, IntPtr lParam);

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
            "IP público " + (string.IsNullOrWhiteSpace(_estado.IpPublico) ? "indisponível" : _estado.IpPublico),
            "Ligada há " + UptimeTexto()
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


        if (_paginaSeguranca.Visible) _paginaSeguranca.Atualizar();
    }

    /// <summary>Modo de teste com prints (--teste-tela-atividade): abre a Atividade numa aba.</summary>
    internal void MostrarAtividade(string aba)
    {
        var botao = _navegacao.Controls.OfType<Button>().First(b => b.Text == "Atividade");
        MostrarPagina(_paginaAtividade, botao);
        _abasAtividade.DefinirOpcoes(new[]
        {
            ("importante", "Importante", Tema.Acento), ("conexao", "Check-in e conexão", Tema.Acento), ("tudo", "Tudo", Tema.Acento)
        }, aba);
        _abaAtividade = aba;
        AtualizarAtividade();
    }

    internal static void PrintsAtividade(string pasta)
    {
        Directory.CreateDirectory(pasta);
        Application.EnableVisualStyles();
        // Um dia de rotina (check-in a cada 5 min) misturado com o que importa.
        for (var i = 0; i < 70; i++) LogAtividade.Registrar(NivelAtividade.Sucesso, "CHECKIN", "Checkin concluído com sucesso.");
        LogAtividade.Registrar(NivelAtividade.Aviso, "HEARTBEAT", "Falha ao enviar heartbeat ao servidor.");
        LogAtividade.Registrar(NivelAtividade.Erro, "CHECKIN", "Falha no checkin: A tarefa foi cancelada.");
        LogAtividade.Registrar(NivelAtividade.Seguranca, "SEGURANÇA", "Arquivo-isca alterado em C:\\Users\\Public\\Documents -- possível ransomware.");
        LogAtividade.Registrar(NivelAtividade.Aviso, "REDE", "Wi-Fi \"Wi-Fi\" desconectado (sem reconexão automática) pelo portal; volta sozinho às 14:02:10 se não houver confirmação.");
        LogAtividade.Registrar(NivelAtividade.Sucesso, "CONFIG", "Configurações salvas pelo painel.");
        LogAtividade.Registrar(NivelAtividade.Info, "TRILHA", "Logon de MAURILIO\\recepcao registrado.");
        for (var i = 0; i < 10; i++) LogAtividade.Registrar(NivelAtividade.Sucesso, "CHECKIN", "Checkin concluído com sucesso.");

        var seguranca = new SegurancaService(() => new Config(), () => null);
        using var monitor = new MonitorRedeService(() => new Config(), () => null, seguranca);
        using var monitorDesempenho = new MonitorDesempenhoService(() => new Config(), () => null);
        using var janela = new MainForm(new AppState(), seguranca, () => 1, () => Task.CompletedTask, () => Task.CompletedTask,
            () => new Config(), _ => { }, () => Task.CompletedTask, monitor, monitorDesempenho) { ShowInTaskbar = false };
        janela.Show();
        foreach (var aba in new[] { "importante", "conexao", "tudo" })
        {
            janela.MostrarAtividade(aba);
            var ate = DateTime.Now.AddSeconds(1.5);
            while (DateTime.Now < ate) { Application.DoEvents(); Thread.Sleep(50); }
            using var imagem = new Bitmap(janela.Width, janela.Height);
            janela.DrawToBitmap(imagem, new Rectangle(Point.Empty, janela.Size));
            imagem.Save(Path.Combine(pasta, $"atividade-{aba}.png"));
        }
    }

    private void AtualizarAtividade()
    {
        var todas = LogAtividade.Recentes(600);
        var conexao = todas.Where(LogAtividade.EhConexao).ToList();
        var entradas = (_abaAtividade switch
        {
            "conexao" => conexao,
            "tudo" => todas,
            _ => todas.Where(e => !LogAtividade.EhConexao(e)).ToList()
        }).Take(300).ToList();

        if (_abaAtividade == "conexao")
        {
            var ultimoOk = conexao.FirstOrDefault(e => e.Categoria == "CHECKIN" && e.Nivel == NivelAtividade.Sucesso);
            var falhas24h = conexao.Count(e => e.Nivel is NivelAtividade.Erro or NivelAtividade.Aviso && e.Quando >= DateTime.Now.AddHours(-24));
            _resumoAtividade.Text = (ultimoOk != null ? $"Último check-in com sucesso: {ultimoOk.Quando:dd/MM HH:mm:ss}" : "Nenhum check-in com sucesso desde que o agente abriu")
                + $"   ·   Falhas de comunicação nas últimas 24 h: {falhas24h}";
            _resumoAtividade.ForeColor = falhas24h > 0 ? Tema.Perigo : Tema.TextoSecundario;
        }
        else
        {
            var problemas = entradas.Count(e => e.Nivel is NivelAtividade.Erro or NivelAtividade.Seguranca);
            _resumoAtividade.Text = _abaAtividade == "importante"
                ? (entradas.Count == 0 ? "Nada além da rotina de check-in até agora." : $"{entradas.Count} registro(s) fora da rotina de check-in" + (problemas > 0 ? $"   ·   {problemas} erro(s) ou alerta(s) de segurança" : ""))
                : $"{entradas.Count} registro(s), incluindo a rotina de check-in.";
            _resumoAtividade.ForeColor = Tema.TextoSecundario;
        }

        _listaAtividade.BeginUpdate();
        _listaAtividade.Items.Clear();
        foreach (var entrada in entradas)
        {
            var item = new ListViewItem(entrada.Quando.ToString("dd/MM HH:mm:ss"));
            item.SubItems.Add(entrada.Nivel.ToString().ToUpperInvariant());
            item.SubItems.Add(entrada.Categoria);
            item.SubItems.Add(entrada.Mensagem);
            item.ForeColor = entrada.Nivel == NivelAtividade.Erro ? Tema.Perigo : entrada.Nivel is NivelAtividade.Seguranca or NivelAtividade.Aviso ? Tema.Alerta : entrada.Nivel == NivelAtividade.Sucesso ? Tema.Sucesso : Tema.Texto;
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

}
