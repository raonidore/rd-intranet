using System.Drawing;
using System.Drawing.Drawing2D;
using System.Linq;
using System.Reflection;
using System.Runtime.InteropServices;
using System.Windows.Forms;

namespace RdIntranetAgente;

/// <summary>
/// Identidade visual única do agente (painel, configurações, menu da
/// bandeja, splash). Tudo desenhado em código, sem Designer/.resx, pelo
/// mesmo motivo do resto do projeto: fácil de revisar como texto.
/// </summary>
public static class Tema
{
    public static readonly Color Fundo = Color.FromArgb(13, 17, 23);
    public static readonly Color Superficie = Color.FromArgb(22, 27, 34);
    public static readonly Color SuperficieElevada = Color.FromArgb(28, 34, 45);
    public static readonly Color Campo = Color.FromArgb(10, 13, 18);
    public static readonly Color Borda = Color.FromArgb(48, 54, 61);
    public static readonly Color BordaSuave = Color.FromArgb(33, 38, 45);
    public static readonly Color Texto = Color.FromArgb(230, 237, 243);
    public static readonly Color TextoSecundario = Color.FromArgb(139, 148, 158);
    public static readonly Color TextoApagado = Color.FromArgb(96, 104, 114);
    public static readonly Color Acento = Color.FromArgb(88, 166, 255);
    public static readonly Color AcentoEscuro = Color.FromArgb(31, 111, 235);
    public static readonly Color Ciano = Color.FromArgb(57, 208, 216);
    public static readonly Color Sucesso = Color.FromArgb(63, 185, 80);
    public static readonly Color Alerta = Color.FromArgb(210, 153, 34);
    public static readonly Color Perigo = Color.FromArgb(248, 81, 73);

    public static Font Fonte(float tamanho, FontStyle estilo = FontStyle.Regular) => new("Segoe UI", tamanho, estilo);
    public static Font FonteSemibold(float tamanho) => new("Segoe UI Semibold", tamanho);
    public static Font FonteMono(float tamanho, FontStyle estilo = FontStyle.Regular) => new("Consolas", tamanho, estilo);

    public static string Versao()
    {
        var v = Assembly.GetExecutingAssembly().GetName().Version;
        return v == null ? "?" : $"{v.Major}.{v.Minor}.{v.Build}";
    }

    public static Image? Logo()
    {
        try
        {
            var assembly = Assembly.GetExecutingAssembly();
            var nome = assembly.GetManifestResourceNames()
                .FirstOrDefault(n => n.EndsWith("logo.png", StringComparison.OrdinalIgnoreCase));
            if (nome == null) return null;
            using var stream = assembly.GetManifestResourceStream(nome);
            return stream == null ? null : Image.FromStream(stream);
        }
        catch
        {
            return null;
        }
    }

    [DllImport("dwmapi.dll")]
    private static extern int DwmSetWindowAttribute(IntPtr hwnd, int attr, ref int valor, int tamanho);

    /// <summary>Barra de título escura (Windows 10 1809+/11); em versões antigas simplesmente não tem efeito.</summary>
    public static void BarraTituloEscura(Form form)
    {
        form.HandleCreated += (s, e) =>
        {
            try
            {
                var ligado = 1;
                if (DwmSetWindowAttribute(form.Handle, 20, ref ligado, sizeof(int)) != 0)
                {
                    DwmSetWindowAttribute(form.Handle, 19, ref ligado, sizeof(int));
                }
            }
            catch
            {
                // sem DWM -- fica a barra padrão
            }
        };
    }

    public static GraphicsPath Arredondado(Rectangle r, int raio)
    {
        var caminho = new GraphicsPath();
        var d = raio * 2;
        caminho.AddArc(r.X, r.Y, d, d, 180, 90);
        caminho.AddArc(r.Right - d, r.Y, d, d, 270, 90);
        caminho.AddArc(r.Right - d, r.Bottom - d, d, d, 0, 90);
        caminho.AddArc(r.X, r.Bottom - d, d, d, 90, 90);
        caminho.CloseFigure();
        return caminho;
    }

    public static void EstilizarCampo(Control c)
    {
        c.BackColor = Campo;
        c.ForeColor = Texto;
        c.Font = Fonte(9.5F);

        switch (c)
        {
            case TextBox t:
                t.BorderStyle = BorderStyle.FixedSingle;
                break;
            case ComboBox cb:
                cb.FlatStyle = FlatStyle.Flat;
                break;
            case NumericUpDown n:
                n.BorderStyle = BorderStyle.FixedSingle;
                break;
        }
    }
}

/// <summary>Botão plano com cantos arredondados e hover -- variantes primária, secundária e perigo.</summary>
public class BotaoTema : Button
{
    public enum Variante { Primario, Secundario, Perigo, Fantasma }

    private readonly Variante _variante;
    private bool _hover;
    private bool _pressionado;

    public BotaoTema(string texto, Variante variante = Variante.Secundario)
    {
        _variante = variante;
        Text = texto;
        FlatStyle = FlatStyle.Flat;
        FlatAppearance.BorderSize = 0;
        Font = Tema.FonteSemibold(9.5F);
        Height = 34;
        Cursor = Cursors.Hand;
        SetStyle(ControlStyles.AllPaintingInWmPaint | ControlStyles.UserPaint | ControlStyles.OptimizedDoubleBuffer | ControlStyles.SupportsTransparentBackColor, true);
        BackColor = Color.Transparent;

        MouseEnter += (s, e) => { _hover = true; Invalidate(); };
        MouseLeave += (s, e) => { _hover = false; _pressionado = false; Invalidate(); };
        MouseDown += (s, e) => { _pressionado = true; Invalidate(); };
        MouseUp += (s, e) => { _pressionado = false; Invalidate(); };
        EnabledChanged += (s, e) => Invalidate();
    }

    protected override void OnPaint(PaintEventArgs e)
    {
        var g = e.Graphics;
        g.SmoothingMode = SmoothingMode.AntiAlias;
        g.Clear(Parent?.BackColor ?? Tema.Fundo);

        var (fundo, borda, texto) = _variante switch
        {
            Variante.Primario => (Tema.AcentoEscuro, Tema.Acento, Color.White),
            Variante.Perigo => (Color.FromArgb(60, 248, 81, 73), Tema.Perigo, Tema.Perigo),
            Variante.Fantasma => (Color.Transparent, Color.Transparent, Tema.TextoSecundario),
            _ => (Tema.SuperficieElevada, Tema.Borda, Tema.Texto)
        };

        if (!Enabled)
        {
            fundo = Tema.Superficie;
            borda = Tema.BordaSuave;
            texto = Tema.TextoApagado;
        }
        else if (_pressionado)
        {
            fundo = ControlPaint.Dark(fundo, 0.05F);
        }
        else if (_hover)
        {
            fundo = _variante == Variante.Fantasma ? Tema.SuperficieElevada : ControlPaint.Light(fundo, 0.25F);
            if (_variante == Variante.Fantasma) texto = Tema.Texto;
        }

        var r = new Rectangle(0, 0, Width - 1, Height - 1);
        using var caminho = Tema.Arredondado(r, 6);
        using (var pincel = new SolidBrush(fundo)) g.FillPath(pincel, caminho);
        if (borda != Color.Transparent)
        {
            using var caneta = new Pen(borda);
            g.DrawPath(caneta, caminho);
        }

        TextRenderer.DrawText(g, Text, Font, r, texto,
            TextFormatFlags.HorizontalCenter | TextFormatFlags.VerticalCenter | TextFormatFlags.EndEllipsis);
    }
}

/// <summary>Cartão com borda arredondada, rótulo em caixa alta e valor em destaque.</summary>
public class CartaoInfo : Control
{
    private string _rotulo = "";
    private string _valor = "";
    private string _detalhe = "";
    private Color _corIndicador = Color.Empty;

    public CartaoInfo()
    {
        SetStyle(ControlStyles.AllPaintingInWmPaint | ControlStyles.UserPaint | ControlStyles.OptimizedDoubleBuffer | ControlStyles.ResizeRedraw, true);
        BackColor = Tema.Fundo;
        Height = 92;
    }

    public void Definir(string rotulo, string valor, string detalhe = "", Color? corIndicador = null)
    {
        var cor = corIndicador ?? Color.Empty;
        if (rotulo == _rotulo && valor == _valor && detalhe == _detalhe && cor == _corIndicador) return;
        _rotulo = rotulo;
        _valor = valor;
        _detalhe = detalhe;
        _corIndicador = cor;
        Invalidate();
    }

    protected override void OnPaint(PaintEventArgs e)
    {
        var g = e.Graphics;
        g.SmoothingMode = SmoothingMode.AntiAlias;
        g.Clear(BackColor);

        var r = new Rectangle(0, 0, Width - 1, Height - 1);
        using (var caminho = Tema.Arredondado(r, 8))
        {
            using var fundo = new SolidBrush(Tema.Superficie);
            using var borda = new Pen(Tema.BordaSuave);
            g.FillPath(fundo, caminho);
            g.DrawPath(borda, caminho);
        }

        var x = 14;
        if (_corIndicador != Color.Empty)
        {
            using var halo = new SolidBrush(Color.FromArgb(50, _corIndicador));
            using var ponto = new SolidBrush(_corIndicador);
            g.FillEllipse(halo, x - 2, 14, 12, 12);
            g.FillEllipse(ponto, x + 1, 17, 6, 6);
            x += 16;
        }

        using var fonteRotulo = Tema.FonteSemibold(7.5F);
        using var fonteValor = Tema.FonteSemibold(12.5F);
        using var fonteDetalhe = Tema.Fonte(8.25F);

        TextRenderer.DrawText(g, _rotulo.ToUpperInvariant(), fonteRotulo, new Rectangle(x, 11, Width - x - 10, 16), Tema.TextoSecundario,
            TextFormatFlags.Left | TextFormatFlags.EndEllipsis);
        TextRenderer.DrawText(g, _valor, fonteValor, new Rectangle(14, 32, Width - 24, 26), Tema.Texto,
            TextFormatFlags.Left | TextFormatFlags.EndEllipsis);
        TextRenderer.DrawText(g, _detalhe, fonteDetalhe, new Rectangle(14, 60, Width - 24, 20), Tema.TextoSecundario,
            TextFormatFlags.Left | TextFormatFlags.EndEllipsis);
    }
}

/// <summary>Etiqueta de status em forma de pílula ("● ONLINE").</summary>
public class PilulaStatus : Control
{
    private Color _cor = Tema.TextoSecundario;

    public PilulaStatus()
    {
        SetStyle(ControlStyles.AllPaintingInWmPaint | ControlStyles.UserPaint | ControlStyles.OptimizedDoubleBuffer | ControlStyles.ResizeRedraw, true);
        Size = new Size(130, 26);
        Font = Tema.FonteSemibold(8F);
    }

    public void Definir(string texto, Color cor)
    {
        if (texto == Text && cor == _cor) return;
        Text = texto;
        _cor = cor;
        using var g = CreateGraphics();
        Width = TextRenderer.MeasureText(g, texto, Font).Width + 36;
        Invalidate();
    }

    protected override void OnPaint(PaintEventArgs e)
    {
        var g = e.Graphics;
        g.SmoothingMode = SmoothingMode.AntiAlias;
        g.Clear(Parent?.BackColor ?? Tema.Fundo);

        var r = new Rectangle(0, 0, Width - 1, Height - 1);
        using var caminho = Tema.Arredondado(r, Height / 2 - 1);
        using var fundo = new SolidBrush(Color.FromArgb(38, _cor));
        using var borda = new Pen(Color.FromArgb(120, _cor));
        g.FillPath(fundo, caminho);
        g.DrawPath(borda, caminho);

        using var ponto = new SolidBrush(_cor);
        g.FillEllipse(ponto, 12, Height / 2 - 4, 8, 8);

        TextRenderer.DrawText(g, Text, Font, new Rectangle(26, 0, Width - 30, Height), _cor,
            TextFormatFlags.Left | TextFormatFlags.VerticalCenter);
    }
}

/// <summary>Cores do menu da bandeja -- escuro, combinando com o painel.</summary>
public class RenderizadorMenuEscuro : ToolStripProfessionalRenderer
{
    public RenderizadorMenuEscuro() : base(new CoresMenu())
    {
        RoundedEdges = false;
    }

    protected override void OnRenderItemText(ToolStripItemTextRenderEventArgs e)
    {
        e.TextColor = e.Item.Enabled ? Tema.Texto : Tema.TextoApagado;
        base.OnRenderItemText(e);
    }

    protected override void OnRenderSeparator(ToolStripSeparatorRenderEventArgs e)
    {
        var y = e.Item.Height / 2;
        using var caneta = new Pen(Tema.BordaSuave);
        e.Graphics.DrawLine(caneta, 8, y, e.Item.Width - 8, y);
    }

    private class CoresMenu : ProfessionalColorTable
    {
        public override Color ToolStripDropDownBackground => Tema.Superficie;
        public override Color ImageMarginGradientBegin => Tema.Superficie;
        public override Color ImageMarginGradientMiddle => Tema.Superficie;
        public override Color ImageMarginGradientEnd => Tema.Superficie;
        public override Color MenuBorder => Tema.Borda;
        public override Color MenuItemBorder => Tema.AcentoEscuro;
        public override Color MenuItemSelected => Tema.SuperficieElevada;
        public override Color MenuItemSelectedGradientBegin => Tema.SuperficieElevada;
        public override Color MenuItemSelectedGradientEnd => Tema.SuperficieElevada;
        public override Color SeparatorDark => Tema.BordaSuave;
        public override Color SeparatorLight => Tema.BordaSuave;
    }
}
