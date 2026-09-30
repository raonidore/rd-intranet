using System.Drawing;
using System.Windows.Forms;

namespace RdIntranetAgente;

/// <summary>
/// Página "Chamados" do painel do agente: abre chamado com os mesmos
/// campos de Chamados &gt; Atendimentos &gt; Novo do portal, sobre esta
/// máquina, outro equipamento (monitor, impressora...) ou nenhum
/// (software, acesso). Sem login a pessoa informa nome e contato; com o
/// login do RD Intranet ela também acompanha e responde os próprios
/// chamados em "Meus chamados". Campos e escolhas usam os controles de
/// ControlesModernos.cs.
/// </summary>
public sealed class ChamadosPagina : Panel
{
    private readonly Func<Config> _config;
    private FormularioChamado? _formulario;
    private bool _carregando;

    // sessão
    private readonly Label _rotuloSessao;
    private readonly BotaoTema _botaoSessao;

    // abas
    private readonly Button _abaAbrir;
    private readonly Button _abaMeus;
    private readonly Panel _areaAbrir;
    private readonly Panel _areaMeus;
    private readonly Panel _areaDetalhe;

    // formulário
    private readonly FlowLayoutPanel _pilha;
    private readonly Label _statusFormulario;
    private readonly CaixaTexto _titulo;
    private readonly CaixaTexto _descricao;
    private readonly SeletorModerno _categoria;
    private readonly SeletorModerno _subcategoria;
    private readonly RotuloCampo _rotuloSubcategoria;
    private readonly SeletorSegmentado _prioridade;
    private readonly SeletorModerno _unidade;
    private readonly SeletorModerno _setor;
    private readonly CartaoEscolha _alvoEste;
    private readonly CartaoEscolha _alvoOutro;
    private readonly CartaoEscolha _alvoNenhum;
    private readonly CartaoSecao _cartaoAlvo;
    private readonly TableLayoutPanel _gradeAlvo;
    private readonly CaixaTexto _buscaAtivo;
    private readonly SeletorModerno _resultadoAtivo;
    private readonly CaixaTexto _solicitanteNome;
    private readonly CaixaTexto _solicitanteEmail;
    private readonly CaixaTexto _solicitanteTelefone;
    private readonly Label _dicaSolicitante;
    private readonly BotaoTema _botaoEnviar;

    // meus chamados
    private readonly Label _statusMeus;
    private readonly ListView _listaMeus;

    // detalhe
    private int _chamadoAbertoId;
    private readonly Label _tituloDetalhe;
    private readonly RichTextBox _conversa;
    private readonly CaixaTexto _resposta;
    private readonly BotaoTema _botaoResponder;

    private const string SemSubcategoria = "Nenhuma";
    private const string SetorPadrao = "Usar o padrão da categoria";
    private const int LinhaCampo = 74;
    private const int AlturaCartaoAlvoFechado = 30 + 50 + 90;

    public ChamadosPagina(Func<Config> config)
    {
        _config = config;
        Dock = DockStyle.Fill;
        BackColor = Tema.Fundo;
        Visible = false;

        // ---------------------------------------------------------- topo
        var titulo = new Panel { Height = 68, BackColor = Tema.Fundo, Dock = DockStyle.Top };
        titulo.Controls.Add(new Label { Text = "Chamados", Location = new Point(0, 0), Size = new Size(600, 34), ForeColor = Tema.Texto, Font = Tema.FonteSemibold(19F) });
        titulo.Controls.Add(new Label
        {
            Text = "Abra um chamado para o suporte e acompanhe os seus.",
            Location = new Point(0, 38),
            Size = new Size(690, 24),
            ForeColor = Tema.TextoSecundario,
            Font = Tema.Fonte(9F)
        });

        var barraSessao = new Panel { Height = 44, Dock = DockStyle.Top, BackColor = Tema.Fundo };
        _botaoSessao = new BotaoTema("Entrar", BotaoTema.Variante.Secundario) { Width = 190, Dock = DockStyle.Right };
        _botaoSessao.Click += async (s, e) => await AlternarSessaoAsync();
        _rotuloSessao = new Label { Dock = DockStyle.Fill, ForeColor = Tema.TextoSecundario, TextAlign = ContentAlignment.MiddleLeft, AutoEllipsis = true };
        barraSessao.Controls.Add(_rotuloSessao);
        barraSessao.Controls.Add(_botaoSessao);

        var barraAbas = new FlowLayoutPanel { Height = 46, Dock = DockStyle.Top, BackColor = Tema.Fundo, WrapContents = false, Padding = new Padding(0, 4, 0, 4) };
        _abaAbrir = CriarAba("Abrir chamado");
        _abaMeus = CriarAba("Meus chamados");
        _abaAbrir.Click += (s, e) => MostrarArea(_areaAbrir!);
        _abaMeus.Click += async (s, e) => { MostrarArea(_areaMeus!); await CarregarMeusAsync(); };
        barraAbas.Controls.Add(_abaAbrir);
        barraAbas.Controls.Add(_abaMeus);

        // ---------------------------------------------------------- abrir
        _areaAbrir = new Panel { Dock = DockStyle.Fill, AutoScroll = true, BackColor = Tema.Fundo };
        _pilha = new FlowLayoutPanel
        {
            FlowDirection = FlowDirection.TopDown,
            WrapContents = false,
            AutoSize = true,
            AutoSizeMode = AutoSizeMode.GrowAndShrink,
            Location = Point.Empty,
            BackColor = Tema.Fundo,
            Margin = Padding.Empty,
            Padding = new Padding(0, 4, 0, 8)
        };
        _areaAbrir.Controls.Add(_pilha);

        // 1. o problema
        _titulo = new CaixaTexto(icone: Icones.Editar) { MaxLength = 200, PlaceholderText = "Ex: Impressora não imprime -- 2º andar financeiro" };
        _descricao = new CaixaTexto(multilinha: true) { PlaceholderText = "O que aconteceu, desde quando, mensagem de erro que apareceu..." };
        var gradeProblema = Grade(1);
        AdicionarLinha(gradeProblema, LinhaCampo, Campo("Título", _titulo));
        AdicionarLinha(gradeProblema, 150, Campo("Descrição", _descricao));
        var cartaoProblema = Cartao(new CabecalhoSecao("1", "Qual é o problema?", "Um título curto e o máximo de detalhes na descrição."), gradeProblema, 30 + 50 + LinhaCampo + 150);

        // 2. classificação
        _categoria = new SeletorModerno { Placeholder = "Escolha a categoria" };
        _subcategoria = new SeletorModerno { Placeholder = SemSubcategoria };
        _rotuloSubcategoria = new RotuloCampo("Subcategoria");
        _prioridade = new SeletorSegmentado();
        _unidade = new SeletorModerno { Placeholder = "Escolha a unidade" };
        _setor = new SeletorModerno { Placeholder = SetorPadrao };
        _categoria.SelecaoAlterada += (s, e) => AtualizarSubcategorias();
        var gradeClasse = Grade(2);
        AdicionarLinha(gradeClasse, LinhaCampo, Campo("Categoria", _categoria), CampoComRotulo(_rotuloSubcategoria, _subcategoria));
        AdicionarLinha(gradeClasse, LinhaCampo, Campo("Prioridade", _prioridade));
        AdicionarLinha(gradeClasse, LinhaCampo, Campo("Unidade", _unidade), Campo("Setor responsável (opcional)", _setor));
        var cartaoClasse = Cartao(new CabecalhoSecao("2", "Classificação", "Ajuda a direcionar o chamado para a equipe certa."), gradeClasse, 30 + 50 + LinhaCampo * 3);

        // 3. sobre o quê
        _alvoEste = new CartaoEscolha(Icones.Computador, "Este computador", "Esta máquina");
        _alvoOutro = new CartaoEscolha(Icones.Monitor, "Outro equipamento", "Monitor, impressora...");
        _alvoNenhum = new CartaoEscolha(Icones.Aplicativos, "Nenhum", "Software, acesso, e-mail");
        _alvoEste.Selecionado = true;
        foreach (var cartao in new[] { _alvoEste, _alvoOutro, _alvoNenhum })
        {
            cartao.Escolhido += (s, e) => EscolherAlvo((CartaoEscolha)s!);
        }
        var linhaCartoes = new TableLayoutPanel { Dock = DockStyle.Fill, ColumnCount = 3, RowCount = 1, BackColor = Tema.Superficie, Margin = Padding.Empty };
        for (var i = 0; i < 3; i++) linhaCartoes.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 33.333F));
        _alvoEste.Dock = _alvoOutro.Dock = _alvoNenhum.Dock = DockStyle.Fill;
        _alvoEste.Margin = new Padding(0, 0, 6, 8);
        _alvoOutro.Margin = new Padding(3, 0, 3, 8);
        _alvoNenhum.Margin = new Padding(6, 0, 0, 8);
        linhaCartoes.Controls.Add(_alvoEste, 0, 0);
        linhaCartoes.Controls.Add(_alvoOutro, 1, 0);
        linhaCartoes.Controls.Add(_alvoNenhum, 2, 0);

        _buscaAtivo = new CaixaTexto(icone: Icones.Busca) { PlaceholderText = "Código, nome ou nº de série -- Enter para buscar" };
        _buscaAtivo.Caixa.KeyDown += async (s, e) =>
        {
            if (e.KeyCode == Keys.Enter)
            {
                e.SuppressKeyPress = true;
                await BuscarAtivosAsync();
            }
        };
        _resultadoAtivo = new SeletorModerno { Placeholder = "Busque ao lado para listar" };
        _gradeAlvo = Grade(2);
        AdicionarLinha(_gradeAlvo, 90, linhaCartoes);
        AdicionarLinha(_gradeAlvo, 0, Campo("Buscar equipamento", _buscaAtivo), Campo("Equipamento", _resultadoAtivo));
        _cartaoAlvo = Cartao(new CabecalhoSecao("3", "O chamado é sobre", "Assim o suporte já sabe qual equipamento olhar."), _gradeAlvo, AlturaCartaoAlvoFechado);

        // 4. contato
        _solicitanteNome = new CaixaTexto(icone: Icones.Pessoa);
        _solicitanteEmail = new CaixaTexto { PlaceholderText = "nome@empresa.com" };
        _solicitanteTelefone = new CaixaTexto { PlaceholderText = "(00) 00000-0000" };
        _dicaSolicitante = new Label { Dock = DockStyle.Fill, ForeColor = Tema.TextoSecundario, BackColor = Tema.Superficie, Font = Tema.Fonte(8.5F), TextAlign = ContentAlignment.MiddleLeft, Padding = new Padding(8, 18, 0, 0) };
        var gradeContato = Grade(2);
        AdicionarLinha(gradeContato, LinhaCampo, Campo("Seu nome", _solicitanteNome), Campo("E-mail", _solicitanteEmail));
        AdicionarLinha(gradeContato, LinhaCampo, Campo("Telefone", _solicitanteTelefone), _dicaSolicitante);
        var cartaoContato = Cartao(new CabecalhoSecao("4", "Seus dados", "Para o suporte te responder."), gradeContato, 30 + 50 + LinhaCampo * 2);

        // rodapé: mensagem + botão
        var rodape = new Panel { Height = 56, BackColor = Tema.Fundo, Margin = new Padding(0, 0, 0, 8) };
        _botaoEnviar = new BotaoTema("Abrir chamado", BotaoTema.Variante.Primario) { Width = 210, Dock = DockStyle.Right, Font = Tema.FonteSemibold(10.5F) };
        _botaoEnviar.Click += async (s, e) => await EnviarAsync();
        _statusFormulario = new Label { Dock = DockStyle.Fill, ForeColor = Tema.TextoSecundario, TextAlign = ContentAlignment.MiddleLeft, AutoEllipsis = true, Font = Tema.Fonte(9.5F), Text = "Carregando..." };
        rodape.Controls.Add(_statusFormulario);
        rodape.Controls.Add(_botaoEnviar);

        _pilha.Controls.AddRange(new Control[] { cartaoProblema, cartaoClasse, _cartaoAlvo, cartaoContato, rodape });
        void AjustarLarguras()
        {
            var largura = Math.Max(420, _areaAbrir.ClientSize.Width - SystemInformation.VerticalScrollBarWidth - 4);
            foreach (Control c in _pilha.Controls) c.Width = largura;
        }
        _areaAbrir.Resize += (s, e) => AjustarLarguras();
        AjustarLarguras();

        // ---------------------------------------------------------- meus
        _areaMeus = new Panel { Dock = DockStyle.Fill, BackColor = Tema.Fundo, Visible = false };
        var acoesMeus = new FlowLayoutPanel { Dock = DockStyle.Top, Height = 44, BackColor = Tema.Fundo };
        var botaoAtualizarMeus = new BotaoTema("Atualizar", BotaoTema.Variante.Secundario) { Width = 120 };
        botaoAtualizarMeus.Click += async (s, e) => await CarregarMeusAsync();
        var botaoVerMeus = new BotaoTema("Abrir conversa", BotaoTema.Variante.Secundario) { Width = 140 };
        botaoVerMeus.Click += async (s, e) => await AbrirSelecionadoAsync();
        acoesMeus.Controls.AddRange(new Control[] { botaoAtualizarMeus, botaoVerMeus });
        _statusMeus = new Label { Dock = DockStyle.Top, Height = 26, ForeColor = Tema.TextoSecundario, AutoEllipsis = true };
        _listaMeus = new ListView
        {
            Dock = DockStyle.Fill,
            View = View.Details,
            FullRowSelect = true,
            MultiSelect = false,
            HideSelection = false,
            BorderStyle = BorderStyle.None,
            BackColor = Tema.Superficie,
            ForeColor = Tema.Texto,
            HeaderStyle = ColumnHeaderStyle.Nonclickable
        };
        foreach (var (texto, largura) in new[] { ("Número", 110), ("Título", 250), ("Categoria", 150), ("Status", 120), ("Atualizado", 120) })
        {
            _listaMeus.Columns.Add(texto, largura);
        }
        _listaMeus.DoubleClick += async (s, e) => await AbrirSelecionadoAsync();
        _areaMeus.Controls.Add(_listaMeus);
        _areaMeus.Controls.Add(_statusMeus);
        _areaMeus.Controls.Add(acoesMeus);

        // ---------------------------------------------------------- detalhe
        _areaDetalhe = new Panel { Dock = DockStyle.Fill, BackColor = Tema.Fundo, Visible = false };
        var topoDetalhe = new Panel { Dock = DockStyle.Top, Height = 40, BackColor = Tema.Fundo };
        var botaoVoltar = new BotaoTema("← Voltar", BotaoTema.Variante.Fantasma) { Width = 100, Dock = DockStyle.Left };
        botaoVoltar.Click += async (s, e) => { MostrarArea(_areaMeus); await CarregarMeusAsync(); };
        _tituloDetalhe = new Label { Dock = DockStyle.Fill, ForeColor = Tema.Texto, Font = Tema.FonteSemibold(10.5F), TextAlign = ContentAlignment.MiddleLeft, AutoEllipsis = true, Padding = new Padding(8, 0, 0, 0) };
        topoDetalhe.Controls.Add(_tituloDetalhe);
        topoDetalhe.Controls.Add(botaoVoltar);
        _conversa = new RichTextBox { Dock = DockStyle.Fill, ReadOnly = true, BorderStyle = BorderStyle.None, BackColor = Tema.Superficie, ForeColor = Tema.Texto, Font = Tema.Fonte(9.5F) };
        var rodapeDetalhe = new Panel { Dock = DockStyle.Bottom, Height = 110, BackColor = Tema.Fundo, Padding = new Padding(0, 8, 0, 0) };
        _resposta = new CaixaTexto(multilinha: true) { PlaceholderText = "Escreva uma resposta para o suporte...", Dock = DockStyle.Fill };
        _botaoResponder = new BotaoTema("Enviar", BotaoTema.Variante.Primario) { Width = 110, Dock = DockStyle.Right };
        _botaoResponder.Click += async (s, e) => await ResponderAsync();
        rodapeDetalhe.Controls.Add(_resposta);
        rodapeDetalhe.Controls.Add(new Panel { Width = 10, Dock = DockStyle.Right, BackColor = Tema.Fundo });
        rodapeDetalhe.Controls.Add(_botaoResponder);
        _areaDetalhe.Controls.Add(_conversa);
        _areaDetalhe.Controls.Add(rodapeDetalhe);
        _areaDetalhe.Controls.Add(topoDetalhe);

        var corpo = new Panel { Dock = DockStyle.Fill, BackColor = Tema.Fundo };
        corpo.Controls.AddRange(new Control[] { _areaAbrir, _areaMeus, _areaDetalhe });

        Controls.Add(corpo);
        Controls.Add(barraAbas);
        Controls.Add(barraSessao);
        Controls.Add(titulo);

        MostrarArea(_areaAbrir);
        AtualizarSessao(null);
    }

    /// <summary>Chamado quando a página é mostrada -- carrega categorias/unidades na primeira vez.</summary>
    public async Task AoMostrarAsync()
    {
        MostrarArea(_areaAbrir);
        if (_formulario == null)
        {
            await CarregarFormularioAsync();
        }
        _titulo.Caixa.Focus();
    }

    // ================================================================ sessão

    private ChamadoAgenteClient Cliente() => new(_config());

    private void AtualizarSessao(UsuarioAgente? usuario)
    {
        var logado = Cliente().Logado;
        if (logado && usuario != null)
        {
            _rotuloSessao.Text = $"●  Conectado como {usuario.Nome} ({usuario.Login})";
            _rotuloSessao.ForeColor = Tema.Sucesso;
        }
        else if (logado)
        {
            _rotuloSessao.Text = "●  Conectado ao RD Intranet";
            _rotuloSessao.ForeColor = Tema.Sucesso;
        }
        else
        {
            _rotuloSessao.Text = "Sem login você abre chamados informando seu contato. Entre para acompanhar os seus.";
            _rotuloSessao.ForeColor = Tema.TextoSecundario;
        }

        _botaoSessao.Text = logado ? "Sair" : "Entrar com RD Intranet";

        _dicaSolicitante.Text = logado
            ? "Em branco, usa seu nome e e-mail do RD Intranet."
            : "Informe e-mail ou telefone para o suporte te retornar.";
    }

    private async Task AlternarSessaoAsync()
    {
        var cliente = Cliente();
        if (cliente.Logado)
        {
            if (MessageBox.Show(FindForm(), "Sair do RD Intranet neste agente?", "Chamados", MessageBoxButtons.YesNo, MessageBoxIcon.Question) != DialogResult.Yes)
            {
                return;
            }
            await cliente.LogoutAsync();
            LogAtividade.Registrar(NivelAtividade.Info, "CHAMADOS", "Sessão do RD Intranet encerrada no agente.");
            _listaMeus.Items.Clear();
            MostrarArea(_areaAbrir);
            AtualizarSessao(null);
            await CarregarFormularioAsync();
            return;
        }

        await EntrarAsync();
    }

    /// <summary>Pede usuário e senha; true se entrou.</summary>
    private async Task<bool> EntrarAsync()
    {
        using var dialogo = new LoginChamadosForm();
        while (dialogo.ShowDialog(FindForm()) == DialogResult.OK)
        {
            dialogo.Ocupado(true);
            var resposta = await Cliente().LoginAsync(dialogo.Login, dialogo.Senha);
            dialogo.Ocupado(false);

            if (resposta.Ok)
            {
                LogAtividade.Registrar(NivelAtividade.Sucesso, "CHAMADOS", $"Login no RD Intranet como {resposta.Dados?.Usuario?.Login}.");
                AtualizarSessao(resposta.Dados?.Usuario);
                await CarregarFormularioAsync();
                return true;
            }

            dialogo.MostrarErro(resposta.Mensagem);
        }

        return false;
    }

    // ================================================================ formulário

    private async Task CarregarFormularioAsync()
    {
        if (_carregando) return;
        _carregando = true;
        Status("Carregando categorias...", Tema.TextoSecundario);
        _botaoEnviar.Enabled = false;

        try
        {
            var resposta = await Cliente().FormularioAsync();
            if (!resposta.Ok || resposta.Dados == null)
            {
                Status(resposta.Mensagem, Tema.Perigo);
                AtualizarSessao(null);
                return;
            }

            _formulario = resposta.Dados;
            PreencherCampos(_formulario);
            AtualizarSessao(_formulario.Usuario);

            _solicitanteNome.PlaceholderText = _formulario.Usuario?.Nome ?? "Obrigatório";
            _solicitanteEmail.PlaceholderText = _formulario.Usuario?.Email is { Length: > 0 } email ? email : "nome@empresa.com";

            Status("", Tema.TextoSecundario);
            _botaoEnviar.Enabled = true;
        }
        finally
        {
            _carregando = false;
        }
    }

    private void PreencherCampos(FormularioChamado f)
    {
        var categoriaAtual = (_categoria.ItemSelecionado as CategoriaChamado)?.Id;
        _categoria.DefinirItens(f.Categorias, f.Categorias.FindIndex(c => c.Id == categoriaAtual));

        var cores = new Dictionary<string, Color>
        {
            ["baixa"] = Tema.TextoSecundario,
            ["media"] = Tema.Acento,
            ["alta"] = Tema.Alerta,
            ["urgente"] = Tema.Perigo
        };
        _prioridade.DefinirOpcoes(f.Prioridades.Select(p => (p.Id, p.Nome, cores.GetValueOrDefault(p.Id, Tema.Acento))), "media");

        var unidadeMaquina = f.EsteAtivo?.UnidadeId.ToString();
        _unidade.DefinirItens(f.Unidades, f.Unidades.Count == 0 ? -1 : Math.Max(0, f.Unidades.FindIndex(u => u.Id == unidadeMaquina)));

        _setor.DefinirItens(new object[] { SetorPadrao }.Concat(f.Setores), 0);

        _alvoEste.Descricao = f.EsteAtivo?.Codigo ?? "Esta máquina";
        AtualizarSubcategorias();
    }

    private void AtualizarSubcategorias()
    {
        var categoria = _categoria.ItemSelecionado as CategoriaChamado;
        var lista = categoria != null && _formulario?.Subcategorias.TryGetValue(categoria.Id.ToString(), out var subs) == true ? subs : new List<SubcategoriaChamado>();
        var exige = categoria?.ExigeSubcategoria == true && lista.Count > 0;

        _subcategoria.Placeholder = categoria == null ? "Escolha a categoria antes" : lista.Count == 0 ? "Sem subcategorias" : exige ? "Escolha a subcategoria" : SemSubcategoria;
        _subcategoria.DefinirItens(exige ? lista : new object[] { SemSubcategoria }.Concat(lista), exige ? -1 : (lista.Count > 0 ? 0 : -1));
        _subcategoria.Enabled = lista.Count > 0;
        _rotuloSubcategoria.Text = exige ? "Subcategoria  ·  obrigatória" : "Subcategoria  ·  opcional";
    }

    private void EscolherAlvo(CartaoEscolha escolhido)
    {
        foreach (var cartao in new[] { _alvoEste, _alvoOutro, _alvoNenhum })
        {
            cartao.Selecionado = cartao == escolhido;
        }

        // A linha da busca só aparece em "Outro equipamento".
        var aberto = escolhido == _alvoOutro;
        _gradeAlvo.RowStyles[1].Height = aberto ? LinhaCampo : 0;
        _cartaoAlvo.Height = AlturaCartaoAlvoFechado + (aberto ? LinhaCampo : 0);
        if (aberto) _buscaAtivo.Caixa.Focus();
    }

    private async Task BuscarAtivosAsync()
    {
        var termo = _buscaAtivo.Text.Trim();
        if (termo.Length < 2)
        {
            Status("Digite pelo menos 2 letras para buscar o equipamento.", Tema.Perigo);
            return;
        }

        Status("Buscando equipamentos...", Tema.TextoSecundario);
        var resposta = await Cliente().BuscarAtivosAsync(termo);
        if (!resposta.Ok || resposta.Dados == null)
        {
            Status(resposta.Mensagem, Tema.Perigo);
            return;
        }

        _resultadoAtivo.DefinirItens(resposta.Dados.Ativos, resposta.Dados.Ativos.Count > 0 ? 0 : -1);
        _resultadoAtivo.Placeholder = "Nenhum equipamento encontrado";
        Status(resposta.Dados.Ativos.Count > 0
            ? $"{resposta.Dados.Ativos.Count} equipamento(s) encontrado(s)."
            : "Nenhum equipamento encontrado. Se não achar, escolha \"Nenhum equipamento\" e explique na descrição.",
            resposta.Dados.Ativos.Count > 0 ? Tema.TextoSecundario : Tema.Alerta);
    }

    private async Task EnviarAsync()
    {
        if (_formulario == null)
        {
            await CarregarFormularioAsync();
            if (_formulario == null) return;
        }

        var logado = Cliente().Logado;
        var categoria = _categoria.ItemSelecionado as CategoriaChamado;
        var subcategoria = _subcategoria.ItemSelecionado as SubcategoriaChamado;
        var unidade = _unidade.ItemSelecionado as ItemSimples;
        var ativoOutro = _resultadoAtivo.ItemSelecionado as AtivoResumo;

        (string Mensagem, Control Campo)? erro = null;
        if (_titulo.Text.Trim() == "") erro = ("Informe um título para o chamado.", _titulo);
        else if (_descricao.Text.Trim() == "") erro = ("Descreva o problema.", _descricao);
        else if (categoria == null) erro = ("Escolha a categoria.", _categoria);
        else if (_subcategoria.Enabled && categoria.ExigeSubcategoria && subcategoria == null) erro = ($"Escolha uma subcategoria para \"{categoria.Nome}\".", _subcategoria);
        else if (unidade == null) erro = ("Escolha a unidade.", _unidade);
        else if (_alvoOutro.Selecionado && ativoOutro == null) erro = ("Busque e escolha o equipamento, ou escolha \"Nenhum equipamento\".", _buscaAtivo);
        else if (!logado && _solicitanteNome.Text.Trim() == "") erro = ("Informe seu nome.", _solicitanteNome);
        else if (!logado && _solicitanteEmail.Text.Trim() == "" && _solicitanteTelefone.Text.Trim() == "") erro = ("Informe e-mail ou telefone para o suporte te retornar.", _solicitanteEmail);

        if (erro != null)
        {
            Status(erro.Value.Mensagem, Tema.Perigo);
            if (erro.Value.Campo is CampoArredondado campoComErro) campoComErro.MarcarErro();
            _areaAbrir.ScrollControlIntoView(erro.Value.Campo);
            if (erro.Value.Campo is CaixaTexto caixa) caixa.Caixa.Focus(); else erro.Value.Campo.Focus();
            return;
        }

        var dados = new
        {
            titulo = _titulo.Text.Trim(),
            descricao = _descricao.Text.Trim(),
            categoria_id = categoria!.Id,
            subcategoria_id = subcategoria?.Id,
            prioridade = _prioridade.ChaveSelecionada ?? "media",
            unidade_id = int.Parse(unidade!.Id),
            setor_id = _setor.ItemSelecionado is ItemSimples setor ? int.Parse(setor.Id) : (int?)null,
            alvo = _alvoOutro.Selecionado ? "outro" : _alvoNenhum.Selecionado ? "nenhum" : "este",
            ativo_id = _alvoOutro.Selecionado ? ativoOutro?.Id : null,
            solicitante_nome = _solicitanteNome.Text.Trim(),
            solicitante_email = _solicitanteEmail.Text.Trim(),
            solicitante_telefone = _solicitanteTelefone.Text.Trim(),
            usuario_windows = Environment.UserName
        };

        _botaoEnviar.Enabled = false;
        Status("Enviando...", Tema.TextoSecundario);
        try
        {
            var resposta = await Cliente().AbrirAsync(dados);
            if (resposta.Dados?.SessaoExpirada == true)
            {
                AtualizarSessao(null);
                Status(resposta.Mensagem + " Seus dados continuam preenchidos.", Tema.Perigo);
                return;
            }
            if (!resposta.Ok)
            {
                Status(resposta.Mensagem, Tema.Perigo);
                return;
            }

            var numero = resposta.Dados?.NumeroControle ?? "";
            LogAtividade.Registrar(NivelAtividade.Sucesso, "CHAMADOS", $"Chamado #{numero} aberto pelo agente.");
            _titulo.Text = "";
            _descricao.Text = "";
            Status($"✓  Chamado #{numero} aberto.", Tema.Sucesso);

            MessageBox.Show(FindForm(),
                $"Chamado #{numero} aberto com sucesso!" + (logado
                    ? "\n\nAcompanhe em \"Meus chamados\"."
                    : "\n\nGuarde o número. Para acompanhar pelo agente, entre com seu usuário do RD Intranet."),
                "Chamados", MessageBoxButtons.OK, MessageBoxIcon.Information);
        }
        finally
        {
            _botaoEnviar.Enabled = true;
        }
    }

    private void Status(string mensagem, Color cor)
    {
        _statusFormulario.Text = mensagem;
        _statusFormulario.ForeColor = cor;
    }

    // ================================================================ meus chamados

    private async Task CarregarMeusAsync()
    {
        if (!Cliente().Logado)
        {
            _listaMeus.Items.Clear();
            _statusMeus.Text = "Entre com seu usuário do RD Intranet para ver seus chamados.";
            _statusMeus.ForeColor = Tema.TextoSecundario;
            if (!await EntrarAsync())
            {
                return;
            }
        }

        _statusMeus.Text = "Carregando...";
        _statusMeus.ForeColor = Tema.TextoSecundario;
        var resposta = await Cliente().MeusAsync();
        if (!resposta.Ok || resposta.Dados == null)
        {
            _statusMeus.Text = resposta.Mensagem;
            _statusMeus.ForeColor = Tema.Perigo;
            if (resposta.Dados?.SessaoExpirada == true) AtualizarSessao(null);
            return;
        }

        _listaMeus.BeginUpdate();
        _listaMeus.Items.Clear();
        foreach (var c in resposta.Dados.Chamados)
        {
            var item = new ListViewItem(new[] { "#" + c.Numero, c.Titulo, c.Categoria, c.Status, DataCurta(c.AtualizadoEm) }) { Tag = c.Id };
            item.ForeColor = c.StatusChave is "resolvido" or "fechado" ? Tema.TextoSecundario : Tema.Texto;
            _listaMeus.Items.Add(item);
        }
        _listaMeus.EndUpdate();
        _statusMeus.Text = resposta.Dados.Chamados.Count == 0
            ? "Você ainda não abriu nenhum chamado com este usuário."
            : $"{resposta.Dados.Chamados.Count} chamado(s). Dê dois cliques para ver a conversa.";
    }

    private async Task AbrirSelecionadoAsync()
    {
        if (_listaMeus.SelectedItems.Count == 0 || _listaMeus.SelectedItems[0].Tag is not int id)
        {
            return;
        }
        await CarregarDetalheAsync(id);
    }

    private async Task CarregarDetalheAsync(int id)
    {
        var resposta = await Cliente().VerAsync(id);
        if (!resposta.Ok || resposta.Dados?.Chamado == null)
        {
            _statusMeus.Text = resposta.Mensagem;
            _statusMeus.ForeColor = Tema.Perigo;
            return;
        }

        var c = resposta.Dados.Chamado;
        _chamadoAbertoId = c.Id;
        _tituloDetalhe.Text = $"#{c.Numero} · {c.Titulo}";

        _conversa.Clear();
        Escrever($"{c.Status}  ·  {c.Prioridade}  ·  {c.Categoria}\n", Tema.Acento, negrito: true);
        if (c.Atendente != "") Escrever($"Atendente: {c.Atendente}\n", Tema.TextoSecundario);
        if (c.Ativo != "") Escrever($"Equipamento: {c.Ativo}\n", Tema.TextoSecundario);
        Escrever($"Aberto em {DataCurta(c.AbertoEm)}\n\n", Tema.TextoSecundario);
        Escrever(c.Descricao + "\n", Tema.Texto);

        foreach (var m in c.Mensagens)
        {
            Escrever($"\n{m.Autor} · {DataCurta(m.Quando)}\n", m.DoSuporte ? Tema.Ciano : Tema.TextoSecundario, negrito: true);
            Escrever(m.Texto + "\n", Tema.Texto);
        }
        _conversa.SelectionStart = _conversa.TextLength;
        _conversa.ScrollToCaret();

        _resposta.Enabled = c.PodeResponder;
        _botaoResponder.Enabled = c.PodeResponder;
        _resposta.PlaceholderText = c.PodeResponder ? "Escreva uma resposta para o suporte..." : "Chamado encerrado. Se o problema voltou, abra um novo chamado.";
        MostrarArea(_areaDetalhe);
    }

    private async Task ResponderAsync()
    {
        var texto = _resposta.Text.Trim();
        if (texto == "" || _chamadoAbertoId == 0) return;

        _botaoResponder.Enabled = false;
        try
        {
            var resposta = await Cliente().ResponderAsync(_chamadoAbertoId, texto);
            if (!resposta.Ok)
            {
                MessageBox.Show(FindForm(), resposta.Mensagem, "Chamados", MessageBoxButtons.OK, MessageBoxIcon.Warning);
                return;
            }
            _resposta.Text = "";
            await CarregarDetalheAsync(_chamadoAbertoId);
        }
        finally
        {
            _botaoResponder.Enabled = true;
        }
    }

    private void Escrever(string texto, Color cor, bool negrito = false)
    {
        _conversa.SelectionStart = _conversa.TextLength;
        _conversa.SelectionLength = 0;
        _conversa.SelectionColor = cor;
        _conversa.SelectionFont = negrito ? Tema.FonteSemibold(9.5F) : Tema.Fonte(9.5F);
        _conversa.AppendText(texto);
    }

    private static string DataCurta(string valor) =>
        DateTime.TryParse(valor, out var data) ? data.ToString("dd/MM/yyyy HH:mm") : valor;

    // ================================================================ layout

    private void MostrarArea(Panel area)
    {
        _areaAbrir.Visible = area == _areaAbrir;
        _areaMeus.Visible = area == _areaMeus;
        _areaDetalhe.Visible = area == _areaDetalhe;
        var abaAtiva = area == _areaAbrir ? _abaAbrir : _abaMeus;
        foreach (var aba in new[] { _abaAbrir, _abaMeus })
        {
            aba.BackColor = aba == abaAtiva ? Tema.SuperficieElevada : Tema.Fundo;
            aba.ForeColor = aba == abaAtiva ? Tema.Acento : Tema.TextoSecundario;
        }
    }

    private static Button CriarAba(string texto) => new()
    {
        Text = texto,
        FlatStyle = FlatStyle.Flat,
        FlatAppearance = { BorderSize = 0 },
        Font = Tema.FonteSemibold(9.5F),
        Size = new Size(150, 36),
        Cursor = Cursors.Hand,
        Margin = new Padding(0, 0, 6, 0)
    };

    private static CartaoSecao Cartao(CabecalhoSecao cabecalho, Control conteudo, int altura)
    {
        var cartao = new CartaoSecao { Height = altura };
        conteudo.Dock = DockStyle.Fill;
        cartao.Controls.Add(conteudo);
        cartao.Controls.Add(cabecalho);
        return cartao;
    }

    private static TableLayoutPanel Grade(int colunas)
    {
        var grade = new TableLayoutPanel { ColumnCount = colunas, BackColor = Tema.Superficie, Margin = Padding.Empty, Padding = Padding.Empty };
        for (var i = 0; i < colunas; i++) grade.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100F / colunas));
        return grade;
    }

    private static void AdicionarLinha(TableLayoutPanel grade, int altura, Control esquerda, Control? direita = null)
    {
        var linha = grade.RowCount;
        grade.RowCount = linha + 1;
        grade.RowStyles.Add(new RowStyle(SizeType.Absolute, altura));
        var colunas = grade.ColumnCount;
        esquerda.Margin = new Padding(0, 0, direita != null ? 8 : 0, 0);
        grade.Controls.Add(esquerda, 0, linha);
        if (direita == null)
        {
            if (colunas > 1) grade.SetColumnSpan(esquerda, colunas);
        }
        else
        {
            direita.Margin = new Padding(8, 0, 0, 0);
            grade.Controls.Add(direita, 1, linha);
        }
    }

    private static Panel Campo(string rotulo, Control campo) => CampoComRotulo(new RotuloCampo(rotulo), campo);

    private static Panel CampoComRotulo(RotuloCampo rotulo, Control campo)
    {
        var painel = new Panel { Dock = DockStyle.Fill, BackColor = Tema.Superficie };
        campo.Dock = campo is CaixaTexto { Height: > 60 } ? DockStyle.Fill : DockStyle.Top;
        painel.Controls.Add(campo);
        painel.Controls.Add(rotulo);
        return painel;
    }
}

/// <summary>Janela de login do RD Intranet dentro do agente (usuário e senha; a senha não é guardada).</summary>
public sealed class LoginChamadosForm : Form
{
    private readonly CaixaTexto _login;
    private readonly CaixaTexto _senha;
    private readonly Label _erro;
    private readonly BotaoTema _entrar;

    public string Login => _login.Text.Trim();
    public string Senha => _senha.Text;

    public LoginChamadosForm()
    {
        AutoScaleDimensions = new SizeF(96F, 96F);
        AutoScaleMode = AutoScaleMode.Dpi;
        Text = "Entrar no RD Intranet";
        FormBorderStyle = FormBorderStyle.FixedDialog;
        MaximizeBox = false;
        MinimizeBox = false;
        ShowInTaskbar = false;
        StartPosition = FormStartPosition.CenterParent;
        ClientSize = new Size(400, 300);
        BackColor = Tema.Superficie;
        ForeColor = Tema.Texto;
        Font = Tema.Fonte(9F);
        Tema.BarraTituloEscura(this);

        var cabecalho = new CabecalhoSecao("RD", "Entrar no RD Intranet", "Mesmo usuário e senha do portal.") { Dock = DockStyle.None, Location = new Point(24, 20), Width = 352 };
        var rotuloLogin = new RotuloCampo("Usuário") { Dock = DockStyle.None, Location = new Point(24, 80), Width = 352 };
        _login = new CaixaTexto(icone: Icones.Pessoa) { Location = new Point(24, 102), Width = 352 };
        var rotuloSenha = new RotuloCampo("Senha") { Dock = DockStyle.None, Location = new Point(24, 152), Width = 352 };
        _senha = new CaixaTexto { Location = new Point(24, 174), Width = 352 };
        _senha.Caixa.UseSystemPasswordChar = true;
        _erro = new Label { Location = new Point(24, 220), Size = new Size(352, 22), ForeColor = Tema.Perigo, BackColor = Tema.Superficie };

        _entrar = new BotaoTema("Entrar", BotaoTema.Variante.Primario) { Location = new Point(256, 248), Width = 120 };
        _entrar.Click += (s, e) =>
        {
            if (Login == "" || Senha == "")
            {
                MostrarErro("Informe usuário e senha.");
                return;
            }
            DialogResult = DialogResult.OK;
        };
        var cancelar = new BotaoTema("Cancelar", BotaoTema.Variante.Fantasma) { Location = new Point(146, 248), Width = 100 };
        cancelar.Click += (s, e) => DialogResult = DialogResult.Cancel;

        AcceptButton = _entrar;
        CancelButton = cancelar;
        Controls.AddRange(new Control[] { cabecalho, rotuloLogin, _login, rotuloSenha, _senha, _erro, _entrar, cancelar });
        Shown += (s, e) => _login.Caixa.Focus();
    }

    public void MostrarErro(string mensagem)
    {
        _erro.Text = mensagem;
        _senha.Text = "";
        _senha.Caixa.Focus();
    }

    public void Ocupado(bool ocupado)
    {
        _entrar.Enabled = !ocupado;
        UseWaitCursor = ocupado;
    }
}
