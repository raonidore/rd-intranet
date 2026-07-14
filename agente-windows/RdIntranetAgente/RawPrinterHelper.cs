using System.Runtime.InteropServices;
using System.Text;

namespace RdIntranetAgente;

/// <summary>
/// Envia bytes crus (RAW) direto pra fila de impressão do Windows, sem
/// passar pelo driver "desenhar página" -- é assim que se manda ZPL pra
/// uma impressora Zebra (a própria impressora interpreta o texto, o
/// driver só entrega os bytes sem reprocessar). Padrão clássico da
/// Microsoft (KB322091 / winspool.drv), usado há décadas em sistemas de
/// ponto-de-venda e etiqueta no Windows.
/// </summary>
internal static class RawPrinterHelper
{
    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Ansi)]
    private struct DOCINFOA
    {
        [MarshalAs(UnmanagedType.LPStr)] public string pDocName;
        [MarshalAs(UnmanagedType.LPStr)] public string? pOutputFile;
        [MarshalAs(UnmanagedType.LPStr)] public string pDataType;
    }

    [DllImport("winspool.Drv", EntryPoint = "OpenPrinterA", SetLastError = true, CharSet = CharSet.Ansi, ExactSpelling = true)]
    private static extern bool OpenPrinter(string szPrinter, out IntPtr hPrinter, IntPtr pd);

    [DllImport("winspool.Drv", EntryPoint = "ClosePrinter", SetLastError = true, ExactSpelling = true)]
    private static extern bool ClosePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "StartDocPrinterA", SetLastError = true, CharSet = CharSet.Ansi, ExactSpelling = true)]
    private static extern bool StartDocPrinter(IntPtr hPrinter, int level, ref DOCINFOA di);

    [DllImport("winspool.Drv", EntryPoint = "EndDocPrinter", SetLastError = true, ExactSpelling = true)]
    private static extern bool EndDocPrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "StartPagePrinter", SetLastError = true, ExactSpelling = true)]
    private static extern bool StartPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "EndPagePrinter", SetLastError = true, ExactSpelling = true)]
    private static extern bool EndPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "WritePrinter", SetLastError = true, ExactSpelling = true)]
    private static extern bool WritePrinter(IntPtr hPrinter, IntPtr pBytes, int dwCount, out int dwWritten);

    /// <summary>
    /// UTF-8 de propósito: o ZPL gerado pelo RD Intranet sempre começa com
    /// ^CI28 (liga UTF-8 na própria impressora) -- sem isso, acentuação
    /// (ex: "Área", "Localização") sairia errada no papel.
    /// </summary>
    public static bool EnviarTexto(string nomeImpressora, string texto, string tipoTrabalho = "RD Intranet - Etiqueta")
    {
        return EnviarBytes(nomeImpressora, Encoding.UTF8.GetBytes(texto), tipoTrabalho);
    }

    private static bool EnviarBytes(string nomeImpressora, byte[] bytes, string tipoTrabalho)
    {
        if (!OpenPrinter(nomeImpressora, out var hPrinter, IntPtr.Zero))
        {
            return false;
        }

        try
        {
            var docInfo = new DOCINFOA { pDocName = tipoTrabalho, pOutputFile = null, pDataType = "RAW" };

            if (!StartDocPrinter(hPrinter, 1, ref docInfo)) return false;

            try
            {
                if (!StartPagePrinter(hPrinter)) return false;

                try
                {
                    var ponteiro = Marshal.AllocCoTaskMem(bytes.Length);
                    try
                    {
                        Marshal.Copy(bytes, 0, ponteiro, bytes.Length);
                        return WritePrinter(hPrinter, ponteiro, bytes.Length, out _);
                    }
                    finally
                    {
                        Marshal.FreeCoTaskMem(ponteiro);
                    }
                }
                finally
                {
                    EndPagePrinter(hPrinter);
                }
            }
            finally
            {
                EndDocPrinter(hPrinter);
            }
        }
        finally
        {
            ClosePrinter(hPrinter);
        }
    }
}
