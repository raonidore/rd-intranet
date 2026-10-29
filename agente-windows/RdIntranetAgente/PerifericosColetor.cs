using System.Management;
using System.Text.RegularExpressions;
using Microsoft.Win32;
using RdIntranetAgente.Models;

namespace RdIntranetAgente;

/// <summary>
/// Monitores, impressoras, teclados e mouses ligados Ã  mÃ¡quina -- pro
/// inventÃ¡rio (ficha do computador > PerifÃ©ricos, "Cadastrar como ativo").
/// O que Ã© embutido (tela do notebook, teclado/touchpad internos) fica de
/// fora: nÃ£o Ã© patrimÃ´nio separado.
/// </summary>
internal static class PerifericosColetor
{
    /// <summary>Container de dispositivo do Windows que significa "parte do prÃ³prio computador".</summary>
    private const string ContainerInterno = "{00000000-0000-0000-FFFF-FFFFFFFFFFFF}";

    /// <summary>SaÃ­das de vÃ­deo internas (D3DKMDT_VIDEO_OUTPUT_TECHNOLOGY): LVDS, eDP, UDI embutido, "internal".</summary>
    private static readonly HashSet<long> SaidaInterna = new() { 6, 11, 13, 0x80000000 };

    private static readonly Dictionary<long, string> SaidaVideo = new()
    {
        [0] = "VGA", [4] = "DVI", [5] = "HDMI", [10] = "DisplayPort", [15] = "Miracast", [16] = "Dock/USB"
    };

    /// <summary>CÃ³digo PNP de 3 letras do EDID -> fabricante.</summary>
    private static readonly Dictionary<string, string> FabricanteMonitor = new(StringComparer.OrdinalIgnoreCase)
    {
        ["DEL"] = "Dell", ["SAM"] = "Samsung", ["SEC"] = "Samsung", ["GSM"] = "LG", ["LGD"] = "LG", ["AOC"] = "AOC",
        ["ENV"] = "AOC", ["PHL"] = "Philips", ["ACR"] = "Acer", ["HWP"] = "HP", ["HPN"] = "HP", ["HPQ"] = "HP",
        ["LEN"] = "Lenovo", ["AUS"] = "ASUS", ["BNQ"] = "BenQ", ["VSC"] = "ViewSonic", ["SNY"] = "Sony",
        ["HKC"] = "HKC", ["POS"] = "Positivo", ["MSI"] = "MSI", ["GBT"] = "Gigabyte", ["XMI"] = "Xiaomi",
        ["NEC"] = "NEC", ["EIZ"] = "EIZO", ["IVM"] = "Iiyama", ["TCL"] = "TCL", ["PNS"] = "Pixelview",
        ["MST"] = "Multilaser", ["MZT"] = "Mizutech", ["HSD"] = "Hannspree", ["APP"] = "Apple", ["FUS"] = "Fujitsu",
        ["CMN"] = "Chimei Innolux", ["BOE"] = "BOE", ["AUO"] = "AU Optronics", ["SHP"] = "Sharp", ["IVO"] = "InfoVision",
        ["TOS"] = "Toshiba", ["PHI"] = "Philips", ["SPT"] = "Sceptre", ["KOA"] = "Konka", ["CTX"] = "CTX"
    };

    /// <summary>Vendor ID USB -> fabricante (os que aparecem em teclado/mouse).</summary>
    private static readonly Dictionary<string, string> FabricanteUsb = new(StringComparer.OrdinalIgnoreCase)
    {
        ["046D"] = "Logitech", ["045E"] = "Microsoft", ["413C"] = "Dell", ["03F0"] = "HP", ["17EF"] = "Lenovo",
        ["04F2"] = "Chicony", ["0461"] = "Primax", ["093A"] = "PixArt", ["1BCF"] = "Sunplus", ["24AE"] = "Rapoo",
        ["0458"] = "Genius", ["04D9"] = "Holtek", ["1A2C"] = "China Resource", ["046A"] = "Cherry", ["1532"] = "Razer",
        ["1B1C"] = "Corsair", ["3434"] = "Keychron", ["2717"] = "Xiaomi", ["0B05"] = "ASUS", ["05AC"] = "Apple",
        ["04CA"] = "Lite-On", ["0951"] = "HyperX", ["258A"] = "Sinowealth", ["1EA7"] = "SHARKOON", ["0E8F"] = "GreenAsia",
        ["1C4F"] = "SiGma Micro", ["18F8"] = "Maxxter", ["062A"] = "MosArt", ["0C45"] = "Microdia", ["04B4"] = "Cypress",
        ["2EA8"] = "Multilaser", ["1D57"] = "Xenta", ["25A7"] = "Areson", ["3151"] = "Multilaser", ["0BDA"] = "Realtek",
        ["048D"] = "ITE", ["056E"] = "Elecom", ["0603"] = "Novatek", ["3938"] = "Redragon", ["04E8"] = "Samsung"
    };

    private static readonly Regex ImpressoraVirtual = new(
        @"pdf|xps|onenote|fax|rustdesk|anydesk|teamviewer|send to|enviar para|snagit|nitro|foxit|bullzip|cutepdf|redirected|redirecionad",
        RegexOptions.IgnoreCase);

    private static readonly Regex PortaVirtual = new(@"^(nul:|portprompt:|file:|shrfax:|xps|ts\d+|rustdesk|anydesk|onenote)", RegexOptions.IgnoreCase);

    /// <summary>null = a coleta falhou inteira (o portal mantÃ©m a lista anterior em vez de apagar).</summary>
    public static List<PerifericoItem>? Coletar()
    {
        var lista = new List<PerifericoItem>();
        var algumOk = false;

        foreach (var etapa in new Func<List<PerifericoItem>>[] { Monitores, Impressoras, TecladosEMouses })
        {
            try
            {
                lista.AddRange(etapa());
                algumOk = true;
            }
            catch
            {
                // WMI de uma classe fora do ar nÃ£o derruba as outras
            }
        }

        return algumOk ? lista : null;
    }

    private static List<PerifericoItem> Monitores()
    {
        var saidas = new Dictionary<string, long>(StringComparer.OrdinalIgnoreCase);
        var tamanhos = new Dictionary<string, (int h, int v)>(StringComparer.OrdinalIgnoreCase);

        try
        {
            using var busca = new ManagementObjectSearcher(@"root\wmi", "SELECT InstanceName, VideoOutputTechnology FROM WmiMonitorConnectionParams");
            foreach (ManagementObject m in busca.Get())
            {
                saidas[m["InstanceName"]?.ToString() ?? ""] = Convert.ToInt64(m["VideoOutputTechnology"] ?? -1L);
            }
        }
        catch
        {
            // sem a saÃ­da: nÃ£o dÃ¡ pra excluir a tela interna por ela, segue sem
        }

        try
        {
            using var busca = new ManagementObjectSearcher(@"root\wmi", "SELECT InstanceName, MaxHorizontalImageSize, MaxVerticalImageSize FROM WmiMonitorBasicDisplayParams");
            foreach (ManagementObject m in busca.Get())
            {
                tamanhos[m["InstanceName"]?.ToString() ?? ""] = (Convert.ToInt32(m["MaxHorizontalImageSize"] ?? 0), Convert.ToInt32(m["MaxVerticalImageSize"] ?? 0));
            }
        }
        catch
        {
            // tamanho Ã© sÃ³ detalhe
        }

        var lista = new List<PerifericoItem>();
        using var buscaId = new ManagementObjectSearcher(@"root\wmi", "SELECT * FROM WmiMonitorID WHERE Active = TRUE");
        foreach (ManagementObject m in buscaId.Get())
        {
            var instancia = m["InstanceName"]?.ToString() ?? "";
            var saida = saidas.TryGetValue(instancia, out var s) ? s : -1;
            if (SaidaInterna.Contains(saida)) continue;

            var codigo = TextoEdid(m["ManufacturerName"]);
            var produto = TextoEdid(m["ProductCodeID"]);
            var nome = TextoEdid(m["UserFriendlyName"]);
            var serie = TextoEdid(m["SerialNumberID"]);
            if (!SerieValida(serie)) serie = SerieNumericaEdid(instancia);

            var detalhes = new List<string>();
            if (tamanhos.TryGetValue(instancia, out var t) && t.h > 0 && t.v > 0)
            {
                detalhes.Add($"{Math.Round(Math.Sqrt(t.h * t.h + t.v * t.v) / 2.54):0}\"");
            }

            var ano = Convert.ToInt32(m["YearOfManufacture"] ?? 0);
            if (ano > 1990) detalhes.Add($"fabricado em {ano}");

            lista.Add(new PerifericoItem
            {
                Tipo = "monitor",
                Fabricante = FabricanteMonitor.TryGetValue(codigo, out var f) ? f : codigo,
                Modelo = string.IsNullOrWhiteSpace(nome) ? produto : nome,
                NumeroSerie = serie,
                Conexao = SaidaVideo.TryGetValue(saida, out var nomeSaida) ? nomeSaida : null,
                Detalhe = detalhes.Count > 0 ? string.Join(" Â· ", detalhes) : null
            });
        }

        return lista;
    }

    private static string TextoEdid(object? valor)
    {
        if (valor is not ushort[] codigos) return "";
        return new string(codigos.TakeWhile(c => c != 0).Select(c => (char)c).ToArray()).Trim();
    }

    private static bool SerieValida(string? serie) =>
        !string.IsNullOrWhiteSpace(serie) && serie.Trim().Length >= 4 && serie.Trim().Distinct().Count() > 1;

    /// <summary>
    /// Monitor sem a sÃ©rie em texto (Windows devolve "0"): tenta o nÃºmero de
    /// sÃ©rie binÃ¡rio do EDID (bytes 12-15), gravado no registro pelo Windows.
    /// </summary>
    private static string? SerieNumericaEdid(string instancia)
    {
        try
        {
            var caminho = Regex.Replace(instancia, @"_\d+$", "");
            using var chave = Registry.LocalMachine.OpenSubKey($@"SYSTEM\CurrentControlSet\Enum\{caminho}\Device Parameters");
            if (chave?.GetValue("EDID") is not byte[] edid || edid.Length < 16) return null;

            var numero = BitConverter.ToUInt32(edid, 12);
            return numero is 0 or 0x01010101 or 0xFFFFFFFF ? null : numero.ToString();
        }
        catch
        {
            return null;
        }
    }

    private static List<PerifericoItem> Impressoras()
    {
        var ipsPorta = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);
        try
        {
            using var busca = new ManagementObjectSearcher("SELECT Name, HostAddress FROM Win32_TCPIPPrinterPort");
            foreach (ManagementObject porta in busca.Get())
            {
                ipsPorta[porta["Name"]?.ToString() ?? ""] = porta["HostAddress"]?.ToString() ?? "";
            }
        }
        catch
        {
            // sem as portas TCP/IP: impressora de rede vai sem IP
        }

        var lista = new List<PerifericoItem>();
        using var buscaImp = new ManagementObjectSearcher("SELECT Name, DriverName, PortName, Network, ShareName FROM Win32_Printer");
        foreach (ManagementObject imp in buscaImp.Get())
        {
            var nome = imp["Name"]?.ToString() ?? "";
            var driver = imp["DriverName"]?.ToString() ?? "";
            var porta = imp["PortName"]?.ToString() ?? "";

            if (ImpressoraVirtual.IsMatch(nome + " " + driver) || PortaVirtual.IsMatch(porta)) continue;

            string conexao;
            string? ip = null;
            if (nome.StartsWith(@"\\")) conexao = "Compartilhada (" + nome.TrimStart('\\').Split('\\')[0] + ")";
            else if (porta.StartsWith("USB", StringComparison.OrdinalIgnoreCase) || porta.StartsWith("DOT4", StringComparison.OrdinalIgnoreCase)) conexao = "USB";
            else if (porta.StartsWith("LPT", StringComparison.OrdinalIgnoreCase)) conexao = "Paralela";
            else if (porta.StartsWith("COM", StringComparison.OrdinalIgnoreCase)) conexao = "Serial";
            else if (porta.StartsWith("WSD", StringComparison.OrdinalIgnoreCase)) conexao = "Rede (WSD)";
            else
            {
                conexao = "Rede";
                if (ipsPorta.TryGetValue(porta, out var host) && !string.IsNullOrWhiteSpace(host)) ip = host;
                else if (System.Net.IPAddress.TryParse(Regex.Replace(porta, @"^IP_|_\d+$", ""), out var direto)) ip = direto.ToString();
            }

            lista.Add(new PerifericoItem
            {
                Tipo = "impressora",
                Fabricante = FabricanteImpressora(driver),
                Modelo = string.IsNullOrWhiteSpace(driver) ? nome : driver,
                Conexao = conexao,
                Ip = ip,
                Detalhe = !string.Equals(nome, driver, StringComparison.OrdinalIgnoreCase) ? "No Windows: " + nome : null
            });
        }

        return lista;
    }

    private static string? FabricanteImpressora(string driver)
    {
        var d = driver.ToLowerInvariant();
        foreach (var (trecho, nome) in new[]
                 {
                     ("zdesigner", "Zebra"), ("zebra", "Zebra"), ("argox", "Argox"), ("elgin", "Elgin"), ("bematech", "Bematech"),
                     ("epson", "Epson"), ("hp ", "HP"), ("brother", "Brother"), ("samsung", "Samsung"), ("kyocera", "Kyocera"),
                     ("ricoh", "Ricoh"), ("canon", "Canon"), ("lexmark", "Lexmark"), ("xerox", "Xerox"), ("oki", "OKI"),
                     ("pantum", "Pantum"), ("sharp", "Sharp"), ("konica", "Konica Minolta"), ("tsc", "TSC"), ("daruma", "Daruma")
                 })
        {
            if (d.StartsWith(trecho.Trim()) || d.Contains(trecho)) return nome;
        }

        return driver.Split(' ', StringSplitOptions.RemoveEmptyEntries).FirstOrDefault();
    }

    /// <summary>
    /// Agrupa pelo "container" do Windows (= aparelho fÃ­sico): um receptor
    /// sem fio de kit teclado+mouse vira um item sÃ³. Embutido fica de fora.
    /// </summary>
    private static List<PerifericoItem> TecladosEMouses()
    {
        var porAparelho = new Dictionary<string, (bool teclado, bool mouse, string deviceId, string? pai)>(StringComparer.OrdinalIgnoreCase);

        using (var busca = new ManagementObjectSearcher("SELECT DeviceID, PNPClass FROM Win32_PnPEntity WHERE PNPClass = 'Keyboard' OR PNPClass = 'Mouse'"))
        {
            foreach (ManagementObject d in busca.Get())
            {
                var props = Propriedades(d, "DEVPKEY_Device_ContainerId", "DEVPKEY_Device_Parent");
                var container = props.GetValueOrDefault("DEVPKEY_Device_ContainerId") ?? "";
                if (container == "" || container.Equals(ContainerInterno, StringComparison.OrdinalIgnoreCase)) continue;

                var ehTeclado = string.Equals(d["PNPClass"]?.ToString(), "Keyboard", StringComparison.OrdinalIgnoreCase);
                var atual = porAparelho.TryGetValue(container, out var a) ? a : (teclado: false, mouse: false, deviceId: d["DeviceID"]?.ToString() ?? "", pai: props.GetValueOrDefault("DEVPKEY_Device_Parent"));
                porAparelho[container] = (atual.teclado || ehTeclado, atual.mouse || !ehTeclado, atual.deviceId, atual.pai);
            }
        }

        var lista = new List<PerifericoItem>();
        foreach (var (teclado, mouse, deviceId, pai) in porAparelho.Values)
        {
            var (nome, serie, bluetooth) = IdentificarAparelho(pai);
            // USB: VID_046D; Bluetooth LE: VID&02046D; Bluetooth clÃ¡ssico: VID&0002046D -- o vendor sÃ£o os 4 Ãºltimos.
            var vidHex = Regex.Match(deviceId, @"VID[_&]([0-9A-F]{4,8})", RegexOptions.IgnoreCase).Groups[1].Value;
            var vid = vidHex.Length >= 4 ? vidHex[^4..].ToUpperInvariant() : "";
            var fabricante = vid != "" && FabricanteUsb.TryGetValue(vid, out var f) ? f : null;

            lista.Add(new PerifericoItem
            {
                Tipo = teclado && mouse ? "teclado_mouse" : teclado ? "teclado" : "mouse",
                Fabricante = fabricante,
                Modelo = nome,
                NumeroSerie = serie,
                Conexao = bluetooth ? "Bluetooth" : deviceId.StartsWith("ACPI", StringComparison.OrdinalIgnoreCase) ? "PS/2" : "USB",
                Detalhe = vid != "" && fabricante == null ? "fabricante USB " + vid : null
            });
        }

        return lista;
    }

    /// <summary>
    /// Sobe pelos pais do dispositivo HID atÃ© achar o aparelho: no USB, a
    /// descriÃ§Ã£o que o prÃ³prio aparelho informa ("USB Receiver", "USB Optical
    /// Mouse") e a sÃ©rie (quando o instance ID Ã© a sÃ©rie real); no Bluetooth,
    /// o nome pareado ("LOGI M240").
    /// </summary>
    private static (string? nome, string? serie, bool bluetooth) IdentificarAparelho(string? pai)
    {
        for (var nivel = 0; nivel < 4 && !string.IsNullOrEmpty(pai); nivel++)
        {
            using var busca = new ManagementObjectSearcher($"SELECT DeviceID, Name FROM Win32_PnPEntity WHERE DeviceID = '{pai.Replace(@"\", @"\\").Replace("'", @"\'")}'");
            var d = busca.Get().Cast<ManagementObject>().FirstOrDefault();
            if (d == null) break;

            var id = d["DeviceID"]?.ToString() ?? "";
            var props = Propriedades(d, "DEVPKEY_Device_BusReportedDeviceDesc", "DEVPKEY_Device_Parent");

            if (Regex.IsMatch(id, @"^(BTHLE|BTHENUM)\\DEV_", RegexOptions.IgnoreCase))
            {
                return (d["Name"]?.ToString(), null, true);
            }

            if (id.StartsWith(@"USB\VID_", StringComparison.OrdinalIgnoreCase) && !id.Contains("&MI_", StringComparison.OrdinalIgnoreCase))
            {
                var instancia = id.Split('\\').Last();
                var serie = !instancia.Contains('&') && SerieValida(instancia) ? instancia : null;
                return (props.GetValueOrDefault("DEVPKEY_Device_BusReportedDeviceDesc"), serie, false);
            }

            pai = props.GetValueOrDefault("DEVPKEY_Device_Parent");
        }

        return (null, null, false);
    }

    private static Dictionary<string, string?> Propriedades(ManagementObject dispositivo, params string[] chaves)
    {
        var resultado = new Dictionary<string, string?>();
        try
        {
            var entrada = dispositivo.GetMethodParameters("GetDeviceProperties");
            entrada["devicePropertyKeys"] = chaves;
            var saida = dispositivo.InvokeMethod("GetDeviceProperties", entrada, null);

            if (saida?["deviceProperties"] is ManagementBaseObject[] props)
            {
                foreach (var p in props)
                {
                    var chave = p["KeyName"]?.ToString();
                    if (chave != null) resultado[chave] = p["Data"]?.ToString();
                }
            }
        }
        catch
        {
            // Windows 7 nÃ£o tem GetDeviceProperties -- fica sem
        }

        return resultado;
    }
}
