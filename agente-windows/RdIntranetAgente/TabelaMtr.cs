using System.Drawing;
using System.Drawing.Drawing2D;
using System.Windows.Forms;

namespace RdIntranetAgente;

/// <summary>
/// Tabela do MTR desenhada: uma linha por salto com as colunas do WinMTR
/// (Nr, Host, Perda %, Enviados, Recebidos, Melhor, Média, Pior, Último) e um
/// minigráfico das últimas respostas -- barra por pacote, vermelha quando
/// perdido. A altura acompanha a quantidade de saltos.
/// </summary>
public sealed class TabelaMtr : Control
{
    private const int AlturaCabecalho = 34;
    private const int AlturaLinha = 38;
    private List<SaltoMtr> _saltos = new();
    private int _hover = -1;

    public event EventHandler? AlturaAlterada;

    /// <summary>Altura pra mostrar todos os saltos -- quem hospeda a tabela ajusta o espaço dela por aqui (docked, o Height é do pai).</summary>
    public int AlturaNecessaria { get; private set; } = AlturaCabecalho + AlturaLinha + 2;

    private static readonly (string Titulo, float Peso, bool Direita)[] Colunas =
    {
        ("Nr", 0.45F, false), ("Host", 2.8F, false), ("Perda", 0.95F, true), ("Env.", 0.65F, true), ("Rec.", 0.65F, true),
        ("Melhor", 0.95F, true), ("Média", 0.95F, true), ("Pior", 0.95F, true), ("Último", 0.95F, true), ("Últimas respostas", 2.2F, false)
    };

    public TabelaMtr()
    {
        SetStyle(ControlStyles.AllPaintingInWmPaint | ControlStyles.UserPaint | ControlStyles.OptimizedDoubleBuffer | ControlStyles.ResizeRedraw, true);
        BackColor = Tema.Superficie;
        Height = AlturaCabecalho + AlturaLinha;
        MouseLeave += (s, e) => { _hover = -1; Invalidate(); };
    }

    public void Definir(List<SaltoMtr> saltos)
    {
        _saltos = saltos;
        var altura = AlturaCabecalho + Math.Max(1, saltos.Count) * AlturaLinha + 2;
        if (altura != AlturaNecessaria)
        {
            AlturaNecessaria = altura;
            AlturaAlterada?.Invoke(this, EventArgs.Empty);
        }
        Invalidate();
    }

    protected override void OnMouseMove(MouseEventArgs e)
    {
        base.OnMouseMove(e);
        var i = e.Y < AlturaCabecalho ? -1 : (e.Y - AlturaCabecalho) / AlturaLinha;
        if (i >= _saltos.Count) i = -1;
        if (i != _hover) { _hover = i; Invalidate(); }
    }

    private List<Rectangle> Celulas(int y, int altura)
    {
        var total = Colunas.Sum(c => c.Peso);
        var largura = Width - 24;
        var x = 12F;
        var celulas = new List<Rectangle>();
        foreach (var c in Colunas)
        {
            var w = largura * c.Peso / total;
            celulas.Add(Rectangle.Round(new RectangleF(x, y, w - 8, altura)));
            x += w;
        }
        return celulas;
    }

    protected override void OnPaint(PaintEventArgs e)
    {
        var g = e.Graphics;
        g.SmoothingMode = SmoothingMode.AntiAlias;
        g.Clear(Parent?.BackColor ?? Tema.Superficie);

        using (var caminho = Tema.Arredondado(new Rectangle(0, 0, Width - 1, Height - 1), 10))
        {
            using var fundo = new SolidBrush(Color.FromArgb(10, 13, 18));
            g.FillPath(fundo, caminho);
            using var borda = new Pen(Tema.BordaSuave);
            g.DrawPath(borda, caminho);
        }

        using var fonteCabecalho = Tema.FonteSemibold(8F);
        using var fonte = Tema.Fonte(9F);
        using var fonteMono = Tema.FonteMono(9F);
        using var fonteSub = Tema.Fonte(7.5F);
        using var fontePilula = Tema.FonteSemibold(8F);

        var cab = Celulas(0, AlturaCabecalho);
        for (var i = 0; i < Colunas.Length; i++)
        {
            TextRenderer.DrawText(g, Colunas[i].Titulo.ToUpperInvariant(), fonteCabecalho, cab[i], Tema.TextoSecundario,
                TextFormatFlags.VerticalCenter | TextFormatFlags.EndEllipsis | (Colunas[i].Direita ? TextFormatFlags.Right : TextFormatFlags.Left));
        }
        using (var linhaDivisoria = new Pen(Tema.BordaSuave)) g.DrawLine(linhaDivisoria, 1, AlturaCabecalho - 1, Width - 2, AlturaCabecalho - 1);

        if (_saltos.Count == 0)
        {
            TextRenderer.DrawText(g, "Informe o destino e clique em Iniciar. Os saltos aparecem aqui em tempo real.", fonte,
                new Rectangle(12, AlturaCabecalho, Width - 24, AlturaLinha), Tema.TextoApagado, TextFormatFlags.VerticalCenter);
            return;
        }

        var maiorLatencia = Math.Max(20, _saltos.SelectMany(s => s.Historico).DefaultIfEmpty(0).Max());

        for (var n = 0; n < _saltos.Count; n++)
        {
            var s = _saltos[n];
            var y = AlturaCabecalho + n * AlturaLinha;
            if (n == _hover)
            {
                using var destaque = new SolidBrush(Color.FromArgb(20, 88, 166, 255));
                g.FillRectangle(destaque, 1, y, Width - 2, AlturaLinha);
            }
            else if (n % 2 == 1)
            {
                using var zebra = new SolidBrush(Color.FromArgb(6, 255, 255, 255));
                g.FillRectangle(zebra, 1, y, Width - 2, AlturaLinha);
            }

            var c = Celulas(y, AlturaLinha);
            var semResposta = s.Endereco == null;
            var corTexto = semResposta ? Tema.TextoApagado : Tema.Texto;
            var direita = TextFormatFlags.VerticalCenter | TextFormatFlags.Right;

            TextRenderer.DrawText(g, s.Numero.ToString(), fonteMono, c[0], Tema.TextoSecundario, TextFormatFlags.VerticalCenter);

            // host: nome em cima, IP embaixo (quando o nome foi resolvido)
            if (!semResposta && !string.IsNullOrEmpty(s.Nome))
            {
                TextRenderer.DrawText(g, s.Nome, fonte, new Rectangle(c[1].X, y + 3, c[1].Width, 18), corTexto, TextFormatFlags.EndEllipsis | TextFormatFlags.NoPrefix);
                TextRenderer.DrawText(g, s.Endereco!.ToString(), fonteSub, new Rectangle(c[1].X, y + 20, c[1].Width, 15), Tema.TextoSecundario, TextFormatFlags.EndEllipsis);
            }
            else
            {
                TextRenderer.DrawText(g, s.Host, semResposta ? fonte : fonteMono, c[1], corTexto, TextFormatFlags.VerticalCenter | TextFormatFlags.EndEllipsis | TextFormatFlags.NoPrefix);
            }

            // perda: pílula colorida
            var perda = s.PerdaPercentual;
            var corPerda = s.Enviados == 0 ? Tema.TextoSecundario : perda == 0 ? Tema.Sucesso : perda < 20 ? Tema.Alerta : Tema.Perigo;
            var textoPerda = $"{perda:0}%";
            var tamanho = TextRenderer.MeasureText(textoPerda, fontePilula);
            var pilula = new Rectangle(c[2].Right - tamanho.Width - 14, y + (AlturaLinha - 20) / 2, tamanho.Width + 14, 20);
            using (var caminhoPilula = Tema.Arredondado(pilula, 10))
            using (var fundoPilula = new SolidBrush(Color.FromArgb(40, corPerda)))
            {
                g.FillPath(fundoPilula, caminhoPilula);
            }
            TextRenderer.DrawText(g, textoPerda, fontePilula, pilula, corPerda, TextFormatFlags.HorizontalCenter | TextFormatFlags.VerticalCenter);

            TextRenderer.DrawText(g, s.Enviados.ToString(), fonteMono, c[3], Tema.TextoSecundario, direita);
            TextRenderer.DrawText(g, s.Recebidos.ToString(), fonteMono, c[4], Tema.TextoSecundario, direita);
            var tem = s.Recebidos > 0;
            TextRenderer.DrawText(g, tem ? s.Melhor.ToString() : "-", fonteMono, c[5], CorLatencia(tem ? s.Melhor : -1), direita);
            TextRenderer.DrawText(g, tem ? $"{s.Media:0}" : "-", fonteMono, c[6], CorLatencia(tem ? (long)s.Media : -1), direita);
            TextRenderer.DrawText(g, tem ? s.Pior.ToString() : "-", fonteMono, c[7], CorLatencia(tem ? s.Pior : -1), direita);
            TextRenderer.DrawText(g, s.Ultimo >= 0 ? s.Ultimo.ToString() : "-", fonteMono, c[8], CorLatencia(s.Ultimo), direita);

            DesenharHistorico(g, s.Historico, c[9], maiorLatencia);
        }
    }

    private static Color CorLatencia(long ms) => ms < 0 ? Tema.TextoApagado : ms < 80 ? Tema.Texto : ms < 200 ? Tema.Alerta : Tema.Perigo;

    /// <summary>Uma barra por pacote (mais recente à direita); pacote perdido = barra vermelha cheia.</summary>
    private static void DesenharHistorico(Graphics g, List<long> historico, Rectangle area, long maior)
    {
        var r = new Rectangle(area.X, area.Y + 8, area.Width, area.Height - 16);
        using (var fundo = new SolidBrush(Color.FromArgb(18, 23, 30))) g.FillRectangle(fundo, r);
        if (historico.Count == 0) return;

        const int larguraBarra = 3, espaco = 1;
        var cabem = Math.Max(1, r.Width / (larguraBarra + espaco));
        var ultimos = historico.Skip(Math.Max(0, historico.Count - cabem)).ToList();
        var x = r.Right - ultimos.Count * (larguraBarra + espaco);
        foreach (var ms in ultimos)
        {
            if (ms < 0)
            {
                using var perdido = new SolidBrush(Tema.Perigo);
                g.FillRectangle(perdido, x, r.Y, larguraBarra, r.Height);
            }
            else
            {
                var altura = Math.Max(2, (int)(r.Height * Math.Min(1.0, (double)ms / maior)));
                using var barra = new SolidBrush(ms < 80 ? Tema.Ciano : ms < 200 ? Tema.Alerta : Tema.Perigo);
                g.FillRectangle(barra, x, r.Bottom - altura, larguraBarra, altura);
            }
            x += larguraBarra + espaco;
        }
    }
}
