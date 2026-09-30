using System.Drawing;
using System.Drawing.Printing;
using System.Reflection;
using System.ServiceProcess;
using System.Windows.Forms;

namespace RdIntranetAgente;

/// <summary>
/// Formulário de configuração construído inteiramente em código (sem
/// Designer/.resx) -- evita problemas de serialização de layout e é mais
/// fácil de revisar/editar como texto puro. Usa os controles de
/// ControlesModernos.cs, em seções numeradas como a página Chamados.
/// Vai embutido no painel (MainForm, área "Configurações") ou numa janela
/// própria na primeira configuração (TrayApplicationContext).
/// </summary>
public class ConfigForm : Form
{
    private readonly CaixaTexto _campoServidor;
    private readonly CaixaTexto _campoChave;
    private readonly BotaoTema _botaoVerificar;
    private readonly Label _rotuloStatusVerificacao;
    private readonly SeletorModerno _campoUnidade;
    private readonly SeletorModerno _campoSetor;
    private readonly SeletorModerno _campoLocalizacao;
    private readonly CampoNumerico _campoIntervalo;
    private readonly CampoNumerico _campoHeartbeat;
    private readonly SeletorModerno _campoImpressora;
    private readonly CaixaTexto _campoMachineGuidOverride;
    private readonly Label _statusSalvar;
    private readonly Panel _rolagem;
    private readonly FlowLayoutPanel _pilha;

    // Capturado ANTES de qualquer edição -- só exige escolher a unidade
    // quando o agente está sendo configurado pela primeira vez nesta
    // máquina. Numa reconfiguração (trocar intervalo, impressora etc. de
    // um agente já em uso), a unidade escolhida aqui não teria efeito
    // nenhum mesmo (ver CheckinPayload) -- exigir de novo seria só
    // atrito sem propósito.
    private readonly bool _eraConfiguradoAoAbrir;
    private readonly int? _unidadeIdAnterior;
    private readonly int? _setorIdAnterior;
    private readonly int? _localizacaoIdAnterior;

    private const string SemImpressora = "Nenhuma";
    private const int LinhaCampo = 74;

    public Config ConfigResultante { get; private set; }

    /// <summary>
    /// Usados quando a tela vai embutida no painel (MainForm, área
    /// "Configurações") -- lá não existe DialogResult pra fechar.
    /// </summary>
    public event Action<Config>? Salvo;
    public event Action? Cancelado;

    public ConfigForm(Config configAtual)
    {
        ConfigResultante = configAtual;
        _eraConfiguradoAoAbrir = configAtual.EstaConfigurado;
        _unidadeIdAnterior = configAtual.UnidadeId;
        _setorIdAnterior = configAtual.SetorId;
        _localizacaoIdAnterior = configAtual.LocalizacaoId;

        AutoScaleDimensions = new SizeF(96F, 96F);
        AutoScaleMode = AutoScaleMode.Dpi;
        Text = "RD Intranet - Configuração do Agente";
        ClientSize = new Size(680, 720);
        FormBorderStyle = FormBorderStyle.FixedDialog;
        MaximizeBox = false;
        MinimizeBox = false;
        StartPosition = FormStartPosition.CenterScreen;
        BackColor = Tema.Fundo;
        ForeColor = Tema.Texto;
        Font = Tema.Fonte(9F);
        Tema.BarraTituloEscura(this);

        // ---------------------------------------------------------- 1. conexão
        _campoServidor = new CaixaTexto(icone: Icones.Globo) { Text = configAtual.ServerUrl, PlaceholderText = "https://rd.intranet" };
        _campoChave = new CaixaTexto(icone: Icones.Chave) { Text = configAtual.ApiKey, PlaceholderText = "Ativos > Dashboard, no RD Intranet" };
        _botaoVerificar = new BotaoTema("Verificar conexão", BotaoTema.Variante.Secundario) { Width = 180, Dock = DockStyle.Left };
        _rotuloStatusVerificacao = new Label
        {
            Dock = DockStyle.Fill,
            ForeColor = Tema.TextoSecundario,
            BackColor = Tema.Superficie,
            TextAlign = ContentAlignment.MiddleLeft,
            AutoEllipsis = true,
            Padding = new Padding(12, 0, 0, 0),
            Text = "Confere a URL e a chave e carrega as unidades."
        };
        var linhaVerificar = new Panel { Dock = DockStyle.Fill, BackColor = Tema.Superficie, Padding = new Padding(0, 2, 0, 10) };
        linhaVerificar.Controls.Add(_rotuloStatusVerificacao);
        linhaVerificar.Controls.Add(_botaoVerificar);

        var gradeConexao = Grade(1);
        AdicionarLinha(gradeConexao, LinhaCampo, Campo("Endereço do servidor", _campoServidor));
        AdicionarLinha(gradeConexao, LinhaCampo, Campo("Chave de API do agente", _campoChave));
        AdicionarLinha(gradeConexao, 48, linhaVerificar);
        var cartaoConexao = Cartao(new CabecalhoSecao("1", "Conexão", "Onde fica o RD Intranet e a chave que autoriza este agente."), gradeConexao, 30 + 50 + LinhaCampo * 2 + 48);

        // ---------------------------------------------------------- 2. localização
        _campoUnidade = new SeletorModerno { Placeholder = "Verifique a conexão para listar" };
        _campoSetor = new SeletorModerno { Placeholder = "Nenhum" };
        _campoLocalizacao = new SeletorModerno { Placeholder = "Nenhuma" };
        var gradeLocal = Grade(2);
        AdicionarLinha(gradeLocal, LinhaCampo, Campo(_eraConfiguradoAoAbrir ? "Unidade" : "Unidade  ·  obrigatória", _campoUnidade));
        AdicionarLinha(gradeLocal, LinhaCampo, Campo("Setor  ·  opcional", _campoSetor), Campo("Localização  ·  opcional", _campoLocalizacao));
        var cartaoLocal = Cartao(new CabecalhoSecao("2", "Localização do ativo", "Em que unidade esta máquina fica no inventário."), gradeLocal, 30 + 50 + LinhaCampo * 2);

        // ---------------------------------------------------------- 3. coleta
        _campoIntervalo = new CampoNumerico(5, 240, configAtual.IntervaloMinutos <= 0 ? 15 : configAtual.IntervaloMinutos, "min");
        _campoHeartbeat = new CampoNumerico(1, 60, configAtual.HeartbeatSegundos <= 0 ? 1 : configAtual.HeartbeatSegundos, "s");
        var gradeColeta = Grade(2);
        AdicionarLinha(gradeColeta, LinhaCampo, Campo("Coleta completa a cada", _campoIntervalo), Campo("Heartbeat (\"estou ligado\") a cada", _campoHeartbeat));
        var cartaoColeta = Cartao(new CabecalhoSecao("3", "Coleta", "Com que frequência o inventário e o sinal de vida são enviados."), gradeColeta, 30 + 50 + LinhaCampo);

        // ---------------------------------------------------------- 4. etiquetas
        _campoImpressora = new SeletorModerno { Placeholder = SemImpressora };
        var impressoras = new List<object> { SemImpressora };
        try
        {
            foreach (string nome in PrinterSettings.InstalledPrinters)
            {
                impressoras.Add(nome);
            }
        }
        catch
        {
            // sem impressoras instaladas ou erro ao enumerar -- só fica com "Nenhuma"
        }
        _campoImpressora.DefinirItens(impressoras, Math.Max(0, impressoras.IndexOf(configAtual.ImpressoraEtiqueta)));
        var gradeEtiquetas = Grade(1);
        AdicionarLinha(gradeEtiquetas, LinhaCampo, Campo("Impressora de etiquetas (Zebra)  ·  opcional", _campoImpressora));
        var cartaoEtiquetas = Cartao(new CabecalhoSecao("4", "Etiquetas", "Só se esta máquina for imprimir etiquetas de patrimônio."), gradeEtiquetas, 30 + 50 + LinhaCampo);

        // ---------------------------------------------------------- 5. avançado
        _campoMachineGuidOverride = new CaixaTexto(icone: Icones.Computador) { Text = configAtual.MachineGuidOverride, PlaceholderText = "Deixe em branco no dia a dia" };
        var gradeAvancado = Grade(1);
        AdicionarLinha(gradeAvancado, LinhaCampo, Campo("Identificador da máquina", _campoMachineGuidOverride));
        var cartaoAvancado = Cartao(new CabecalhoSecao("5", "Avançado", "Só ao restaurar uma máquina reformatada, pra ela continuar sendo o mesmo ativo."), gradeAvancado, 30 + 50 + LinhaCampo);

        // ---------------------------------------------------------- rodapé
        var (textoServico, corServico) = StatusServico();
        var info = new Label
        {
            Dock = DockStyle.Fill,
            Text = $"Versão {ObterVersao()}   ·   {textoServico}",
            ForeColor = corServico,
            TextAlign = ContentAlignment.MiddleLeft,
            Font = Tema.FonteSemibold(8.5F)
        };
        _statusSalvar = new Label { Dock = DockStyle.Top, Height = 22, ForeColor = Tema.Perigo, TextAlign = ContentAlignment.MiddleRight, AutoEllipsis = true };
        var botaoSalvar = new BotaoTema("Salvar", BotaoTema.Variante.Primario) { Width = 150, Dock = DockStyle.Right, Font = Tema.FonteSemibold(10.5F) };
        var botaoCancelar = new BotaoTema("Cancelar", BotaoTema.Variante.Fantasma) { Width = 110, Dock = DockStyle.Right };
        var linhaBotoes = new Panel { Dock = DockStyle.Fill, BackColor = Tema.Fundo };
        linhaBotoes.Controls.Add(info);
        linhaBotoes.Controls.Add(botaoCancelar);
        linhaBotoes.Controls.Add(new Panel { Width = 8, Dock = DockStyle.Right, BackColor = Tema.Fundo });
        linhaBotoes.Controls.Add(botaoSalvar);
        var rodape = new Panel { Height = 72, BackColor = Tema.Fundo, Margin = new Padding(0, 0, 0, 8) };
        rodape.Controls.Add(linhaBotoes);
        rodape.Controls.Add(_statusSalvar);

        _botaoVerificar.Click += async (s, e) => await VerificarEBuscarCadastrosAsync();
        botaoSalvar.Click += (s, e) => Salvar();
        botaoCancelar.Click += (s, e) =>
        {
            if (Modal) DialogResult = DialogResult.Cancel;
            Cancelado?.Invoke();
        };

        _rolagem = new Panel { Dock = DockStyle.Fill, AutoScroll = true, BackColor = Tema.Fundo, Padding = new Padding(0, 0, 0, 0) };
        _pilha = new FlowLayoutPanel
        {
            FlowDirection = FlowDirection.TopDown,
            WrapContents = false,
            AutoSize = true,
            AutoSizeMode = AutoSizeMode.GrowAndShrink,
            Location = Point.Empty,
            BackColor = Tema.Fundo,
            Margin = Padding.Empty
        };
        _pilha.Controls.AddRange(new Control[] { cartaoConexao, cartaoLocal, cartaoColeta, cartaoEtiquetas, cartaoAvancado, rodape });
        _rolagem.Controls.Add(_pilha);
        _rolagem.Resize += (s, e) => AjustarLarguras();
        Controls.Add(_rolagem);
        Tema.AplicarRolagemEscura(this);

        AcceptButton = null; // Enter não deve disparar Salvar sem passar pela validação de unidade
        CancelButton = botaoCancelar;

        Load += async (s, e) =>
        {
            // Janela própria (primeira configuração): margem interna; embutida no painel: encosta nas bordas.
            if (TopLevel) _rolagem.Padding = new Padding(20, 16, 12, 0);
            AjustarLarguras();

            // Reconfiguração de um agente já configurado -- busca sozinho, sem
            // precisar clicar, pra já mostrar as escolhas anteriores nas listas.
            if (_eraConfiguradoAoAbrir)
            {
                await VerificarEBuscarCadastrosAsync();
            }
        };
    }

    private void AjustarLarguras()
    {
        var largura = Math.Max(420, _rolagem.ClientSize.Width - _rolagem.Padding.Horizontal - SystemInformation.VerticalScrollBarWidth - 4);
        foreach (Control c in _pilha.Controls) c.Width = largura;
    }

    private void Salvar()
    {
        _statusSalvar.Text = "";

        if (string.IsNullOrWhiteSpace(_campoServidor.Text) || string.IsNullOrWhiteSpace(_campoChave.Text))
        {
            if (string.IsNullOrWhiteSpace(_campoServidor.Text)) _campoServidor.MarcarErro();
            if (string.IsNullOrWhiteSpace(_campoChave.Text)) _campoChave.MarcarErro();
            _statusSalvar.Text = "Preencha o endereço do servidor e a chave de API.";
            _rolagem.ScrollControlIntoView(_campoServidor);
            return;
        }

        var unidadeEscolhida = _campoUnidade.ItemSelecionado as CadastroItem;

        if (!_eraConfiguradoAoAbrir && unidadeEscolhida == null)
        {
            _campoUnidade.MarcarErro();
            _statusSalvar.Text = "Clique em \"Verificar conexão\" e escolha a unidade -- assim o ativo já nasce na unidade certa.";
            _rolagem.ScrollControlIntoView(_campoUnidade);
            return;
        }

        var impressoraSelecionada = _campoImpressora.ItemSelecionado as string;
        var setorEscolhido = _campoSetor.ItemSelecionado as CadastroItem;
        var localizacaoEscolhida = _campoLocalizacao.ItemSelecionado as CadastroItem;

        ConfigResultante = new Config
        {
            ServerUrl = _campoServidor.Text.Trim().TrimEnd('/'),
            ApiKey = _campoChave.Text.Trim(),
            IntervaloMinutos = _campoIntervalo.Valor,
            HeartbeatSegundos = _campoHeartbeat.Valor,
            ImpressoraEtiqueta = impressoraSelecionada == null || impressoraSelecionada == SemImpressora ? "" : impressoraSelecionada,
            MachineGuidOverride = _campoMachineGuidOverride.Text.Trim(),
            // Mantém a escolha anterior se o operador não buscou de novo
            // (reconfiguração de um agente já em uso -- ver comentário no construtor).
            UnidadeId = unidadeEscolhida?.Id ?? _unidadeIdAnterior,
            SetorId = setorEscolhido?.Id ?? _setorIdAnterior,
            LocalizacaoId = localizacaoEscolhida?.Id ?? _localizacaoIdAnterior
        };

        if (Modal) DialogResult = DialogResult.OK;
        Salvo?.Invoke(ConfigResultante);
    }

    private async Task VerificarEBuscarCadastrosAsync()
    {
        _botaoVerificar.Enabled = false;
        _rotuloStatusVerificacao.ForeColor = Tema.TextoSecundario;
        _rotuloStatusVerificacao.Text = "Verificando...";

        var dados = await new CadastrosClient().BuscarAsync(_campoServidor.Text.Trim().TrimEnd('/'), _campoChave.Text.Trim());

        if (dados == null)
        {
            _rotuloStatusVerificacao.ForeColor = Tema.Perigo;
            _rotuloStatusVerificacao.Text = "●  Falha ao conectar -- confira a URL e a chave de API.";
            _botaoVerificar.Enabled = true;
            return;
        }

        PreencherLista(_campoUnidade, dados.Unidades, null, _unidadeIdAnterior);
        PreencherLista(_campoSetor, dados.Setores, "Nenhum", _setorIdAnterior);
        PreencherLista(_campoLocalizacao, dados.Localizacoes, "Nenhuma", _localizacaoIdAnterior);
        _campoUnidade.Placeholder = "Escolha a unidade";

        _rotuloStatusVerificacao.ForeColor = Tema.Sucesso;
        _rotuloStatusVerificacao.Text = $"●  Conectado -- {dados.Unidades.Count} unidade(s) encontrada(s).";
        _botaoVerificar.Enabled = true;
    }

    /// <summary>rotuloVazio null = sem opção "nenhum" (unidade é obrigatória, começa sem seleção).</summary>
    private static void PreencherLista(SeletorModerno lista, List<CadastroItem> itens, string? rotuloVazio, int? idParaSelecionar)
    {
        var indice = idParaSelecionar == null ? -1 : itens.FindIndex(i => i.Id == idParaSelecionar.Value);

        if (rotuloVazio == null)
        {
            lista.DefinirItens(itens, indice);
            return;
        }

        lista.DefinirItens(new object[] { rotuloVazio }.Concat(itens), indice >= 0 ? indice + 1 : 0);
    }

    // ================================================================ layout

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
        esquerda.Margin = new Padding(0, 0, direita != null ? 8 : 0, 0);
        grade.Controls.Add(esquerda, 0, linha);
        if (direita == null)
        {
            if (grade.ColumnCount > 1) grade.SetColumnSpan(esquerda, grade.ColumnCount);
        }
        else
        {
            direita.Margin = new Padding(8, 0, 0, 0);
            grade.Controls.Add(direita, 1, linha);
        }
    }

    private static Panel Campo(string rotulo, Control campo)
    {
        var painel = new Panel { Dock = DockStyle.Fill, BackColor = Tema.Superficie };
        campo.Dock = DockStyle.Top;
        painel.Controls.Add(campo);
        painel.Controls.Add(new RotuloCampo(rotulo));
        return painel;
    }

    private static string ObterVersao()
    {
        var versao = Assembly.GetExecutingAssembly().GetName().Version;
        return versao == null ? "?" : $"{versao.Major}.{versao.Minor}.{versao.Build}";
    }

    /// <summary>
    /// Mostrado aqui (a "tela principal" do agente) pra quem instala/dá
    /// suporte remoto conseguir confirmar visualmente que o Serviço do
    /// Windows (ver AgenteServico/TrayApplicationContext.AlternarServicoAsync)
    /// realmente ficou instalado e rodando, sem precisar abrir
    /// services.msc na máquina do cliente.
    /// </summary>
    private static (string texto, Color cor) StatusServico()
    {
        try
        {
            using var controlador = new ServiceController(AgenteServico.NomeServico);
            return controlador.Status == ServiceControllerStatus.Running
                ? ("Serviço ativo", Tema.Sucesso)
                : ($"Serviço {controlador.Status.ToString().ToLowerInvariant()}", Tema.Alerta);
        }
        catch (InvalidOperationException)
        {
            return ("Serviço não instalado", Tema.TextoSecundario);
        }
    }
}
