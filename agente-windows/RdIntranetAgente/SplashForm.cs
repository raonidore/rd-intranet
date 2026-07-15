using System.Drawing;
using System.Linq;
using System.Reflection;
using System.Windows.Forms;

namespace RdIntranetAgente;

/// <summary>
/// Tela de abertura, visível por ~2s antes do ícone da bandeja aparecer --
/// serve pra deixar claro, visualmente, quando uma versão nova do agente
/// entrou em ação (evita a dúvida de "será que atualizou mesmo?").
/// Mostra a versão (Assembly.GetExecutingAssembly, o mesmo valor de
/// &lt;Version&gt; no .csproj) tanto aqui quanto em Configurações.
/// </summary>
public class SplashForm : Form
{
    private static readonly Color CorFundo = Color.FromArgb(13, 17, 23);
    private static readonly Color CorBorda = Color.FromArgb(48, 54, 61);
    private static readonly Color CorTrilho = Color.FromArgb(33, 38, 45);
    private static readonly Color CorAcento = Color.FromArgb(88, 166, 255);

    private readonly System.Windows.Forms.Timer _timerBarra;
    private readonly System.Windows.Forms.Timer _timerFechar;
    private readonly Panel _barraProgresso;
    private readonly int _larguraTrilho;

    public SplashForm()
    {
        FormBorderStyle = FormBorderStyle.None;
        StartPosition = FormStartPosition.CenterScreen;
        ClientSize = new Size(420, 240);
        BackColor = CorFundo;
        ShowInTaskbar = false;
        TopMost = true;

        var picLogo = new PictureBox
        {
            Image = CarregarLogo(),
            SizeMode = PictureBoxSizeMode.Zoom,
            Size = new Size(84, 84),
            Location = new Point((ClientSize.Width - 84) / 2, 26),
            BackColor = Color.Transparent
        };

        var lblTitulo = new Label
        {
            Text = "Iniciando Agente RD Intranet",
            ForeColor = Color.White,
            Font = new Font("Segoe UI", 11.5F, FontStyle.Bold),
            TextAlign = ContentAlignment.MiddleCenter,
            Size = new Size(ClientSize.Width, 26),
            Location = new Point(0, 128)
        };

        var lblVersao = new Label
        {
            Text = "v" + ObterVersao(),
            ForeColor = CorAcento,
            Font = new Font("Consolas", 9F, FontStyle.Bold),
            TextAlign = ContentAlignment.MiddleCenter,
            Size = new Size(ClientSize.Width, 18),
            Location = new Point(0, 156)
        };

        var trilho = new Panel
        {
            Location = new Point(60, 195),
            Size = new Size(ClientSize.Width - 120, 5),
            BackColor = CorTrilho
        };

        _barraProgresso = new Panel
        {
            Location = new Point(0, 0),
            Size = new Size(0, 5),
            BackColor = CorAcento
        };
        trilho.Controls.Add(_barraProgresso);
        _larguraTrilho = trilho.Width;

        Controls.Add(picLogo);
        Controls.Add(lblTitulo);
        Controls.Add(lblVersao);
        Controls.Add(trilho);

        Paint += (s, e) =>
        {
            using var caneta = new Pen(CorBorda);
            e.Graphics.DrawRectangle(caneta, 0, 0, ClientSize.Width - 1, ClientSize.Height - 1);
        };

        const int duracaoMs = 2000;
        const int intervaloTickMs = 30;
        var passos = duracaoMs / intervaloTickMs;
        var incremento = System.Math.Max(1, _larguraTrilho / passos);

        _timerBarra = new System.Windows.Forms.Timer { Interval = intervaloTickMs };
        _timerBarra.Tick += (s, e) =>
        {
            if (_barraProgresso.Width < _larguraTrilho)
            {
                _barraProgresso.Width = System.Math.Min(_larguraTrilho, _barraProgresso.Width + incremento);
            }
        };
        _timerBarra.Start();

        _timerFechar = new System.Windows.Forms.Timer { Interval = duracaoMs };
        _timerFechar.Tick += (s, e) =>
        {
            _timerFechar.Stop();
            _timerBarra.Stop();
            Close();
        };
        _timerFechar.Start();
    }

    private static string ObterVersao()
    {
        var versao = Assembly.GetExecutingAssembly().GetName().Version;
        return versao == null ? "?" : $"{versao.Major}.{versao.Minor}.{versao.Build}";
    }

    private static Image? CarregarLogo()
    {
        try
        {
            var assembly = Assembly.GetExecutingAssembly();
            var nomeRecurso = assembly.GetManifestResourceNames()
                .FirstOrDefault(n => n.EndsWith("logo.png", System.StringComparison.OrdinalIgnoreCase));

            if (nomeRecurso == null)
            {
                return null;
            }

            using var stream = assembly.GetManifestResourceStream(nomeRecurso);
            return stream == null ? null : Image.FromStream(stream);
        }
        catch
        {
            // sem logo embutido nao impede o agente de iniciar -- so fica sem imagem na tela de start
            return null;
        }
    }
}
