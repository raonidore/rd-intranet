using System.ComponentModel;
using System.Diagnostics.CodeAnalysis;
using System.Drawing;
using System.Drawing.Drawing2D;
using System.Windows.Forms;

namespace RdIntranetAgente;

/// <summary>
/// Controles desenhados à mão pro visual do painel (campos arredondados com
/// anel de foco, lista suspensa própria, cartões de escolha com ícone,
/// seletor segmentado). Os controles nativos do Windows, mesmo com tema
/// escuro, ficam com cara de formulário antigo -- borda 3D, seta clássica,
/// radio button.
/// </summary>
public static class Icones
{
    // Segoe Fluent Icons (Windows 11) e Segoe MDL2 Assets (Windows 10) usam os mesmos códigos.
    public const string Computador = "\uE7F8";
    public const string Monitor = "\uE7F4";
    public const string Aplicativos = "\uE71D";
    public const string Busca = "\uE721";
    public const string Seta = "\uE70D";
    public const string Check = "\uE73E";
    public const string Enviar = "\uE724";
    public const string Pessoa = "\uE77B";
    public const string Etiqueta = "\uE8EC";
    public const string Editar = "\uE70F";
    public const string Globo = "\uE774";
    public const string Chave = "\uE8D7";
    public const string Rede = "\uE968";
    public const string Velocidade = "\uEC4A";
    public const string Rota = "\uE81D";
    public const string Atualizar = "\uE72C";

    private static readonly string Familia = new System.Drawing.Text.InstalledFontCollection().Families
        .Select(f => f.Name)
        .FirstOrDefault(n => n is "Segoe Fluent Icons" or "Segoe MDL2 Assets") ?? "Segoe UI Symbol";

    public static Font Fonte(float tamanho) => new(Familia, tamanho);
}

/// <summary>Painel de seção: fundo de superfície com cantos arredondados e borda sutil.</summary>
public class CartaoSecao : Panel
{
    public CartaoSecao()
    {
        SetStyle(ControlStyles.AllPaintingInWmPaint | ControlStyles.UserPaint | ControlStyles.OptimizedDoubleBuffer | ControlStyles.ResizeRedraw, true);
        BackColor = Tema.Superficie;
        Padding = new Padding(20, 16, 20, 14);
        Margin = new Padding(0, 0, 0, 14);
    }

    protected override void OnPaint(PaintEventArgs e)
    {
        var g = e.Graphics;
        g.SmoothingMode = SmoothingMode.AntiAlias;
        g.Clear(Parent?.BackColor ?? Tema.Fundo);
        using var caminho = Tema.Arredondado(new Rectangle(0, 0, Width - 1, Height - 1), 12);
        using (var fundo = new SolidBrush(Tema.Superficie)) g.FillPath(fundo, caminho);
        using var borda = new Pen(Tema.BordaSuave);
        g.DrawPath(borda, caminho);
    }
}

/// <summary>Cabeçalho de seção: número num círculo com gradiente, título e subtítulo.</summary>
public class CabecalhoSecao : Control
{
    private readonly string _numero;
    private readonly string _titulo;
    private readonly string _subtitulo;

    public CabecalhoSecao(string numero, string titulo, string subtitulo)
    {
        _numero = numero;
        _titulo = titulo;
        _subtitulo = subtitulo;
        SetStyle(ControlStyles.AllPaintingInWmPaint | ControlStyles.UserPaint | ControlStyles.OptimizedDoubleBuffer | ControlStyles.ResizeRedraw, true);
        Height = 50;
        Dock = DockStyle.Top;
        BackColor = Tema.Superficie;
    }

    protected override void OnPaint(PaintEventArgs e)
    {
        var g = e.Graphics;
        g.SmoothingMode = SmoothingMode.AntiAlias;
        g.Clear(BackColor);

        var circulo = new Rectangle(0, 4, 28, 28);
        using (var gradiente = new LinearGradientBrush(circulo, Tema.Acento, Tema.Ciano, 45F))
        {
            g.FillEllipse(gradiente, circulo);
        }
        using (var fonteNumero = Tema.FonteSemibold(9.5F))
        {
            TextRenderer.DrawText(g, _numero, fonteNumero, circulo, Color.FromArgb(6, 24, 26),
                TextFormatFlags.HorizontalCenter | TextFormatFlags.VerticalCenter);
        }

        using var fonteTitulo = Tema.FonteSemibold(11F);
        using var fonteSub = Tema.Fonte(8.5F);
        TextRenderer.DrawText(g, _titulo, fonteTitulo, new Point(40, 1), Tema.Texto);
        TextRenderer.DrawText(g, _subtitulo, fonteSub, new Rectangle(40, 22, Width - 40, 18), Tema.TextoSecundario, TextFormatFlags.EndEllipsis);
    }
}

/// <summary>Rótulo pequeno acima do campo.</summary>
public class RotuloCampo : Label
{
    public RotuloCampo(string texto)
    {
        Text = texto;
        Dock = DockStyle.Top;
        Height = 22;
        ForeColor = Tema.TextoSecundario;
        BackColor = Tema.Superficie;
        Font = Tema.FonteSemibold(8.5F);
        TextAlign = ContentAlignment.TopLeft;
    }
}

/// <summary>Base dos campos arredondados: fundo, borda, hover e anel de foco.</summary>
public abstract class CampoArredondado : Control
{
    protected bool Hover;
    protected bool Focado;
    protected bool ComErro;
    protected const int Raio = 8;

    /// <summary>Borda vermelha de "campo com problema" -- some quando a pessoa digita ou escolhe algo no campo.</summary>
    public void MarcarErro()
    {
        ComErro = true;
        Invalidate();
    }

    protected void LimparErro()
    {
        if (!ComErro) return;
        ComErro = false;
        Invalidate();
    }

    protected CampoArredondado()
    {
        SetStyle(ControlStyles.AllPaintingInWmPaint | ControlStyles.UserPaint | ControlStyles.OptimizedDoubleBuffer | ControlStyles.ResizeRedraw | ControlStyles.SupportsTransparentBackColor, true);
        BackColor = Color.Transparent;
        Height = 40;
        Font = Tema.Fonte(10F);
        ForeColor = Tema.Texto;
        Cursor = Cursors.IBeam;
        MouseEnter += (s, e) => { Hover = true; Invalidate(); };
        MouseLeave += (s, e) => { Hover = false; Invalidate(); };
        EnabledChanged += (s, e) => Invalidate();
    }

    protected Color FundoCampo => Enabled ? Color.FromArgb(14, 18, 24) : Tema.Superficie;

    protected void DesenharMoldura(Graphics g)
    {
        g.SmoothingMode = SmoothingMode.AntiAlias;
        g.Clear(Parent?.BackColor ?? Tema.Superficie);

        var r = new Rectangle(2, 2, Width - 5, Height - 5);
        if ((Focado || ComErro) && Enabled)
        {
            // anel de foco: halo suave na cor de destaque (vermelho quando o campo tem erro)
            using var halo = Tema.Arredondado(new Rectangle(0, 0, Width - 1, Height - 1), Raio + 2);
            using var pincelHalo = new Pen(Color.FromArgb(70, ComErro ? Tema.Perigo : Tema.Acento), 3F);
            g.DrawPath(pincelHalo, halo);
        }

        using var caminho = Tema.Arredondado(r, Raio);
        using (var fundo = new SolidBrush(FundoCampo)) g.FillPath(fundo, caminho);
        var corBorda = !Enabled ? Tema.BordaSuave : ComErro ? Tema.Perigo : Focado ? Tema.Acento : Hover ? Color.FromArgb(72, 80, 90) : Tema.Borda;
        using var borda = new Pen(corBorda, Focado ? 1.4F : 1F);
        g.DrawPath(borda, caminho);
    }
}

/// <summary>Caixa de texto arredondada (uma linha ou várias), com ícone opcional à esquerda.</summary>
[DefaultEvent(nameof(TextoAlterado))]
public class CaixaTexto : CampoArredondado
{
    private readonly TextBox _caixa;
    private readonly string? _icone;

    public event EventHandler? TextoAlterado;

    public CaixaTexto(bool multilinha = false, string? icone = null)
    {
        _icone = icone;
        _caixa = new TextBox
        {
            BorderStyle = BorderStyle.None,
            Multiline = multilinha,
            AcceptsReturn = multilinha,
            ScrollBars = ScrollBars.None,
            BackColor = Color.FromArgb(14, 18, 24),
            ForeColor = Tema.Texto,
            Font = Tema.Fonte(10F)
        };
        if (multilinha) Tema.TemaEscuroNativo(_caixa);
        _caixa.GotFocus += (s, e) => { Focado = true; Invalidate(); };
        _caixa.LostFocus += (s, e) => { Focado = false; Invalidate(); };
        _caixa.MouseEnter += (s, e) => { Hover = true; Invalidate(); };
        _caixa.MouseLeave += (s, e) => { Hover = false; Invalidate(); };
        _caixa.TextChanged += (s, e) =>
        {
            LimparErro();
            AjustarRolagem();
            TextoAlterado?.Invoke(this, EventArgs.Empty);
        };
        Controls.Add(_caixa);
        Click += (s, e) => _caixa.Focus();
        Height = multilinha ? 120 : 40;
    }

    public TextBox Caixa => _caixa;

    /// <summary>Várias linhas: barra de rolagem só aparece quando o texto passa da altura da caixa.</summary>
    private void AjustarRolagem()
    {
        if (!_caixa.Multiline || _caixa.Width <= 0) return;
        var altura = TextRenderer.MeasureText(_caixa.Text + "\nX", _caixa.Font, new Size(_caixa.ClientSize.Width - 4, int.MaxValue), TextFormatFlags.WordBreak | TextFormatFlags.TextBoxControl).Height;
        var precisa = altura > _caixa.Height ? ScrollBars.Vertical : ScrollBars.None;
        if (_caixa.ScrollBars != precisa) _caixa.ScrollBars = precisa;
    }

    [AllowNull]
    public override string Text
    {
        get => _caixa.Text;
        set => _caixa.Text = value ?? "";
    }

    public string PlaceholderText
    {
        get => _caixa.PlaceholderText;
        set => _caixa.PlaceholderText = value;
    }

    public int MaxLength
    {
        get => _caixa.MaxLength;
        set => _caixa.MaxLength = value;
    }

    protected override void OnEnabledChanged(EventArgs e)
    {
        base.OnEnabledChanged(e);
        if (_caixa is null) return;
        _caixa.Enabled = Enabled;
        _caixa.BackColor = FundoCampo;
    }

    protected override void OnLayout(LayoutEventArgs levent)
    {
        base.OnLayout(levent);
        if (_caixa is null) return; // o construtor da base já dispara layout antes da caixa existir
        var esquerda = _icone != null ? 36 : 13;
        if (_caixa.Multiline)
        {
            _caixa.Bounds = new Rectangle(esquerda, 10, Width - esquerda - 8, Height - 18);
        }
        else
        {
            var altura = _caixa.PreferredHeight;
            _caixa.Bounds = new Rectangle(esquerda, (Height - altura) / 2, Width - esquerda - 12, altura);
        }
    }

    protected override void OnPaint(PaintEventArgs e)
    {
        DesenharMoldura(e.Graphics);
        if (_icone != null)
        {
            using var fonte = Icones.Fonte(11F);
            var cor = Focado ? Tema.Acento : Tema.TextoSecundario;
            TextRenderer.DrawText(e.Graphics, _icone, fonte, new Rectangle(10, 0, 22, _caixa.Multiline ? 38 : Height), cor,
                TextFormatFlags.HorizontalCenter | TextFormatFlags.VerticalCenter);
        }
    }
}

/// <summary>Lista suspensa própria: caixa arredondada com seta e um popup escuro com destaque ao passar o mouse.</summary>
[DefaultEvent(nameof(SelecaoAlterada))]
public class SeletorModerno : CampoArredondado
{
    private readonly List<object> _itens = new();
    private int _indice = -1;
    private ToolStripDropDown? _popup;

    public event EventHandler? SelecaoAlterada;

    public string Placeholder { get; set; } = "Selecione";

    public SeletorModerno()
    {
        Cursor = Cursors.Hand;
        TabStop = true;
        SetStyle(ControlStyles.Selectable, true);
    }

    public IReadOnlyList<object> Itens => _itens;

    public void DefinirItens(IEnumerable<object> itens, int selecionado = -1)
    {
        _itens.Clear();
        _itens.AddRange(itens);
        _indice = selecionado < _itens.Count ? selecionado : -1;
        Invalidate();
        SelecaoAlterada?.Invoke(this, EventArgs.Empty);
    }

    public int IndiceSelecionado
    {
        get => _indice;
        set
        {
            var novo = value >= -1 && value < _itens.Count ? value : -1;
            if (novo == _indice) return;
            _indice = novo;
            LimparErro();
            Invalidate();
            SelecaoAlterada?.Invoke(this, EventArgs.Empty);
        }
    }

    public object? ItemSelecionado => _indice >= 0 ? _itens[_indice] : null;

    protected override void OnGotFocus(EventArgs e) { base.OnGotFocus(e); Focado = true; Invalidate(); }
    protected override void OnLostFocus(EventArgs e) { base.OnLostFocus(e); Focado = false; Invalidate(); }

    protected override void OnMouseDown(MouseEventArgs e)
    {
        base.OnMouseDown(e);
        if (!Enabled) return;
        Focus();
        AbrirLista();
    }

    protected override bool IsInputKey(Keys keyData) => keyData is Keys.Up or Keys.Down || base.IsInputKey(keyData);

    protected override void OnKeyDown(KeyEventArgs e)
    {
        base.OnKeyDown(e);
        if (e.KeyCode == Keys.Down && e.Alt || e.KeyCode is Keys.F4 or Keys.Space or Keys.Enter) { AbrirLista(); e.Handled = true; }
        else if (e.KeyCode == Keys.Down) { IndiceSelecionado = Math.Min(_itens.Count - 1, _indice + 1); e.Handled = true; }
        else if (e.KeyCode == Keys.Up) { IndiceSelecionado = Math.Max(0, _indice - 1); e.Handled = true; }
    }

    private void AbrirLista()
    {
        if (_itens.Count == 0) return;

        var lista = new ListaSuspensa(_itens, _indice) { Width = Width - 4 };
        lista.Height = Math.Min(_itens.Count, 8) * ListaSuspensa.AlturaItem + 8;
        var host = new ToolStripControlHost(lista) { Margin = Padding.Empty, Padding = Padding.Empty, AutoSize = false, Size = lista.Size };
        _popup = new ToolStripDropDown { Padding = Padding.Empty, BackColor = Tema.SuperficieElevada, DropShadowEnabled = true, Renderer = new RenderizadorMenuEscuro() };
        _popup.Items.Add(host);
        lista.Escolhido += indice =>
        {
            _popup?.Close();
            IndiceSelecionado = indice;
            Focus();
        };
        _popup.Closed += (s, e) => { Invalidate(); };
        _popup.Show(this, new Point(2, Height - 1));
        lista.Focus();
    }

    protected override void OnPaint(PaintEventArgs e)
    {
        DesenharMoldura(e.Graphics);
        var texto = ItemSelecionado?.ToString() ?? Placeholder;
        var cor = !Enabled ? Tema.TextoApagado : ItemSelecionado == null ? Tema.TextoApagado : Tema.Texto;
        TextRenderer.DrawText(e.Graphics, texto, Font, new Rectangle(13, 0, Width - 48, Height), cor,
            TextFormatFlags.VerticalCenter | TextFormatFlags.EndEllipsis | TextFormatFlags.NoPrefix);
        using var fonteSeta = Icones.Fonte(8.5F);
        TextRenderer.DrawText(e.Graphics, Icones.Seta, fonteSeta, new Rectangle(Width - 34, 0, 22, Height),
            Focado ? Tema.Acento : Tema.TextoSecundario, TextFormatFlags.HorizontalCenter | TextFormatFlags.VerticalCenter);
    }
}

/// <summary>Conteúdo do popup do SeletorModerno: itens com destaque de hover e check no selecionado.</summary>
internal sealed class ListaSuspensa : Control
{
    public const int AlturaItem = 34;
    private readonly List<object> _itens;
    private readonly int _selecionado;
    private int _hover = -1;
    private int _topo;

    public event Action<int>? Escolhido;

    public ListaSuspensa(List<object> itens, int selecionado)
    {
        _itens = itens;
        _selecionado = selecionado;
        _hover = selecionado;
        SetStyle(ControlStyles.AllPaintingInWmPaint | ControlStyles.UserPaint | ControlStyles.OptimizedDoubleBuffer | ControlStyles.Selectable, true);
        BackColor = Tema.SuperficieElevada;
        Font = Tema.Fonte(10F);
        Cursor = Cursors.Hand;
        if (selecionado >= 8) _topo = selecionado - 7;
    }

    private int Visiveis => Math.Max(1, (Height - 8) / AlturaItem);

    protected override void OnMouseMove(MouseEventArgs e)
    {
        base.OnMouseMove(e);
        var i = _topo + (e.Y - 4) / AlturaItem;
        if (i != _hover && i >= 0 && i < _itens.Count) { _hover = i; Invalidate(); }
    }

    protected override void OnMouseWheel(MouseEventArgs e)
    {
        base.OnMouseWheel(e);
        _topo = Math.Clamp(_topo - Math.Sign(e.Delta), 0, Math.Max(0, _itens.Count - Visiveis));
        Invalidate();
    }

    protected override void OnMouseClick(MouseEventArgs e)
    {
        base.OnMouseClick(e);
        var i = _topo + (e.Y - 4) / AlturaItem;
        if (i >= 0 && i < _itens.Count) Escolhido?.Invoke(i);
    }

    protected override bool IsInputKey(Keys keyData) => keyData is Keys.Up or Keys.Down or Keys.Enter || base.IsInputKey(keyData);

    protected override void OnKeyDown(KeyEventArgs e)
    {
        base.OnKeyDown(e);
        if (e.KeyCode == Keys.Down) _hover = Math.Min(_itens.Count - 1, _hover + 1);
        else if (e.KeyCode == Keys.Up) _hover = Math.Max(0, _hover - 1);
        else if (e.KeyCode == Keys.Enter && _hover >= 0) { Escolhido?.Invoke(_hover); return; }
        if (_hover < _topo) _topo = _hover;
        if (_hover >= _topo + Visiveis) _topo = _hover - Visiveis + 1;
        Invalidate();
    }

    protected override void OnPaint(PaintEventArgs e)
    {
        var g = e.Graphics;
        g.SmoothingMode = SmoothingMode.AntiAlias;
        g.Clear(Tema.SuperficieElevada);
        using var fonteCheck = Icones.Fonte(9F);

        for (var n = 0; n < Visiveis && _topo + n < _itens.Count; n++)
        {
            var i = _topo + n;
            var r = new Rectangle(4, 4 + n * AlturaItem, Width - 8, AlturaItem - 2);
            if (i == _hover)
            {
                using var caminho = Tema.Arredondado(r, 6);
                using var fundo = new SolidBrush(Color.FromArgb(40, Tema.Acento));
                g.FillPath(fundo, caminho);
            }
            var cor = i == _selecionado ? Tema.Acento : Tema.Texto;
            TextRenderer.DrawText(g, _itens[i].ToString(), Font, new Rectangle(r.X + 10, r.Y, r.Width - 40, r.Height), cor,
                TextFormatFlags.VerticalCenter | TextFormatFlags.EndEllipsis | TextFormatFlags.NoPrefix);
            if (i == _selecionado)
            {
                TextRenderer.DrawText(g, Icones.Check, fonteCheck, new Rectangle(r.Right - 30, r.Y, 24, r.Height), Tema.Acento,
                    TextFormatFlags.HorizontalCenter | TextFormatFlags.VerticalCenter);
            }
        }

        if (_itens.Count > Visiveis)
        {
            // indicador de rolagem fino à direita
            var trilho = Height - 8;
            var tamanho = Math.Max(24, trilho * Visiveis / _itens.Count);
            var posicao = 4 + (trilho - tamanho) * _topo / Math.Max(1, _itens.Count - Visiveis);
            using var barra = new SolidBrush(Tema.Borda);
            g.FillRectangle(barra, Width - 4, posicao, 2, tamanho);
        }
    }
}

/// <summary>Cartão de escolha (substitui o radio button): ícone, título, descrição e check quando selecionado.</summary>
public class CartaoEscolha : Control
{
    private bool _selecionado;
    private bool _hover;
    private readonly string _icone;
    private string _titulo;
    private string _descricao;

    public event EventHandler? Escolhido;

    public CartaoEscolha(string icone, string titulo, string descricao)
    {
        _icone = icone;
        _titulo = titulo;
        _descricao = descricao;
        SetStyle(ControlStyles.AllPaintingInWmPaint | ControlStyles.UserPaint | ControlStyles.OptimizedDoubleBuffer | ControlStyles.ResizeRedraw | ControlStyles.Selectable, true);
        Height = 78;
        Cursor = Cursors.Hand;
        TabStop = true;
        MouseEnter += (s, e) => { _hover = true; Invalidate(); };
        MouseLeave += (s, e) => { _hover = false; Invalidate(); };
    }

    public string Titulo
    {
        get => _titulo;
        set { _titulo = value; Invalidate(); }
    }

    public string Descricao
    {
        get => _descricao;
        set { _descricao = value; Invalidate(); }
    }

    public bool Selecionado
    {
        get => _selecionado;
        set { _selecionado = value; Invalidate(); }
    }

    protected override void OnClick(EventArgs e)
    {
        base.OnClick(e);
        Focus();
        Escolhido?.Invoke(this, EventArgs.Empty);
    }

    protected override bool IsInputKey(Keys keyData) => keyData == Keys.Space || base.IsInputKey(keyData);

    protected override void OnKeyDown(KeyEventArgs e)
    {
        base.OnKeyDown(e);
        if (e.KeyCode is Keys.Space or Keys.Enter) Escolhido?.Invoke(this, EventArgs.Empty);
    }

    protected override void OnGotFocus(EventArgs e) { base.OnGotFocus(e); Invalidate(); }
    protected override void OnLostFocus(EventArgs e) { base.OnLostFocus(e); Invalidate(); }

    protected override void OnPaint(PaintEventArgs e)
    {
        var g = e.Graphics;
        g.SmoothingMode = SmoothingMode.AntiAlias;
        g.Clear(Parent?.BackColor ?? Tema.Superficie);

        var r = new Rectangle(1, 1, Width - 3, Height - 3);
        using var caminho = Tema.Arredondado(r, 10);
        var fundo = _selecionado ? Color.FromArgb(28, 88, 166, 255) : _hover ? Tema.SuperficieElevada : Color.FromArgb(14, 18, 24);
        using (var pincel = new SolidBrush(fundo)) g.FillPath(pincel, caminho);
        var borda = _selecionado ? Tema.Acento : Focused ? Color.FromArgb(90, Tema.Acento) : _hover ? Color.FromArgb(72, 80, 90) : Tema.Borda;
        using (var caneta = new Pen(borda, _selecionado ? 1.6F : 1F)) g.DrawPath(caneta, caminho);

        // bloco do ícone
        var caixaIcone = new Rectangle(14, (Height - 40) / 2, 40, 40);
        using (var caminhoIcone = Tema.Arredondado(caixaIcone, 9))
        {
            if (_selecionado)
            {
                using var gradiente = new LinearGradientBrush(caixaIcone, Tema.AcentoEscuro, Tema.Ciano, 45F);
                g.FillPath(gradiente, caminhoIcone);
            }
            else
            {
                using var pincelIcone = new SolidBrush(Tema.SuperficieElevada);
                g.FillPath(pincelIcone, caminhoIcone);
            }
        }
        using (var fonteIcone = Icones.Fonte(15F))
        {
            TextRenderer.DrawText(g, _icone, fonteIcone, caixaIcone, _selecionado ? Color.White : Tema.TextoSecundario,
                TextFormatFlags.HorizontalCenter | TextFormatFlags.VerticalCenter);
        }

        var x = caixaIcone.Right + 12;
        var larguraTexto = Width - x - 34;
        using var fonteTitulo = Tema.FonteSemibold(9.5F);
        using var fonteDesc = Tema.Fonte(8.5F);
        TextRenderer.DrawText(g, _titulo, fonteTitulo, new Rectangle(x, Height / 2 - 20, larguraTexto, 20),
            _selecionado ? Tema.Texto : Color.FromArgb(205, 212, 220), TextFormatFlags.EndEllipsis | TextFormatFlags.NoPrefix);
        TextRenderer.DrawText(g, _descricao, fonteDesc, new Rectangle(x, Height / 2, larguraTexto, 20),
            Tema.TextoSecundario, TextFormatFlags.EndEllipsis | TextFormatFlags.NoPrefix);

        // marcador no canto: círculo vazio ou check preenchido
        var marca = new Rectangle(Width - 28, 10, 16, 16);
        if (_selecionado)
        {
            using var pincelMarca = new SolidBrush(Tema.Acento);
            g.FillEllipse(pincelMarca, marca);
            using var fonteCheck = Icones.Fonte(7F);
            TextRenderer.DrawText(g, Icones.Check, fonteCheck, marca, Tema.Fundo, TextFormatFlags.HorizontalCenter | TextFormatFlags.VerticalCenter);
        }
        else
        {
            using var canetaMarca = new Pen(Tema.Borda, 1.3F);
            g.DrawEllipse(canetaMarca, marca);
        }
    }
}

/// <summary>Seletor segmentado (ex.: prioridade): trilho com opções lado a lado, cada uma com um ponto colorido.</summary>
public class SeletorSegmentado : Control
{
    private readonly List<(string Chave, string Texto, Color Cor)> _opcoes = new();
    private int _indice = -1;
    private int _hover = -1;

    public event EventHandler? SelecaoAlterada;

    public SeletorSegmentado()
    {
        SetStyle(ControlStyles.AllPaintingInWmPaint | ControlStyles.UserPaint | ControlStyles.OptimizedDoubleBuffer | ControlStyles.ResizeRedraw | ControlStyles.Selectable, true);
        Height = 40;
        Cursor = Cursors.Hand;
        TabStop = true;
        Font = Tema.FonteSemibold(9F);
        MouseLeave += (s, e) => { _hover = -1; Invalidate(); };
    }

    public void DefinirOpcoes(IEnumerable<(string Chave, string Texto, Color Cor)> opcoes, string? selecionada)
    {
        _opcoes.Clear();
        _opcoes.AddRange(opcoes);
        _indice = _opcoes.FindIndex(o => o.Chave == selecionada);
        Invalidate();
    }

    public string? ChaveSelecionada => _indice >= 0 ? _opcoes[_indice].Chave : null;

    private int IndiceEm(int x) => _opcoes.Count == 0 ? -1 : Math.Clamp((x - 3) * _opcoes.Count / Math.Max(1, Width - 6), 0, _opcoes.Count - 1);

    protected override void OnMouseMove(MouseEventArgs e)
    {
        base.OnMouseMove(e);
        var i = IndiceEm(e.X);
        if (i != _hover) { _hover = i; Invalidate(); }
    }

    protected override void OnMouseClick(MouseEventArgs e)
    {
        base.OnMouseClick(e);
        Focus();
        Selecionar(IndiceEm(e.X));
    }

    protected override bool IsInputKey(Keys keyData) => keyData is Keys.Left or Keys.Right || base.IsInputKey(keyData);

    protected override void OnKeyDown(KeyEventArgs e)
    {
        base.OnKeyDown(e);
        if (e.KeyCode == Keys.Left) Selecionar(Math.Max(0, _indice - 1));
        else if (e.KeyCode == Keys.Right) Selecionar(Math.Min(_opcoes.Count - 1, _indice + 1));
    }

    private void Selecionar(int i)
    {
        if (i < 0 || i == _indice) return;
        _indice = i;
        Invalidate();
        SelecaoAlterada?.Invoke(this, EventArgs.Empty);
    }

    protected override void OnGotFocus(EventArgs e) { base.OnGotFocus(e); Invalidate(); }
    protected override void OnLostFocus(EventArgs e) { base.OnLostFocus(e); Invalidate(); }

    protected override void OnPaint(PaintEventArgs e)
    {
        var g = e.Graphics;
        g.SmoothingMode = SmoothingMode.AntiAlias;
        g.Clear(Parent?.BackColor ?? Tema.Superficie);

        var trilho = new Rectangle(2, 2, Width - 5, Height - 5);
        using (var caminho = Tema.Arredondado(trilho, 9))
        {
            using var fundo = new SolidBrush(Color.FromArgb(14, 18, 24));
            g.FillPath(fundo, caminho);
            using var borda = new Pen(Focused ? Color.FromArgb(120, Tema.Acento) : Tema.Borda);
            g.DrawPath(borda, caminho);
        }
        if (_opcoes.Count == 0) return;

        var largura = (trilho.Width - 6) / (float)_opcoes.Count;
        for (var i = 0; i < _opcoes.Count; i++)
        {
            var (_, texto, cor) = _opcoes[i];
            var r = Rectangle.Round(new RectangleF(trilho.X + 3 + i * largura, trilho.Y + 3, largura - 2, trilho.Height - 6));

            if (i == _indice)
            {
                using var caminhoSel = Tema.Arredondado(r, 7);
                using var fundoSel = new SolidBrush(Color.FromArgb(46, cor));
                g.FillPath(fundoSel, caminhoSel);
                using var bordaSel = new Pen(Color.FromArgb(150, cor));
                g.DrawPath(bordaSel, caminhoSel);
            }
            else if (i == _hover)
            {
                using var caminhoHover = Tema.Arredondado(r, 7);
                using var fundoHover = new SolidBrush(Tema.SuperficieElevada);
                g.FillPath(fundoHover, caminhoHover);
            }

            var tamanhoTexto = TextRenderer.MeasureText(texto, Font);
            var inicio = r.X + (r.Width - tamanhoTexto.Width - 14) / 2;
            using (var ponto = new SolidBrush(i == _indice ? cor : Color.FromArgb(150, cor)))
            {
                g.FillEllipse(ponto, inicio, r.Y + r.Height / 2 - 4, 8, 8);
            }
            TextRenderer.DrawText(g, texto, Font, new Point(inicio + 14, r.Y + (r.Height - tamanhoTexto.Height) / 2),
                i == _indice ? Tema.Texto : Tema.TextoSecundario);
        }
    }
}

/// <summary>Campo numérico arredondado: digita o número ou usa − / + à direita; unidade (min, s) ao lado do valor.</summary>
public class CampoNumerico : CampoArredondado
{
    private readonly TextBox _caixa;
    private readonly string _unidade;
    private readonly int _minimo;
    private readonly int _maximo;
    private int _hoverBotao; // -1 menos, 1 mais, 0 nenhum

    public CampoNumerico(int minimo, int maximo, int valor, string unidade)
    {
        _minimo = minimo;
        _maximo = maximo;
        _unidade = unidade;
        _caixa = new TextBox
        {
            BorderStyle = BorderStyle.None,
            BackColor = Color.FromArgb(14, 18, 24),
            ForeColor = Tema.Texto,
            Font = Tema.FonteSemibold(10.5F),
            Text = Math.Clamp(valor, minimo, maximo).ToString()
        };
        _caixa.KeyPress += (s, e) => { if (!char.IsControl(e.KeyChar) && !char.IsDigit(e.KeyChar)) e.Handled = true; };
        _caixa.KeyDown += (s, e) =>
        {
            if (e.KeyCode == Keys.Up) { Somar(1); e.Handled = true; }
            else if (e.KeyCode == Keys.Down) { Somar(-1); e.Handled = true; }
        };
        _caixa.GotFocus += (s, e) => { Focado = true; Invalidate(); };
        _caixa.LostFocus += (s, e) => { Focado = false; Valor = Valor; Invalidate(); };
        _caixa.TextChanged += (s, e) => { LimparErro(); Invalidate(); };
        // Só com o campo em foco: rolar a página por cima dele não pode mudar o valor sem querer.
        _caixa.MouseWheel += (s, e) => { if (_caixa.Focused) Somar(Math.Sign(e.Delta)); };
        Controls.Add(_caixa);
        Cursor = Cursors.Default;
    }

    /// <summary>Valor atual, sempre dentro do mínimo/máximo.</summary>
    public int Valor
    {
        get => int.TryParse(_caixa.Text, out var v) ? Math.Clamp(v, _minimo, _maximo) : _minimo;
        set => _caixa.Text = Math.Clamp(value, _minimo, _maximo).ToString();
    }

    private void Somar(int passo) => Valor += passo;

    private Rectangle BotaoMenos => new(Width - 76, 6, 32, Height - 13);
    private Rectangle BotaoMais => new(Width - 40, 6, 32, Height - 13);

    protected override void OnLayout(LayoutEventArgs levent)
    {
        base.OnLayout(levent);
        if (_caixa is null) return;
        var altura = _caixa.PreferredHeight;
        var largura = Math.Max(24, TextRenderer.MeasureText(_maximo.ToString(), _caixa.Font).Width + 4);
        _caixa.Bounds = new Rectangle(14, (Height - altura) / 2, largura, altura);
    }

    protected override void OnMouseMove(MouseEventArgs e)
    {
        base.OnMouseMove(e);
        var novo = BotaoMenos.Contains(e.Location) ? -1 : BotaoMais.Contains(e.Location) ? 1 : 0;
        Cursor = novo != 0 ? Cursors.Hand : Cursors.Default;
        if (novo != _hoverBotao) { _hoverBotao = novo; Invalidate(); }
    }

    protected override void OnMouseLeave(EventArgs e)
    {
        base.OnMouseLeave(e);
        _hoverBotao = 0;
        Invalidate();
    }

    protected override void OnMouseDown(MouseEventArgs e)
    {
        base.OnMouseDown(e);
        if (BotaoMenos.Contains(e.Location)) Somar(-1);
        else if (BotaoMais.Contains(e.Location)) Somar(1);
        _caixa.Focus();
    }

    protected override void OnPaint(PaintEventArgs e)
    {
        DesenharMoldura(e.Graphics);
        var g = e.Graphics;
        using (var fonteUnidade = Tema.Fonte(9F))
        {
            TextRenderer.DrawText(g, _unidade, fonteUnidade, new Rectangle(_caixa.Right + 4, 0, 60, Height), Tema.TextoSecundario,
                TextFormatFlags.VerticalCenter | TextFormatFlags.NoPrefix);
        }

        foreach (var (r, texto, hover) in new[] { (BotaoMenos, "−", _hoverBotao == -1), (BotaoMais, "+", _hoverBotao == 1) })
        {
            using var caminho = Tema.Arredondado(r, 6);
            using var fundo = new SolidBrush(hover ? Color.FromArgb(50, Tema.Acento) : Tema.SuperficieElevada);
            g.FillPath(fundo, caminho);
            using var fonte = Tema.FonteSemibold(11F);
            TextRenderer.DrawText(g, texto, fonte, r, hover ? Tema.Acento : Tema.TextoSecundario,
                TextFormatFlags.HorizontalCenter | TextFormatFlags.VerticalCenter);
        }
    }
}

/// <summary>Chave liga/desliga (substitui o checkbox): trilho arredondado com bolinha e texto ao lado.</summary>
[DefaultEvent(nameof(Alterado))]
public class InterruptorModerno : Control
{
    private bool _ligado;
    private bool _hover;

    public event EventHandler? Alterado;

    public InterruptorModerno(string texto, bool ligado = false)
    {
        Text = texto;
        _ligado = ligado;
        SetStyle(ControlStyles.AllPaintingInWmPaint | ControlStyles.UserPaint | ControlStyles.OptimizedDoubleBuffer | ControlStyles.ResizeRedraw | ControlStyles.Selectable, true);
        Height = 40;
        Cursor = Cursors.Hand;
        TabStop = true;
        Font = Tema.Fonte(9.5F);
        MouseEnter += (s, e) => { _hover = true; Invalidate(); };
        MouseLeave += (s, e) => { _hover = false; Invalidate(); };
    }

    public bool Ligado
    {
        get => _ligado;
        set
        {
            if (_ligado == value) return;
            _ligado = value;
            Invalidate();
            Alterado?.Invoke(this, EventArgs.Empty);
        }
    }

    protected override void OnClick(EventArgs e) { base.OnClick(e); Focus(); Ligado = !Ligado; }
    protected override bool IsInputKey(Keys keyData) => keyData == Keys.Space || base.IsInputKey(keyData);
    protected override void OnKeyDown(KeyEventArgs e) { base.OnKeyDown(e); if (e.KeyCode == Keys.Space) Ligado = !Ligado; }
    protected override void OnGotFocus(EventArgs e) { base.OnGotFocus(e); Invalidate(); }
    protected override void OnLostFocus(EventArgs e) { base.OnLostFocus(e); Invalidate(); }

    protected override void OnPaint(PaintEventArgs e)
    {
        var g = e.Graphics;
        g.SmoothingMode = SmoothingMode.AntiAlias;
        g.Clear(Parent?.BackColor ?? Tema.Superficie);

        var trilho = new Rectangle(2, (Height - 22) / 2, 42, 22);
        using (var caminho = Tema.Arredondado(trilho, 11))
        {
            if (_ligado)
            {
                using var gradiente = new LinearGradientBrush(trilho, Tema.AcentoEscuro, Tema.Ciano, 0F);
                g.FillPath(gradiente, caminho);
            }
            else
            {
                using var fundo = new SolidBrush(_hover ? Tema.SuperficieElevada : Color.FromArgb(14, 18, 24));
                g.FillPath(fundo, caminho);
                using var borda = new Pen(Focused ? Tema.Acento : Tema.Borda);
                g.DrawPath(borda, caminho);
            }
        }

        var bolinha = new Rectangle(_ligado ? trilho.Right - 19 : trilho.X + 3, trilho.Y + 3, 16, 16);
        using (var pincel = new SolidBrush(_ligado ? Color.White : Tema.TextoSecundario)) g.FillEllipse(pincel, bolinha);

        TextRenderer.DrawText(g, Text, Font, new Rectangle(trilho.Right + 10, 0, Width - trilho.Right - 10, Height),
            _ligado ? Tema.Texto : Tema.TextoSecundario, TextFormatFlags.VerticalCenter | TextFormatFlags.EndEllipsis);
    }
}

/// <summary>
/// Manda a rodinha do mouse pro controle que está debaixo do ponteiro, não
/// pro que tem o foco. Sem isso, depois de clicar num item do menu lateral
/// (Configurações, Network) a rodinha ia pro botão do menu e a página não
/// rolava -- só pela barra lateral. Do controle sob o ponteiro a mensagem
/// sobe pelos pais até a área com rolagem.
/// </summary>
public sealed class RoteadorRolagem : IMessageFilter
{
    private const int WmMouseWheel = 0x020A;
    private const int WmMouseHWheel = 0x020E;
    private static bool _instalado;

    [System.Runtime.InteropServices.DllImport("user32.dll")]
    private static extern IntPtr WindowFromPoint(Point ponto);

    [System.Runtime.InteropServices.DllImport("user32.dll")]
    private static extern IntPtr SendMessage(IntPtr hwnd, int msg, IntPtr wParam, IntPtr lParam);

    /// <summary>Registra uma vez só, pro processo inteiro.</summary>
    public static void Instalar()
    {
        if (_instalado) return;
        _instalado = true;
        Application.AddMessageFilter(new RoteadorRolagem());
    }

    public bool PreFilterMessage(ref Message m)
    {
        if (m.Msg is not (WmMouseWheel or WmMouseHWheel)) return false;

        var sobCursor = WindowFromPoint(Cursor.Position);
        // Já está indo pro controle certo, ou o ponteiro está fora das nossas janelas.
        if (sobCursor == IntPtr.Zero || sobCursor == m.HWnd || Control.FromChildHandle(sobCursor) == null) return false;

        SendMessage(sobCursor, m.Msg, m.WParam, m.LParam);
        return true;
    }
}
