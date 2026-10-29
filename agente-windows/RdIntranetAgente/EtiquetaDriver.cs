using System.Drawing;
using System.Drawing.Drawing2D;
using System.Drawing.Printing;
using System.Drawing.Text;
using System.Management;
using System.Text.Json.Serialization;
using System.Text.RegularExpressions;

namespace RdIntranetAgente;

/// <summary>
/// Imprime a etiqueta de patrimônio DESENHANDO a página pelo driver do
/// Windows, em vez de mandar ZPL cru. É o caminho das impressoras que não
/// falam ZPL -- Argox OS-214 plus (PPLA/PPLB), Elgin, TSC... --: o driver
/// do fabricante converte a página pra linguagem dela. O layout (posições
/// em mm) vem pronto do portal (EtiquetaService::gerarLayout), o mesmo
/// cálculo do ZPL e da pré-visualização.
/// </summary>
internal static class EtiquetaDriver
{
    public const string ModoAuto = "auto";
    public const string ModoZpl = "zpl";
    public const string ModoDriver = "driver";

    private static readonly Regex FalaZpl = new(@"zebra|zdesigner|\bzpl\b", RegexOptions.IgnoreCase);
    private static readonly Regex NaoFalaZpl = new(@"argox|ppla|pplb|elgin|\btsc\b|datamax|godex|bematech|brother|dymo|sato", RegexOptions.IgnoreCase);

    /// <summary>
    /// "zpl" ou "driver". No automático: Zebra/ZDesigner = ZPL; marcas conhecidas que não falam ZPL =
    /// driver; o resto continua ZPL (comportamento de sempre -- tem Zebra instalada como "Generic / Text Only").
    /// </summary>
    public static string ModoEfetivo(Config config)
    {
        if (config.ModoEtiqueta is ModoZpl or ModoDriver) return config.ModoEtiqueta;

        var texto = config.ImpressoraEtiqueta + " " + DriverDaImpressora(config.ImpressoraEtiqueta);
        if (FalaZpl.IsMatch(texto)) return ModoZpl;
        return NaoFalaZpl.IsMatch(texto) ? ModoDriver : ModoZpl;
    }

    private static string DriverDaImpressora(string nome)
    {
        try
        {
            using var busca = new ManagementObjectSearcher("SELECT Name, DriverName FROM Win32_Printer");
            foreach (ManagementObject impressora in busca.Get())
            {
                if (string.Equals(impressora["Name"] as string, nome, StringComparison.OrdinalIgnoreCase))
                {
                    return impressora["DriverName"] as string ?? "";
                }
            }
        }
        catch
        {
            // WMI fora do ar -- decide só pelo nome
        }

        return "";
    }

    public static (bool sucesso, string mensagem) Imprimir(string impressora, LayoutEtiqueta layout, string? arquivoSaida = null)
    {
        if (layout.LarguraMm <= 0 || layout.AlturaMm <= 0)
        {
            return (false, "Layout da etiqueta inválido.");
        }

        using var qr = CarregarQr(layout.Qr?.PngBase64);
        using var documento = new PrintDocument
        {
            DocumentName = "Etiqueta RD Intranet",
            PrintController = new StandardPrintController() // sem a janelinha "Imprimindo página 1..."
        };

        documento.PrinterSettings.PrinterName = impressora;
        if (!documento.PrinterSettings.IsValid)
        {
            return (false, $"A impressora \"{impressora}\" não existe neste Windows -- confira em Configurações.");
        }

        if (arquivoSaida != null)
        {
            documento.PrinterSettings.PrintToFile = true;
            documento.PrinterSettings.PrintFileName = arquivoSaida;
        }

        // Tamanho da página = tamanho da etiqueta (centésimos de polegada), sem margem.
        documento.DefaultPageSettings.PaperSize = new PaperSize("Etiqueta RD", Centesimos(layout.LarguraMm), Centesimos(layout.AlturaMm));
        documento.DefaultPageSettings.Landscape = false;
        documento.DefaultPageSettings.Margins = new Margins(0, 0, 0, 0);
        documento.OriginAtMargins = false;

        Exception? erroDesenho = null;
        documento.PrintPage += (_, e) =>
        {
            try
            {
                Desenhar(e, layout, qr);
            }
            catch (Exception ex)
            {
                erroDesenho = ex;
            }

            e.HasMorePages = false;
        };

        documento.Print();

        return erroDesenho == null
            ? (true, "Enviado pra impressora (pelo driver do Windows).")
            : (false, "Falha ao desenhar a etiqueta: " + erroDesenho.Message);
    }

    private static void Desenhar(PrintPageEventArgs e, LayoutEtiqueta layout, Image? qr)
    {
        var g = e.Graphics!;
        g.PageUnit = GraphicsUnit.Millimeter;
        // A origem do Graphics é a área imprimível; volta pro canto físico da etiqueta (margem física em 1/100").
        g.TranslateTransform(-e.PageSettings.HardMarginX * 0.254f, -e.PageSettings.HardMarginY * 0.254f);

        // Térmica é preto ou branco: sem antisserrilhado (cinza vira pontilhado borrado).
        g.SmoothingMode = SmoothingMode.None;
        g.TextRenderingHint = TextRenderingHint.SingleBitPerPixelGridFit;
        g.InterpolationMode = InterpolationMode.NearestNeighbor;
        g.PixelOffsetMode = PixelOffsetMode.Half;

        if (qr != null && layout.Qr != null)
        {
            g.DrawImage(qr, new RectangleF(layout.Qr.XMm, layout.Qr.YMm, layout.Qr.LadoMm, layout.Qr.LadoMm));
        }

        var familia = FamiliaFonte();
        using var formato = new StringFormat(StringFormatFlags.LineLimit) { Trimming = StringTrimming.Character };

        foreach (var texto in layout.Textos)
        {
            if (string.IsNullOrWhiteSpace(texto.Texto)) continue;

            var linhas = Math.Max(1, texto.Linhas);
            var area = new RectangleF(texto.XMm, texto.YMm, texto.LarguraMm, (texto.FonteMm + texto.EspacoLinhaMm) * linhas);
            using var fonte = new Font(familia, texto.FonteMm, texto.Campo == "codigo" ? FontStyle.Bold : FontStyle.Regular, GraphicsUnit.Millimeter);
            g.DrawString(texto.Texto, fonte, Brushes.Black, area, formato);
        }
    }

    /// <summary>Arial Narrow fica mais perto da fonte condensada do ZPL (código de patrimônio inteiro na coluna); sem ela, Arial.</summary>
    private static string FamiliaFonte()
    {
        using var instaladas = new InstalledFontCollection();
        return instaladas.Families.Any(f => f.Name.Equals("Arial Narrow", StringComparison.OrdinalIgnoreCase)) ? "Arial Narrow" : "Arial";
    }

    private static Image? CarregarQr(string? pngBase64)
    {
        if (string.IsNullOrWhiteSpace(pngBase64)) return null;

        try
        {
            return Image.FromStream(new MemoryStream(Convert.FromBase64String(pngBase64)));
        }
        catch
        {
            return null; // QR corrompido não impede o resto da etiqueta
        }
    }

    private static int Centesimos(float mm) => (int)Math.Round(mm / 25.4 * 100);
}

public class LayoutEtiqueta
{
    [JsonPropertyName("largura_mm")] public float LarguraMm { get; set; }
    [JsonPropertyName("altura_mm")] public float AlturaMm { get; set; }
    [JsonPropertyName("qr")] public QrEtiqueta? Qr { get; set; }
    [JsonPropertyName("textos")] public List<TextoEtiqueta> Textos { get; set; } = new();
}

public class QrEtiqueta
{
    [JsonPropertyName("x_mm")] public float XMm { get; set; }
    [JsonPropertyName("y_mm")] public float YMm { get; set; }
    [JsonPropertyName("lado_mm")] public float LadoMm { get; set; }
    [JsonPropertyName("png_base64")] public string? PngBase64 { get; set; }
}

public class TextoEtiqueta
{
    [JsonPropertyName("campo")] public string Campo { get; set; } = "";
    [JsonPropertyName("texto")] public string Texto { get; set; } = "";
    [JsonPropertyName("x_mm")] public float XMm { get; set; }
    [JsonPropertyName("y_mm")] public float YMm { get; set; }
    [JsonPropertyName("largura_mm")] public float LarguraMm { get; set; }
    [JsonPropertyName("fonte_mm")] public float FonteMm { get; set; }
    [JsonPropertyName("linhas")] public int Linhas { get; set; } = 1;
    [JsonPropertyName("espaco_linha_mm")] public float EspacoLinhaMm { get; set; } = 0.8f;
}
