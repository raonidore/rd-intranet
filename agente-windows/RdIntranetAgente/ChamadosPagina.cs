using System.Drawing;
using System.Windows.Forms;

namespace RdIntranetAgente;

/// <summary>
/// Página "Chamados" do painel do agente: abre chamado com os mesmos
/// campos de Chamados &gt; Atendimentos &gt; Novo do portal, sobre esta
/// máquina, outro equipamento (monitor, impressora...) ou nenhum
/// (software, acesso). Sem login a pessoa informa nome e contato; com o
/// login do RD Intranet ela também acompanha e responde os próprios
/// chamados em "Meus chamados".
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
    private readonly Label _statusFormulario;
    private readonly TextBox _titulo;
    private readonly ComboBox _categoria;
    private readonly ComboBox _subcategoria;
    private readonly Label _rotuloSubcategoria;
    private readonly ComboBox _prioridade;
    private readonly ComboBox _unidade;
    private readonly ComboBox _setor;
    private readonly RadioButton _alvoEste;
    private readonly RadioButton _alvoOutro;
    private readonly RadioButton _alvoNenhum;
    private readonly TableLayoutPanel _linhaOutro;
    private readonly TextBox _buscaAtivo;
    private readonly ComboBox _resultadoAtivo;
    private readonly TextBox _descricao;
    private readonly TextBox _solicitanteNome;
    private readonly TextBox _solicitanteEmail;
    private readonly TextBox _solicitanteTelefone;
    private readonly Label _dicaSolicitante;
    private readonly BotaoTema _botaoEnviar;

    // meus chamados
    private readonly Label _statusMeus;
    private readonly ListView _listaMeus;

    // detalhe
    private int _chamadoAbertoId;
    private readonly Label _tituloDetalhe;
    private readonly RichTextBox _conversa;
    private readonly TextBox _resposta;
    private readonly BotaoTema _botaoResponder;

    private const string SemSubcategoria = "— Nenhuma —";
    private const string SetorPadrao = "— Usar o padrão —";

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
        _botaoSessao = new BotaoTema("Entrar", BotaoTema.Variante.Secundario) { Width = 170, Dock = DockStyle.Right };
        _botaoSessao.Click += async (s, e) => await AlternarSessaoAsync();
        _rotuloSessao = new Label { Dock = DockStyle.Fill, ForeColor = Tema.TextoSecundario, TextAlign = ContentAlignment.MiddleLeft, AutoEllipsis = true };
        barraSessao.Controls.Add(_rotuloSessao);
        barraSessao.Controls.Add(_botaoSessao);

        var barraAbas = new FlowLayoutPanel { Height = 42, Dock = DockStyle.Top, BackColor = Tema.Fundo, WrapContents = false };
        _abaAbrir = CriarAba("Abrir chamado");
        _abaMeus = CriarAba("Meus chamados");
        _abaAbrir.Click += (s, e) => MostrarArea(_areaAbrir!);
        _abaMeus.Click += async (s, e) => { MostrarArea(_areaMeus!); await CarregarMeusAsync(); };
        barraAbas.Controls.Add(_abaAbrir);
        barraAbas.Controls.Add(_abaMeus);

        // ---------------------------------------------------------- abrir
        _areaAbrir = new Panel { Dock = DockStyle.Fill, AutoScroll = true, BackColor = Tema.Fundo };
        var grade = new TableLayoutPanel
        {
            Dock = DockStyle.Top,
            AutoSize = true,
            AutoSizeMode = AutoSizeMode.GrowAndShrink,
            ColumnCount = 2,
            BackColor = Tema.Fundo,
            Padding = new Padding(0, 0, 8, 12)
        };
        grade.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 50));
        grade.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 50));

        _statusFormulario = new Label { ForeColor = Tema.TextoSecundario, Dock = DockStyle.Fill, Height = 24, AutoEllipsis = true, Text = "Carregando..." };
        _titulo = Campo();
        _titulo.MaxLength = 200;
        _titulo.PlaceholderText = "Ex: Impressora não imprime -- 2º andar financeiro";
        _categoria = Combo();
        _subcategoria = Combo();
        _prioridade = Combo();
        _unidade = Combo();
        _setor = Combo();
        _categoria.SelectedIndexChanged += (s, e) => AtualizarSubcategorias();

        _alvoEste = Radio("Este computador");
        _alvoOutro = Radio("Outro equipamento (monitor, impressora...)");
        _alvoNenhum = Radio("Nenhum equipamento (software, acesso...)");
        _alvoEste.Checked = true;
        var linhaAlvo = new FlowLayoutPanel { Dock = DockStyle.Fill, Height = 30, BackColor = Tema.Fundo, WrapContents = true, AutoSize = true, AutoSizeMode = AutoSizeMode.GrowAndShrink };
        linhaAlvo.Controls.AddRange(new Control[] { _alvoEste, _alvoOutro, _alvoNenhum });

        _buscaAtivo = Campo();
        _buscaAtivo.PlaceholderText = "Código, nome ou nº de série (mín. 2 letras)";
        _buscaAtivo.KeyDown += async (s, e) =>
        {
            if (e.KeyCode == Keys.Enter)
            {
                e.SuppressKeyPress = true;
                await BuscarAtivosAsync();
            }
        };
        _resultadoAtivo = Combo();
        var botaoBuscar = new BotaoTema("Buscar", BotaoTema.Variante.Secundario) { Width = 90, Dock = DockStyle.Right };
        botaoBuscar.Click += async (s, e) => await BuscarAtivosAsync();
        _buscaAtivo.Dock = DockStyle.Fill;
        var buscaComBotao = new Panel { Dock = DockStyle.Fill, Height = 30, BackColor = Tema.Fundo };
        buscaComBotao.Controls.Add(_buscaAtivo);
        buscaComBotao.Controls.Add(new Panel { Width = 8, Dock = DockStyle.Right, BackColor = Tema.Fundo });
        buscaComBotao.Controls.Add(botaoBuscar);
        _linhaOutro = new TableLayoutPanel { Dock = DockStyle.Fill, Height = 58, ColumnCount = 2, BackColor = Tema.Fundo, Visible = false, Margin = Padding.Empty };
        _linhaOutro.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 50));
        _linhaOutro.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 50));
        _linhaOutro.Controls.Add(ComRotulo("Buscar equipamento", buscaComBotao), 0, 0);
        _linhaOutro.Controls.Add(ComRotulo("Equipamento", _resultadoAtivo), 1, 0);
        foreach (var r in new[] { _alvoEste, _alvoOutro, _alvoNenhum })
        {
            r.CheckedChanged += (s, e) => _linhaOutro.Visible = _alvoOutro.Checked;
        }

        _descricao = Campo(multilinha: true);
        _descricao.Height = 130;
        _descricao.PlaceholderText = "Descreva o problema: o que aconteceu, desde quando, mensagem de erro...";
        _solicitanteNome = Campo();
        _solicitanteEmail = Campo();
        _solicitanteTelefone = Campo();
        _dicaSolicitante = new Label { Dock = DockStyle.Fill, ForeColor = Tema.TextoSecundario, Font = Tema.Fonte(8.5F), TextAlign = ContentAlignment.MiddleLeft, Padding = new Padding(3, 18, 0, 0) };

        _botaoEnviar = new BotaoTema("Abrir chamado", BotaoTema.Variante.Primario) { Width = 170, Margin = new Padding(3, 10, 3, 3) };
        _botaoEnviar.Click += async (s, e) => await EnviarAsync();

        _rotuloSubcategoria = new Label();
        AdicionarLinha(grade, _statusFormulario, null);
        AdicionarLinha(grade, ComRotulo("Título", _titulo), null);
        AdicionarLinha(grade, ComRotulo("Categoria", _categoria), ComRotulo("Subcategoria", _subcategoria, _rotuloSubcategoria));
        AdicionarLinha(grade, ComRotulo("Prioridade", _prioridade), ComRotulo("Unidade", _unidade));
        AdicionarLinha(grade, ComRotulo("Setor responsável (opcional)", _setor), null);
        AdicionarLinha(grade, ComRotulo("O chamado é sobre", linhaAlvo, altura: 62), null);
        AdicionarLinha(grade, _linhaOutro, null);
        AdicionarLinha(grade, ComRotulo("Descrição", _descricao, altura: 156), null);
        AdicionarLinha(grade, ComRotulo("Seu nome", _solicitanteNome), ComRotulo("E-mail", _solicitanteEmail));
        AdicionarLinha(grade, ComRotulo("Telefone", _solicitanteTelefone), _dicaSolicitante);
        AdicionarLinha(grade, _botaoEnviar, null);
        _areaAbrir.Controls.Add(grade);

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
        _resposta = Campo(multilinha: true);
        _resposta.PlaceholderText = "Escreva uma resposta para o suporte...";
        _resposta.Dock = DockStyle.Fill;
        _botaoResponder = new BotaoTema("Enviar", BotaoTema.Variante.Primario) { Width = 110, Dock = DockStyle.Right };
        _botaoResponder.Click += async (s, e) => await ResponderAsync();
        rodapeDetalhe.Controls.Add(_resposta);
        rodapeDetalhe.Controls.Add(new Panel { Width = 8, Dock = DockStyle.Right, BackColor = Tema.Fundo });
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

    /// <summary>Chamado quando a página é mostrada -- recarrega categorias/unidades (uma vez) e o estado do login.</summary>
    public async Task AoMostrarAsync()
    {
        if (_formulario == null)
        {
            await CarregarFormularioAsync();
        }
    }

    // ================================================================ sessão

    private ChamadoAgenteClient Cliente() => new(_config());

    private void AtualizarSessao(UsuarioAgente? usuario)
    {
        var logado = Cliente().Logado;
        if (logado && usuario != null)
        {
            _rotuloSessao.Text = $"Conectado como {usuario.Nome} ({usuario.Login}).";
            _rotuloSessao.ForeColor = Tema.Sucesso;
        }
        else if (logado)
        {
            _rotuloSessao.Text = "Conectado ao RD Intranet.";
            _rotuloSessao.ForeColor = Tema.Sucesso;
        }
        else
        {
            _rotuloSessao.Text = "Sem login: você pode abrir chamados informando seu nome e contato. Entre para acompanhar os seus.";
            _rotuloSessao.ForeColor = Tema.TextoSecundario;
        }

        _botaoSessao.Text = logado ? "Sair" : "Entrar com RD Intranet";
        _abaMeus.Enabled = true;

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
        _statusFormulario.Text = "Carregando categorias...";
        _statusFormulario.ForeColor = Tema.TextoSecundario;
        _botaoEnviar.Enabled = false;

        try
        {
            var resposta = await Cliente().FormularioAsync();
            if (!resposta.Ok || resposta.Dados == null)
            {
                _statusFormulario.Text = resposta.Mensagem;
                _statusFormulario.ForeColor = Tema.Perigo;
                AtualizarSessao(null);
                return;
            }

            _formulario = resposta.Dados;
            PreencherCombos(_formulario);
            AtualizarSessao(_formulario.Usuario);

            if (_formulario.Usuario != null)
            {
                if (_solicitanteNome.Text == "") _solicitanteNome.PlaceholderText = _formulario.Usuario.Nome;
                if (_solicitanteEmail.Text == "") _solicitanteEmail.PlaceholderText = _formulario.Usuario.Email;
            }
            else
            {
                _solicitanteNome.PlaceholderText = "Obrigatório";
                _solicitanteEmail.PlaceholderText = "";
            }

            _statusFormulario.Text = "";
            _botaoEnviar.Enabled = true;
        }
        finally
        {
            _carregando = false;
        }
    }

    private void PreencherCombos(FormularioChamado f)
    {
        var categoriaAtual = (_categoria.SelectedItem as CategoriaChamado)?.Id;
        _categoria.Items.Clear();
        _categoria.Items.Add("— Selecione —");
        foreach (var c in f.Categorias) _categoria.Items.Add(c);
        _categoria.SelectedIndex = Math.Max(0, f.Categorias.FindIndex(c => c.Id == categoriaAtual) + 1);

        _prioridade.Items.Clear();
        foreach (var p in f.Prioridades) _prioridade.Items.Add(p);
        _prioridade.SelectedIndex = Math.Max(0, f.Prioridades.FindIndex(p => p.Id == "media"));

        _unidade.Items.Clear();
        foreach (var u in f.Unidades) _unidade.Items.Add(u);
        var unidadeMaquina = f.EsteAtivo?.UnidadeId.ToString();
        _unidade.SelectedIndex = f.Unidades.Count == 0 ? -1 : Math.Max(0, f.Unidades.FindIndex(u => u.Id == unidadeMaquina));

        _setor.Items.Clear();
        _setor.Items.Add(SetorPadrao);
        foreach (var s in f.Setores) _setor.Items.Add(s);
        _setor.SelectedIndex = 0;

        _alvoEste.Text = f.EsteAtivo != null ? $"Este computador ({f.EsteAtivo.Codigo})" : "Este computador";
        AtualizarSubcategorias();
    }

    private void AtualizarSubcategorias()
    {
        _subcategoria.Items.Clear();
        _subcategoria.Items.Add(SemSubcategoria);
        var categoria = _categoria.SelectedItem as CategoriaChamado;
        var lista = categoria != null && _formulario?.Subcategorias.TryGetValue(categoria.Id.ToString(), out var subs) == true ? subs : new List<SubcategoriaChamado>();
        foreach (var s in lista) _subcategoria.Items.Add(s);
        _subcategoria.SelectedIndex = 0;
        _subcategoria.Enabled = lista.Count > 0;

        var exige = categoria?.ExigeSubcategoria == true && lista.Count > 0;
        _rotuloSubcategoria.Text = exige ? "Subcategoria (obrigatória)" : "Subcategoria (opcional)";
    }

    private async Task BuscarAtivosAsync()
    {
        var termo = _buscaAtivo.Text.Trim();
        if (termo.Length < 2)
        {
            MostrarErroFormulario("Digite pelo menos 2 letras para buscar o equipamento.");
            return;
        }

        _resultadoAtivo.Items.Clear();
        var resposta = await Cliente().BuscarAtivosAsync(termo);
        if (!resposta.Ok || resposta.Dados == null)
        {
            MostrarErroFormulario(resposta.Mensagem);
            return;
        }

        foreach (var a in resposta.Dados.Ativos) _resultadoAtivo.Items.Add(a);
        if (_resultadoAtivo.Items.Count > 0)
        {
            _resultadoAtivo.SelectedIndex = 0;
            _statusFormulario.Text = "";
        }
        else
        {
            MostrarErroFormulario("Nenhum equipamento encontrado. Se não achar, escolha \"Nenhum equipamento\" e explique na descrição.");
        }
    }

    private async Task EnviarAsync()
    {
        if (_formulario == null)
        {
            await CarregarFormularioAsync();
            if (_formulario == null) return;
        }

        var logado = Cliente().Logado;
        var categoria = _categoria.SelectedItem as CategoriaChamado;
        var subcategoria = _subcategoria.SelectedItem as SubcategoriaChamado;
        var unidade = _unidade.SelectedItem as ItemSimples;
        var ativoOutro = _resultadoAtivo.SelectedItem as AtivoResumo;

        string? erro = null;
        if (_titulo.Text.Trim() == "") erro = "Informe um título para o chamado.";
        else if (categoria == null) erro = "Escolha a categoria.";
        else if (_subcategoria.Enabled && categoria.ExigeSubcategoria && subcategoria == null) erro = $"Escolha uma subcategoria para \"{categoria.Nome}\".";
        else if (unidade == null) erro = "Escolha a unidade.";
        else if (_alvoOutro.Checked && ativoOutro == null) erro = "Busque e escolha o equipamento, ou marque \"Nenhum equipamento\".";
        else if (_descricao.Text.Trim() == "") erro = "Descreva o problema.";
        else if (!logado && _solicitanteNome.Text.Trim() == "") erro = "Informe seu nome.";
        else if (!logado && _solicitanteEmail.Text.Trim() == "" && _solicitanteTelefone.Text.Trim() == "") erro = "Informe e-mail ou telefone para o suporte te retornar.";

        if (erro != null)
        {
            MostrarErroFormulario(erro);
            return;
        }

        var dados = new
        {
            titulo = _titulo.Text.Trim(),
            descricao = _descricao.Text.Trim(),
            categoria_id = categoria!.Id,
            subcategoria_id = subcategoria?.Id,
            prioridade = (_prioridade.SelectedItem as ItemSimples)?.Id ?? "media",
            unidade_id = int.Parse(unidade!.Id),
            setor_id = _setor.SelectedItem is ItemSimples setor ? int.Parse(setor.Id) : (int?)null,
            alvo = _alvoOutro.Checked ? "outro" : _alvoNenhum.Checked ? "nenhum" : "este",
            ativo_id = _alvoOutro.Checked ? ativoOutro?.Id : null,
            solicitante_nome = _solicitanteNome.Text.Trim(),
            solicitante_email = _solicitanteEmail.Text.Trim(),
            solicitante_telefone = _solicitanteTelefone.Text.Trim(),
            usuario_windows = Environment.UserName
        };

        _botaoEnviar.Enabled = false;
        _statusFormulario.Text = "Enviando...";
        _statusFormulario.ForeColor = Tema.TextoSecundario;
        try
        {
            var resposta = await Cliente().AbrirAsync(dados);
            if (resposta.Dados?.SessaoExpirada == true)
            {
                AtualizarSessao(null);
                MostrarErroFormulario(resposta.Mensagem + " Seus dados continuam preenchidos.");
                return;
            }
            if (!resposta.Ok)
            {
                MostrarErroFormulario(resposta.Mensagem);
                return;
            }

            var numero = resposta.Dados?.NumeroControle ?? "";
            LogAtividade.Registrar(NivelAtividade.Sucesso, "CHAMADOS", $"Chamado #{numero} aberto pelo agente.");
            _titulo.Text = "";
            _descricao.Text = "";
            _statusFormulario.Text = $"Chamado #{numero} aberto.";
            _statusFormulario.ForeColor = Tema.Sucesso;

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

    private void MostrarErroFormulario(string mensagem)
    {
        _statusFormulario.Text = mensagem;
        _statusFormulario.ForeColor = Tema.Perigo;
        _areaAbrir.AutoScrollPosition = new Point(0, 0);
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
        Size = new Size(150, 34),
        Cursor = Cursors.Hand,
        Margin = new Padding(0, 0, 6, 0)
    };

    private static void AdicionarLinha(TableLayoutPanel grade, Control esquerda, Control? direita)
    {
        var linha = grade.RowCount;
        grade.RowCount = linha + 1;
        grade.RowStyles.Add(new RowStyle(SizeType.AutoSize));
        grade.Controls.Add(esquerda, 0, linha);
        if (direita == null)
        {
            grade.SetColumnSpan(esquerda, 2);
        }
        else
        {
            grade.Controls.Add(direita, 1, linha);
        }
    }

    private static Panel ComRotulo(string texto, Control campo, Label? rotulo = null, int altura = 58)
    {
        var painel = new Panel { Dock = DockStyle.Fill, Height = altura, BackColor = Tema.Fundo, Padding = new Padding(3, 0, 3, 4), Margin = Padding.Empty };
        campo.Dock = DockStyle.Fill;
        painel.Controls.Add(campo);
        rotulo ??= new Label();
        rotulo.Text = texto;
        rotulo.Dock = DockStyle.Top;
        rotulo.Height = 20;
        rotulo.ForeColor = Tema.TextoSecundario;
        rotulo.Font = Tema.Fonte(8.5F);
        painel.Controls.Add(rotulo);
        return painel;
    }

    private static TextBox Campo(bool multilinha = false)
    {
        var campo = new TextBox { Multiline = multilinha, ScrollBars = multilinha ? ScrollBars.Vertical : ScrollBars.None, AcceptsReturn = multilinha };
        Tema.EstilizarCampo(campo);
        return campo;
    }

    private static ComboBox Combo()
    {
        var combo = new ComboBox { DropDownStyle = ComboBoxStyle.DropDownList, IntegralHeight = false, MaxDropDownItems = 14 };
        Tema.EstilizarCampo(combo);
        return combo;
    }

    private static RadioButton Radio(string texto) => new()
    {
        Text = texto,
        AutoSize = true,
        ForeColor = Tema.Texto,
        BackColor = Tema.Fundo,
        Margin = new Padding(3, 6, 18, 0)
    };
}

/// <summary>Janela de login do RD Intranet dentro do agente (usuário e senha; a senha não é guardada).</summary>
public sealed class LoginChamadosForm : Form
{
    private readonly TextBox _login;
    private readonly TextBox _senha;
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
        ClientSize = new Size(380, 262);
        BackColor = Tema.Fundo;
        ForeColor = Tema.Texto;
        Font = Tema.Fonte(9F);
        Tema.BarraTituloEscura(this);

        var explicacao = new Label
        {
            Text = "Use o mesmo usuário e senha do portal RD Intranet.",
            Location = new Point(20, 16),
            Size = new Size(340, 22),
            ForeColor = Tema.TextoSecundario
        };
        var rotuloLogin = new Label { Text = "Usuário", Location = new Point(20, 48), Size = new Size(340, 18), ForeColor = Tema.TextoSecundario };
        _login = new TextBox { Location = new Point(20, 68), Width = 340 };
        Tema.EstilizarCampo(_login);
        var rotuloSenha = new Label { Text = "Senha", Location = new Point(20, 104), Size = new Size(340, 18), ForeColor = Tema.TextoSecundario };
        _senha = new TextBox { Location = new Point(20, 124), Width = 340, UseSystemPasswordChar = true };
        Tema.EstilizarCampo(_senha);
        _erro = new Label { Location = new Point(20, 160), Size = new Size(340, 40), ForeColor = Tema.Perigo };

        _entrar = new BotaoTema("Entrar", BotaoTema.Variante.Primario) { Location = new Point(250, 208), Width = 110 };
        _entrar.Click += (s, e) =>
        {
            if (Login == "" || Senha == "")
            {
                MostrarErro("Informe usuário e senha.");
                return;
            }
            DialogResult = DialogResult.OK;
        };
        var cancelar = new BotaoTema("Cancelar", BotaoTema.Variante.Secundario) { Location = new Point(130, 208), Width = 110 };
        cancelar.Click += (s, e) => DialogResult = DialogResult.Cancel;

        AcceptButton = _entrar;
        CancelButton = cancelar;
        Controls.AddRange(new Control[] { explicacao, rotuloLogin, _login, rotuloSenha, _senha, _erro, _entrar, cancelar });
    }

    public void MostrarErro(string mensagem)
    {
        _erro.Text = mensagem;
        _senha.Text = "";
        _senha.Focus();
    }

    public void Ocupado(bool ocupado)
    {
        _entrar.Enabled = !ocupado;
        UseWaitCursor = ocupado;
    }
}
