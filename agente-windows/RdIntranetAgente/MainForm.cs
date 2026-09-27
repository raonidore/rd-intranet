using System.Drawing;
using System.ServiceProcess;
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
    private readonly Panel _paginaConfig;
    private readonly Panel _hostConfig;
    private readonly Button _botaoConfig;
    private ConfigForm? _formConfig;
    private readonly PilulaStatus _statusConexao;
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

        // Escala por DPI: medidas escritas pra 100% (96 DPI) crescem sozinhas
        // em 125%/150%/200%; o layout empilhado acompanha o redimensionamento.
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
        var botaoSeguranca = CriarBotaoNavegacao("Segurança", 145);
        var botaoAtividade = CriarBotaoNavegacao("Atividade", 190);
        _botaoConfig = CriarBotaoNavegacao("Configurações", 235);
        botaoVisao.Click += (s, e) => MostrarPagina(_paginaVisao!, botaoVisao);
        botaoSeguranca.Click += (s, e) => MostrarPagina(_paginaSeguranca!, botaoSeguranca);
        botaoAtividade.Click += (s, e) => MostrarPagina(_paginaAtividade!, botaoAtividade);
        _botaoConfig.Click += (s, e) => AbrirConfiguracoes();
        _navegacao.Controls.AddRange(new Control[] { botaoVisao, botaoSeguranca, botaoAtividade, _botaoConfig });

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
        var tituloVisao = CriarTitulo("Visão geral", "Condição atual do agente nesta máquina.");
        _statusConexao = new PilulaStatus { Location = new Point(0, 6) };
        var linhaStatus = new Panel { Height = 44, BackColor = Tema.Fundo };
        linhaStatus.Controls.Add(_statusConexao);
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
        acoes.Controls.Add(CriarBotaoAcao("Coletar agora", BotaoTema.Variante.Primario, async () => await _coletar()));
        acoes.Controls.Add(CriarBotaoAcao("Atualizar agora", BotaoTema.Variante.Secundario, async () => await _atualizar()));
        acoes.Controls.Add(CriarBotaoAcao("Configurações", BotaoTema.Variante.Secundario, () => AbrirConfiguracoes()));
        acoes.Controls.Add(CriarBotaoAcao("Serviço do Windows", BotaoTema.Variante.Secundario, async () => await _alternarServico()));
        Empilhar(_paginaVisao, null, tituloVisao, linhaStatus, grade, Espaco(16), acoes);

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

        // Configurações dentro do próprio painel (antes era uma janela à parte):
        // a mesma tela de sempre, embutida -- validação e busca de unidades iguais.
        _paginaConfig = CriarPagina();
        var tituloConfig = CriarTitulo("Configurações", "Conexão com o RD Intranet e preferências desta máquina.");
        _hostConfig = new Panel { BackColor = Tema.Fundo, AutoScroll = true };
        Tema.TemaEscuroNativo(_hostConfig);
        Empilhar(_paginaConfig, _hostConfig, tituloConfig);

        _conteudo.Controls.AddRange(new Control[] { _paginaVisao, _paginaSeguranca, _paginaAtividade, _paginaConfig });
        MostrarPagina(_paginaVisao, botaoVisao);
        ResumeLayout(false);
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
            TextRenderer.DrawText(e.Graphics, e.SubItem.Text, lista.Font, area, e.Item.ForeColor,
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
        _paginaConfig.Visible = false;
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
}
