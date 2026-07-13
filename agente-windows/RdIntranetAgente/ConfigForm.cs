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
    private readonly NumericUpDown _campoIntervalo;

    public Config ConfigResultante { get; private set; }

    public ConfigForm(Config configAtual)
    {
        ConfigResultante = configAtual;

        Text = "RD Intranet - Configuração do Agente";
        Width = 460;
        Height = 260;
        FormBorderStyle = FormBorderStyle.FixedDialog;
        MaximizeBox = false;
        MinimizeBox = false;
        StartPosition = FormStartPosition.CenterScreen;

        var rotuloServidor = new Label { Text = "Endereço do servidor (ex: https://rd.intranet)", Left = 15, Top = 15, Width = 420 };
        _campoServidor = new TextBox { Left = 15, Top = 38, Width = 420, Text = configAtual.ServerUrl };

        var rotuloChave = new Label { Text = "Chave de API do agente (Ativos > Dashboard, no RD Intranet)", Left = 15, Top = 70, Width = 420 };
        _campoChave = new TextBox { Left = 15, Top = 93, Width = 420, Text = configAtual.ApiKey };

        var rotuloIntervalo = new Label { Text = "Intervalo entre coletas (minutos)", Left = 15, Top = 125, Width = 250 };
        _campoIntervalo = new NumericUpDown
        {
            Left = 15,
            Top = 148,
            Width = 80,
            Minimum = 5,
            Maximum = 240,
            Value = Math.Clamp(configAtual.IntervaloMinutos <= 0 ? 15 : configAtual.IntervaloMinutos, 5, 240)
        };

        var botaoSalvar = new Button { Text = "Salvar", Left = 260, Top = 180, Width = 80, DialogResult = DialogResult.OK };
        var botaoCancelar = new Button { Text = "Cancelar", Left = 350, Top = 180, Width = 80, DialogResult = DialogResult.Cancel };

        botaoSalvar.Click += (s, e) =>
        {
            if (string.IsNullOrWhiteSpace(_campoServidor.Text) || string.IsNullOrWhiteSpace(_campoChave.Text))
            {
                MessageBox.Show("Preencha o endereço do servidor e a chave de API.", "RD Intranet",
                    MessageBoxButtons.OK, MessageBoxIcon.Warning);
                DialogResult = DialogResult.None;
                return;
            }

            ConfigResultante = new Config
            {
                ServerUrl = _campoServidor.Text.Trim().TrimEnd('/'),
                ApiKey = _campoChave.Text.Trim(),
                IntervaloMinutos = (int)_campoIntervalo.Value
            };
        };

        Controls.AddRange(new Control[]
        {
            rotuloServidor, _campoServidor,
            rotuloChave, _campoChave,
            rotuloIntervalo, _campoIntervalo,
            botaoSalvar, botaoCancelar
        });

        AcceptButton = botaoSalvar;
        CancelButton = botaoCancelar;
    }
}
