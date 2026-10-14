using System.Collections;
using System.Globalization;
using System.Text.RegularExpressions;
using System.Windows.Forms;

namespace RdIntranetAgente;

/// <summary>
/// Ordenação das listas do painel (clique no cabeçalho). Entende o que as
/// telas mostram: números com unidade ("4.6%", "505 MB", "1.7 MB/s",
/// "0.3 Mbps", "1.20 GB"), datas ("14/10 10:22:48", "14/10/26 10:23"),
/// IP:porta, níveis (Crítico > Suspeito > Atenção > Normal) e severidade
/// (CRITICAL > WARNING > INFO). O resto vai em ordem alfabética.
/// </summary>
public sealed class ComparadorLista : IComparer
{
    public int Coluna { get; }
    public bool Crescente { get; }

    public ComparadorLista(int coluna, bool crescente)
    {
        Coluna = coluna;
        Crescente = crescente;
    }

    private static readonly Dictionary<string, int> Niveis = new(StringComparer.OrdinalIgnoreCase)
    {
        ["CRÍTICO"] = 4, ["CRITICAL"] = 4, ["Suspeito"] = 3, ["WARNING"] = 3, ["Atenção"] = 2, ["INFO"] = 1, ["Normal"] = 1, ["Microsoft"] = 0
    };

    private static readonly Regex Numero = new(@"^\s*(-?[\d,]*\.?\d+)\s*(%|B|KB|MB|GB|TB|KB/s|MB/s|GB/s|Kbps|Mbps|Gbps|ms|s)?\s*$", RegexOptions.IgnoreCase);
    private static readonly string[] FormatosData = { "dd/MM HH:mm:ss", "dd/MM/yy HH:mm", "dd/MM/yyyy HH:mm:ss", "dd/MM/yyyy HH:mm", "dd/MM HH:mm" };

    public int Compare(object? x, object? y)
    {
        var a = Texto(x as ListViewItem);
        var b = Texto(y as ListViewItem);
        var resultado = Comparar(a, b);
        return Crescente ? resultado : -resultado;
    }

    private string Texto(ListViewItem? item) =>
        item == null ? "" : Coluna == 0 ? item.Text : Coluna < item.SubItems.Count ? item.SubItems[Coluna].Text : "";

    private static int Comparar(string a, string b)
    {
        if (Niveis.TryGetValue(a.Trim(), out var na) && Niveis.TryGetValue(b.Trim(), out var nb)) return na.CompareTo(nb);

        if (ValorNumerico(a) is { } va && ValorNumerico(b) is { } vb) return va.CompareTo(vb);

        if (DateTime.TryParseExact(a.Trim(), FormatosData, CultureInfo.InvariantCulture, DateTimeStyles.None, out var da)
            && DateTime.TryParseExact(b.Trim(), FormatosData, CultureInfo.InvariantCulture, DateTimeStyles.None, out var db)) return da.CompareTo(db);

        if (ChaveIp(a) is { } ia && ChaveIp(b) is { } ib) return string.CompareOrdinal(ia, ib);

        return string.Compare(a, b, CultureInfo.GetCultureInfo("pt-BR"), CompareOptions.IgnoreCase);
    }

    private static double? ValorNumerico(string texto)
    {
        var m = Numero.Match(texto);
        if (!m.Success || !double.TryParse(m.Groups[1].Value.Replace(",", ""), NumberStyles.Float, CultureInfo.InvariantCulture, out var v)) return null;
        var multiplicador = m.Groups[2].Value.ToUpperInvariant() switch
        {
            "KB" or "KB/S" or "KBPS" => 1e3,
            "MB" or "MB/S" or "MBPS" => 1e6,
            "GB" or "GB/S" or "GBPS" => 1e9,
            "TB" => 1e12,
            "S" => 1000,
            _ => 1
        };
        return v * multiplicador;
    }

    /// <summary>"10.10.2.140:1521" -> chave que ordena como número (octeto a octeto, depois porta).</summary>
    private static string? ChaveIp(string texto)
    {
        var m = Regex.Match(texto.Trim(), @"^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})(?::(\d{1,5}))?");
        if (!m.Success) return null;
        return string.Concat(Enumerable.Range(1, 4).Select(i => int.Parse(m.Groups[i].Value).ToString("D3")))
            + (m.Groups[5].Success ? int.Parse(m.Groups[5].Value).ToString("D5") : "00000");
    }
}
