using System.Drawing;
using System.Drawing.Printing;
using System.Reflection;
using System.ServiceProcess;
using System.Windows.Forms;

namespace RdIntranetAgente;

/// <summary>
/// Formulário de configuração construído inteiramente em código (sem
/// Designer/.resx) -- evita problemas de serialização de layout e é mais
/// fácil de revisar/editar como texto puro.
/// </summary>
public class ConfigForm : Form
{
    private readonly TextBox _campoServidor;
    private readonly TextBox _campoChave;
    private readonly Button _botaoVerificar;
    private readonly Label _rotuloStatusVerificacao;
    private readonly ComboBox _campoUnidade;
    private readonly ComboBox _campoSetor;
    private readonly ComboBox _campoLocalizacao;
    private readonly NumericUpDown _campoIntervalo;
    private readonly NumericUpDown _campoHeartbeat;
    private readonly ComboBox _campoImpressora;
    private readonly TextBox _campoMachineGuidOverride;

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

    public Config ConfigResultante { get; private set; }

    public ConfigForm(Config configAtual)
    {
        ConfigResultante = configAtual;
        _eraConfiguradoAoAbrir = configAtual.EstaConfigurado;
        _unidadeIdAnterior = configAtual.UnidadeId;
        _setorIdAnterior = configAtual.SetorId;
        _localizacaoIdAnterior = configAtual.LocalizacaoId;

        Text = "RD Intranet - Configuração do Agente";
        Width = 460;
        Height = 510;
        FormBorderStyle = FormBorderStyle.FixedDialog;
        MaximizeBox = false;
        MinimizeBox = false;
        StartPosition = FormStartPosition.CenterScreen;

        var rotuloServidor = new Label { Text = "Endereço do servidor (ex: https://rd.intranet)", Left = 15, Top = 15, Width = 420 };
        _campoServidor = new TextBox { Left = 15, Top = 38, Width = 420, Text = configAtual.ServerUrl };

        var rotuloChave = new Label { Text = "Chave de API do agente (Ativos > Dashboard, no RD Intranet)", Left = 15, Top = 70, Width = 420 };
        _campoChave = new TextBox { Left = 15, Top = 93, Width = 420, Text = configAtual.ApiKey };

        _botaoVerificar = new Button { Text = "Verificar / Buscar unidades", Left = 15, Top = 125, Width = 190, Height = 26 };
        _rotuloStatusVerificacao = new Label { Left = 215, Top = 130, Width = 220, Height = 34, ForeColor = Color.Gray, Font = new Font("Segoe UI", 8F) };

        var rotuloUnidade = new Label { Text = "Unidade (obrigatório na primeira configuração)", Left = 15, Top = 163, Width = 420 };
        _campoUnidade = new ComboBox { Left = 15, Top = 186, Width = 420, DropDownStyle = ComboBoxStyle.DropDownList };
        _campoUnidade.Items.Add("-- selecione depois de verificar --");
        _campoUnidade.SelectedIndex = 0;

        var rotuloSetor = new Label { Text = "Setor (opcional)", Left = 15, Top = 218, Width = 200 };
        _campoSetor = new ComboBox { Left = 15, Top = 241, Width = 200, DropDownStyle = ComboBoxStyle.DropDownList };
        _campoSetor.Items.Add("(nenhum)");
        _campoSetor.SelectedIndex = 0;

        var rotuloLocalizacao = new Label { Text = "Localização (opcional)", Left = 235, Top = 218, Width = 200 };
        _campoLocalizacao = new ComboBox { Left = 235, Top = 241, Width = 200, DropDownStyle = ComboBoxStyle.DropDownList };
        _campoLocalizacao.Items.Add("(nenhuma)");
        _campoLocalizacao.SelectedIndex = 0;

        var rotuloIntervalo = new Label { Text = "Intervalo entre coletas completas (minutos)", Left = 15, Top = 280, Width = 220 };
        _campoIntervalo = new NumericUpDown
        {
            Left = 15,
            Top = 303,
            Width = 80,
            Minimum = 5,
            Maximum = 240,
            Value = Math.Clamp(configAtual.IntervaloMinutos <= 0 ? 15 : configAtual.IntervaloMinutos, 5, 240)
        };

        var rotuloHeartbeat = new Label { Text = "Heartbeat -- \"estou ligado\" (segundos)", Left = 245, Top = 280, Width = 190 };
        _campoHeartbeat = new NumericUpDown
        {
            Left = 245,
            Top = 303,
            Width = 80,
            Minimum = 1,
            Maximum = 60,
            Value = Math.Clamp(configAtual.HeartbeatSegundos <= 0 ? 1 : configAtual.HeartbeatSegundos, 1, 60)
        };

        var rotuloImpressora = new Label { Text = "Impressora de etiquetas (Zebra) -- opcional, só se for imprimir daqui", Left = 15, Top = 335, Width = 420 };
        _campoImpressora = new ComboBox { Left = 15, Top = 358, Width = 420, DropDownStyle = ComboBoxStyle.DropDownList };
        _campoImpressora.Items.Add("(nenhuma)");
        try
        {
            foreach (string nome in PrinterSettings.InstalledPrinters)
            {
                _campoImpressora.Items.Add(nome);
            }
        }
        catch
        {
            // sem impressoras instaladas ou erro ao enumerar -- so fica com "(nenhuma)"
        }

        var indiceAtual = _campoImpressora.Items.IndexOf(configAtual.ImpressoraEtiqueta);
        _campoImpressora.SelectedIndex = indiceAtual >= 0 ? indiceAtual : 0;

        var rotuloOverride = new Label
        {
            Text = "Identificador da máquina (avançado -- só preencha ao restaurar uma máquina reformatada; deixe em branco no dia a dia)",
            Left = 15,
            Top = 388,
            Width = 420,
            Height = 30
        };
        _campoMachineGuidOverride = new TextBox { Left = 15, Top = 420, Width = 420, Text = configAtual.MachineGuidOverride };

        var rotuloVersao = new Label
        {
            Text = "Versão do agente: v" + ObterVersao(),
            Left = 15,
            Top = 426 + 25,
            Width = 220,
            ForeColor = Color.Gray,
            Font = new Font("Segoe UI", 8F)
        };

        var (textoServico, corServico) = StatusServico();
        var rotuloServico = new Label
        {
            Text = textoServico,
            Left = 245,
            Top = 426 + 25,
            Width = 195,
            ForeColor = corServico,
            Font = new Font("Segoe UI", 8F, FontStyle.Bold)
        };

        var botaoSalvar = new Button { Text = "Salvar", Left = 260, Top = 420 + 25, Width = 80, DialogResult = DialogResult.OK };
        var botaoCancelar = new Button { Text = "Cancelar", Left = 350, Top = 420 + 25, Width = 80, DialogResult = DialogResult.Cancel };

        _botaoVerificar.Click += async (s, e) => await VerificarEBuscarCadastrosAsync();

        botaoSalvar.Click += (s, e) =>
        {
            if (string.IsNullOrWhiteSpace(_campoServidor.Text) || string.IsNullOrWhiteSpace(_campoChave.Text))
            {
                MessageBox.Show("Preencha o endereço do servidor e a chave de API.", "RD Intranet",
                    MessageBoxButtons.OK, MessageBoxIcon.Warning);
                DialogResult = DialogResult.None;
                return;
            }

            var unidadeEscolhida = _campoUnidade.SelectedItem as CadastroItem;

            if (!_eraConfiguradoAoAbrir && unidadeEscolhida == null)
            {
                MessageBox.Show("Clique em \"Verificar / Buscar unidades\" e escolha a unidade antes de salvar -- é assim que o ativo já nasce na unidade certa, sem precisar corrigir depois.", "RD Intranet",
                    MessageBoxButtons.OK, MessageBoxIcon.Warning);
                DialogResult = DialogResult.None;
                return;
            }

            var impressoraSelecionada = _campoImpressora.SelectedItem as string;
            var setorEscolhido = _campoSetor.SelectedItem as CadastroItem;
            var localizacaoEscolhida = _campoLocalizacao.SelectedItem as CadastroItem;

            ConfigResultante = new Config
            {
                ServerUrl = _campoServidor.Text.Trim().TrimEnd('/'),
                ApiKey = _campoChave.Text.Trim(),
                IntervaloMinutos = (int)_campoIntervalo.Value,
                HeartbeatSegundos = (int)_campoHeartbeat.Value,
                ImpressoraEtiqueta = (impressoraSelecionada == "(nenhuma)" ? null : impressoraSelecionada) ?? "",
                MachineGuidOverride = _campoMachineGuidOverride.Text.Trim(),
                // Mantém a escolha anterior se o operador não buscou de novo
                // (reconfiguração de um agente já em uso -- ver comentário no construtor).
                UnidadeId = unidadeEscolhida?.Id ?? _unidadeIdAnterior,
                SetorId = setorEscolhido?.Id ?? _setorIdAnterior,
                LocalizacaoId = localizacaoEscolhida?.Id ?? _localizacaoIdAnterior
            };
        };

        Controls.AddRange(new Control[]
        {
            rotuloServidor, _campoServidor,
            rotuloChave, _campoChave,
            _botaoVerificar, _rotuloStatusVerificacao,
            rotuloUnidade, _campoUnidade,
            rotuloSetor, _campoSetor,
            rotuloLocalizacao, _campoLocalizacao,
            rotuloIntervalo, _campoIntervalo,
            rotuloHeartbeat, _campoHeartbeat,
            rotuloImpressora, _campoImpressora,
            rotuloOverride, _campoMachineGuidOverride,
            rotuloVersao, rotuloServico,
            botaoSalvar, botaoCancelar
        });

        AcceptButton = null; // Enter não deve disparar Salvar sem passar pela validação de unidade
        CancelButton = botaoCancelar;

        // Reconfiguração de um agente já configurado -- busca sozinho, sem
        // precisar clicar, pra já mostrar as escolhas anteriores nos combos.
        if (_eraConfiguradoAoAbrir)
        {
            Load += async (s, e) => await VerificarEBuscarCadastrosAsync();
        }
    }

    private async Task VerificarEBuscarCadastrosAsync()
    {
        _botaoVerificar.Enabled = false;
        _rotuloStatusVerificacao.ForeColor = Color.Gray;
        _rotuloStatusVerificacao.Text = "Verificando...";

        var dados = await new CadastrosClient().BuscarAsync(_campoServidor.Text.Trim().TrimEnd('/'), _campoChave.Text.Trim());

        if (dados == null)
        {
            _rotuloStatusVerificacao.ForeColor = Color.Firebrick;
            _rotuloStatusVerificacao.Text = "Falha ao conectar -- confira a URL e a chave de API.";
            _botaoVerificar.Enabled = true;
            return;
        }

        PreencherCombo(_campoUnidade, dados.Unidades, "-- selecione --", _unidadeIdAnterior);
        PreencherCombo(_campoSetor, dados.Setores, "(nenhum)", _setorIdAnterior);
        PreencherCombo(_campoLocalizacao, dados.Localizacoes, "(nenhuma)", _localizacaoIdAnterior);

        _rotuloStatusVerificacao.ForeColor = Color.SeaGreen;
        _rotuloStatusVerificacao.Text = $"Conectado -- {dados.Unidades.Count} unidade(s) encontrada(s).";
        _botaoVerificar.Enabled = true;
    }

    private static void PreencherCombo(ComboBox combo, List<CadastroItem> itens, string rotuloVazio, int? idParaSelecionar)
    {
        combo.Items.Clear();
        combo.Items.Add(rotuloVazio);

        foreach (var item in itens)
        {
            combo.Items.Add(item);
        }

        var indice = idParaSelecionar == null
            ? -1
            : itens.FindIndex(i => i.Id == idParaSelecionar.Value);

        combo.SelectedIndex = indice >= 0 ? indice + 1 : 0;
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
                ? ("Serviço do Windows: rodando", Color.SeaGreen)
                : ($"Serviço do Windows: instalado ({controlador.Status})", Color.DarkOrange);
        }
        catch (InvalidOperationException)
        {
            return ("Serviço do Windows: não instalado", Color.Gray);
        }
    }
}
