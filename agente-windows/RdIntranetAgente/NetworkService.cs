using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.Management;
using System.Net;
using System.Net.NetworkInformation;
using System.Net.Sockets;
using System.Runtime.InteropServices;
using System.Text;
using System.Text.Json;
using System.Text.Json.Serialization;

namespace RdIntranetAgente;

public sealed class AdaptadorRedeInfo
{
    [JsonPropertyName("adapter_id")]
    public int AdapterId { get; init; }

    [JsonPropertyName("nome")]
    public string Nome { get; init; } = "";

    [JsonPropertyName("descricao")]
    public string Descricao { get; init; } = "";

    [JsonPropertyName("mac")]
    public string Mac { get; init; } = "";

    [JsonPropertyName("status")]
    public string Status { get; init; } = "";

    [JsonPropertyName("ativo")]
    public bool Ativo { get; init; }

    [JsonPropertyName("wifi_ssid")]
    public string? WifiSsid { get; init; }

    [JsonPropertyName("dhcp")]
    public bool Dhcp { get; init; }

    [JsonPropertyName("ip")]
    public string? Ip { get; init; }

    [JsonPropertyName("mascara")]
    public string? Mascara { get; init; }

    [JsonPropertyName("gateway")]
    public string? Gateway { get; init; }

    [JsonPropertyName("dns")]
    public List<string> Dns { get; init; } = new();

    [JsonPropertyName("reversao_disponivel")]
    public bool ReversaoDisponivel { get; init; }
}

public sealed class NetworkService
{
    private const int WlanOpcodeCurrentConnection = 7;

    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    private struct WlanInterfaceInfo
    {
        public Guid InterfaceGuid;
        [MarshalAs(UnmanagedType.ByValTStr, SizeConst = 256)] public string Description;
        public uint State;
    }

    [StructLayout(LayoutKind.Sequential)]
    private struct Dot11Ssid
    {
        public uint Length;
        [MarshalAs(UnmanagedType.ByValArray, SizeConst = 32, ArraySubType = UnmanagedType.U1)] public byte[] Bytes;
    }

    [StructLayout(LayoutKind.Sequential)]
    private struct WlanAssociationAttributes
    {
        public Dot11Ssid Ssid;
        public uint BssType;
        [MarshalAs(UnmanagedType.ByValArray, SizeConst = 6, ArraySubType = UnmanagedType.U1)] public byte[] Bssid;
        public uint PhyType;
        public uint SignalQuality;
        public uint RxRate;
        public uint TxRate;
    }

    [StructLayout(LayoutKind.Sequential)]
    private struct WlanSecurityAttributes
    {
        public int SecurityEnabled;
        public int OneXEnabled;
        public int AuthAlgorithm;
        public int CipherAlgorithm;
    }

    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    private struct WlanConnectionAttributes
    {
        public uint State;
        public uint ConnectionMode;
        [MarshalAs(UnmanagedType.ByValTStr, SizeConst = 256)] public string ProfileName;
        public WlanAssociationAttributes Association;
        public WlanSecurityAttributes Security;
    }

    [DllImport("wlanapi.dll")]
    private static extern int WlanOpenHandle(uint clientVersion, IntPtr reserved, out uint negotiatedVersion, out IntPtr clientHandle);

    [DllImport("wlanapi.dll")]
    private static extern int WlanEnumInterfaces(IntPtr clientHandle, IntPtr reserved, out IntPtr interfaceList);

    [DllImport("wlanapi.dll")]
    private static extern int WlanQueryInterface(IntPtr clientHandle, ref Guid interfaceGuid, int opcode, IntPtr reserved, out int dataSize, out IntPtr data, out int valueType);

    [DllImport("wlanapi.dll")]
    private static extern void WlanFreeMemory(IntPtr memory);

    [DllImport("wlanapi.dll")]
    private static extern int WlanCloseHandle(IntPtr clientHandle, IntPtr reserved);

    private sealed class Snapshot
    {
        public int AdapterId { get; set; }
        public string Mac { get; set; } = "";
        public bool Dhcp { get; set; }
        public string[] Ip { get; set; } = Array.Empty<string>();
        public string[] Mascara { get; set; } = Array.Empty<string>();
        public string[] Gateway { get; set; } = Array.Empty<string>();
        public string[] Dns { get; set; } = Array.Empty<string>();
    }

    private sealed class ConfiguracaoSolicitada
    {
        [JsonPropertyName("adapter_id")]
        public int AdapterId { get; set; }

        [JsonPropertyName("dhcp")]
        public bool Dhcp { get; set; }

        [JsonPropertyName("ip")]
        public string? Ip { get; set; }

        [JsonPropertyName("mascara")]
        public string? Mascara { get; set; }

        [JsonPropertyName("gateway")]
        public string? Gateway { get; set; }

        [JsonPropertyName("dns")]
        public List<string>? Dns { get; set; }
    }

    private sealed class PingSolicitado
    {
        [JsonPropertyName("ip")]
        public string Ip { get; set; } = "";

        [JsonPropertyName("quantidade")]
        public int Quantidade { get; set; } = 4;
    }

    private sealed class RespostaPing
    {
        [JsonPropertyName("sucesso")]
        public bool Sucesso { get; init; }

        [JsonPropertyName("status")]
        public string Status { get; init; } = "";

        [JsonPropertyName("tempo_ms")]
        public long? TempoMs { get; init; }
    }

    private static readonly string PastaDados = Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "RDIntranetAgent");
    private static readonly string CaminhoSnapshots = Path.Combine(PastaDados, "network-snapshots.json");
    private static readonly object TravaSnapshots = new();

    public static List<AdaptadorRedeInfo> ListarAdaptadores()
    {
        var resultado = new List<AdaptadorRedeInfo>();
        var snapshots = CarregarSnapshots();
        using var busca = new ManagementObjectSearcher("SELECT * FROM Win32_NetworkAdapterConfiguration WHERE MACAddress IS NOT NULL");
        foreach (ManagementObject adaptador in busca.Get())
        {
            using (adaptador)
            {
                var id = LerInteiro(adaptador, "Index");
                var mac = LerTexto(adaptador, "MACAddress") ?? "";
                var nic = NetworkInterface.GetAllNetworkInterfaces().FirstOrDefault(n =>
                {
                    if (Guid.TryParse(n.Id, out var guid) && guid == GetGuid(adaptador, "SettingID")) return true;
                    try { return n.GetIPProperties().GetIPv4Properties()?.Index == id; }
                    catch { return false; }
                });
                var ips = LerArray(adaptador, "IPAddress");
                var indiceIp = Array.FindIndex(ips, e => IPAddress.TryParse(e, out var ip) && ip.AddressFamily == AddressFamily.InterNetwork);
                var mascaras = LerArray(adaptador, "IPSubnet");
                var gateways = LerArray(adaptador, "DefaultIPGateway");
                resultado.Add(new AdaptadorRedeInfo
                {
                    AdapterId = id,
                    Nome = nic?.Name ?? LerTexto(adaptador, "Description") ?? $"Adaptador {id}",
                    Descricao = LerTexto(adaptador, "Description") ?? "",
                    Mac = mac,
                    Status = nic?.OperationalStatus.ToString() ?? "Desconhecido",
                    Ativo = nic?.OperationalStatus == OperationalStatus.Up,
                    WifiSsid = nic?.NetworkInterfaceType == NetworkInterfaceType.Wireless80211 && nic.OperationalStatus == OperationalStatus.Up &&
                        Guid.TryParse(nic.Id, out var wifiGuid) ? ObterSsidWifi(wifiGuid) : null,
                    Dhcp = LerBooleano(adaptador, "DHCPEnabled"),
                    Ip = indiceIp >= 0 ? ips[indiceIp] : null,
                    Mascara = indiceIp >= 0 && indiceIp < mascaras.Length ? mascaras[indiceIp] : null,
                    Gateway = gateways.FirstOrDefault(),
                    Dns = LerArray(adaptador, "DNSServerSearchOrder").ToList(),
                    ReversaoDisponivel = snapshots.TryGetValue(id, out var snapshot) && string.Equals(snapshot.Mac, mac, StringComparison.OrdinalIgnoreCase)
                });
            }
        }
        return resultado
            .OrderByDescending(a => a.Ativo)
            .ThenBy(a => a.Nome, StringComparer.OrdinalIgnoreCase)
            .ToList();
    }

    public static object Aplicar(string? parametro)
    {
        var config = JsonSerializer.Deserialize<ConfiguracaoSolicitada>(parametro ?? "")
            ?? throw new InvalidOperationException("Configuração de rede inválida.");
        var atual = ObterSnapshot(config.AdapterId);
        if (!config.Dhcp)
        {
            ValidarIPv4(config.Ip, "IP");
            ValidarMascara(config.Mascara);
            if (!string.IsNullOrWhiteSpace(config.Gateway))
            {
                ValidarIPv4(config.Gateway, "Gateway");
                if (!NaMesmaSubrede(config.Ip!, config.Gateway!, config.Mascara!))
                    throw new InvalidOperationException("O gateway precisa estar na mesma sub-rede do endereço IP.");
            }
        }
        var dns = (config.Dns ?? new List<string>()).Where(d => !string.IsNullOrWhiteSpace(d)).Select(d => d.Trim()).ToArray();
        if (dns.Length > 2 || dns.Any(d => !IPAddress.TryParse(d, out _)))
            throw new InvalidOperationException("Informe até dois servidores DNS válidos.");

        SalvarSnapshot(atual);
        AplicarSnapshot(config.AdapterId, atual.Mac, config.Dhcp,
            config.Dhcp ? Array.Empty<string>() : new[] { config.Ip!.Trim() },
            config.Dhcp ? Array.Empty<string>() : new[] { config.Mascara!.Trim() },
            config.Dhcp || string.IsNullOrWhiteSpace(config.Gateway) ? Array.Empty<string>() : new[] { config.Gateway!.Trim() },
            config.Dhcp ? Array.Empty<string>() : dns);

        LogAtividade.Registrar(NivelAtividade.Aviso, "REDE", $"Configuração alterada no adaptador {config.AdapterId}; snapshot anterior salvo para reversão.");
        return new { sucesso = true, reversao_disponivel = true, adaptadores = ListarAdaptadores() };
    }

    public static object Reverter(string? parametro)
    {
        if (!int.TryParse(parametro, out var adapterId) || adapterId <= 0)
            throw new InvalidOperationException("Adaptador inválido para reversão.");
        var snapshots = CarregarSnapshots();
        if (!snapshots.TryGetValue(adapterId, out var snapshot))
            throw new InvalidOperationException("Não há configuração anterior salva para este adaptador.");
        AplicarSnapshot(snapshot.AdapterId, snapshot.Mac, snapshot.Dhcp, snapshot.Ip, snapshot.Mascara, snapshot.Gateway, snapshot.Dns);
        snapshots.Remove(adapterId);
        SalvarSnapshots(snapshots);
        LogAtividade.Registrar(NivelAtividade.Sucesso, "REDE", $"Configuração de rede revertida no adaptador {adapterId}.");
        return new { sucesso = true, adaptadores = ListarAdaptadores() };
    }

    public static async Task<object> PingAsync(string? parametro)
    {
        var pedido = JsonSerializer.Deserialize<PingSolicitado>(parametro ?? "")
            ?? throw new InvalidOperationException("Endereço IP inválido.");
        if (!IPAddress.TryParse(pedido.Ip, out var endereco))
            throw new InvalidOperationException("Digite um endereço IP válido; nomes de host não são aceitos.");
        var quantidade = Math.Clamp(pedido.Quantidade, 1, 10);
        var respostas = new List<RespostaPing>();
        using var ping = new Ping();
        for (var i = 0; i < quantidade; i++)
        {
            try
            {
                var resposta = await ping.SendPingAsync(endereco, 2000);
                respostas.Add(new RespostaPing
                {
                    Sucesso = resposta.Status == IPStatus.Success,
                    Status = resposta.Status.ToString(),
                    TempoMs = resposta.Status == IPStatus.Success ? resposta.RoundtripTime : null
                });
            }
            catch (Exception ex)
            {
                respostas.Add(new RespostaPing { Sucesso = false, Status = ex.Message });
            }
        }
        var exitosos = respostas.Count(r => r.Sucesso);
        var tempos = respostas.Where(r => r.TempoMs.HasValue).Select(r => r.TempoMs!.Value).ToArray();
        var resumo = $"{exitosos}/{quantidade} respostas" + (tempos.Length > 0 ? $", média {tempos.Average():0} ms" : ", sem resposta");
        LogAtividade.Registrar(exitosos == quantidade ? NivelAtividade.Sucesso : NivelAtividade.Aviso, "PING", $"Ping em {endereco}: {resumo}.");
        return new { ip = endereco.ToString(), respostas };
    }

    public static async Task<object> TracerouteAsync(string? parametro)
    {
        var pedido = JsonSerializer.Deserialize<PingSolicitado>(parametro ?? "")
            ?? throw new InvalidOperationException("Endereço IP inválido.");
        if (!IPAddress.TryParse(pedido.Ip, out var endereco))
            throw new InvalidOperationException("Digite um endereço IP válido; nomes de host não são aceitos.");

        using var processo = new Process
        {
            StartInfo = new ProcessStartInfo
            {
                FileName = "tracert.exe",
                UseShellExecute = false,
                CreateNoWindow = true,
                RedirectStandardOutput = true,
                RedirectStandardError = true
            }
        };
        processo.StartInfo.ArgumentList.Add("-d");
        processo.StartInfo.ArgumentList.Add("-h");
        processo.StartInfo.ArgumentList.Add("12");
        processo.StartInfo.ArgumentList.Add("-w");
        processo.StartInfo.ArgumentList.Add("700");
        processo.StartInfo.ArgumentList.Add(endereco.ToString());

        processo.Start();
        var saidaTask = processo.StandardOutput.ReadToEndAsync();
        var erroTask = processo.StandardError.ReadToEndAsync();
        using var timeout = new CancellationTokenSource(TimeSpan.FromSeconds(16));
        var expirou = false;
        try
        {
            await processo.WaitForExitAsync(timeout.Token);
        }
        catch (OperationCanceledException)
        {
            expirou = true;
            try { processo.Kill(entireProcessTree: true); } catch { }
            await processo.WaitForExitAsync();
        }

        var texto = (await saidaTask).Trim();
        var erro = (await erroTask).Trim();
        if (texto.Length == 0) texto = erro;
        var chegou = texto.Contains("Trace complete", StringComparison.OrdinalIgnoreCase)
            || texto.Contains("Rastreamento concluído", StringComparison.OrdinalIgnoreCase)
            || texto.Contains("Rastreamento completo", StringComparison.OrdinalIgnoreCase);
        LogAtividade.Registrar(expirou ? NivelAtividade.Aviso : NivelAtividade.Info, "TRACE ROUTE",
            expirou ? $"Traceroute para {endereco} atingiu o limite de 16 segundos." :
            $"Traceroute para {endereco}: {(chegou ? "destino alcançado" : "concluído; confira os saltos abaixo") }.");
        return new { ip = endereco.ToString(), timeout = expirou, destino_alcancado = chegou, saida = texto };
    }

    public static async Task<object> TestarVelocidadeAsync()
    {
        try
        {
            using var cliente = new HttpClient { Timeout = TimeSpan.FromSeconds(45) };
            cliente.DefaultRequestHeaders.CacheControl = new System.Net.Http.Headers.CacheControlHeaderValue { NoCache = true };
            const int bytesDownload = 8 * 1024 * 1024;
            const int bytesUpload = 1024 * 1024;

            var relogio = Stopwatch.StartNew();
            using var download = await cliente.GetAsync($"https://speed.cloudflare.com/__down?bytes={bytesDownload}", HttpCompletionOption.ResponseHeadersRead);
            download.EnsureSuccessStatusCode();
            await using (var stream = await download.Content.ReadAsStreamAsync())
            {
                var buffer = new byte[64 * 1024];
                while (await stream.ReadAsync(buffer) != 0) { }
            }
            relogio.Stop();
            var downloadMbps = bytesDownload * 8d / Math.Max(0.001, relogio.Elapsed.TotalSeconds) / 1_000_000d;
            var corpoUpload = new ByteArrayContent(new byte[bytesUpload]);
            corpoUpload.Headers.ContentType = new System.Net.Http.Headers.MediaTypeHeaderValue("application/octet-stream");
            relogio.Restart();
            using var upload = await cliente.PostAsync("https://speed.cloudflare.com/__up", corpoUpload);
            upload.EnsureSuccessStatusCode();
            await upload.Content.ReadAsByteArrayAsync();
            relogio.Stop();
            var uploadMbps = bytesUpload * 8d / Math.Max(0.001, relogio.Elapsed.TotalSeconds) / 1_000_000d;
            LogAtividade.Registrar(NivelAtividade.Sucesso, "VELOCIDADE", $"Teste Cloudflare: download {downloadMbps:0.##} Mbps, upload {uploadMbps:0.##} Mbps.");
            return new { provedor = "Cloudflare speed.cloudflare.com", download_mbps = Math.Round(downloadMbps, 2), upload_mbps = Math.Round(uploadMbps, 2) };
        }
        catch (Exception ex)
        {
            LogAtividade.Registrar(NivelAtividade.Erro, "VELOCIDADE", $"Teste de velocidade falhou: {ex.Message}");
            throw;
        }
    }

    private static string? ObterSsidWifi(Guid interfaceGuid)
    {
        IntPtr cliente = IntPtr.Zero;
        IntPtr lista = IntPtr.Zero;
        try
        {
            if (WlanOpenHandle(2, IntPtr.Zero, out _, out cliente) != 0 ||
                WlanEnumInterfaces(cliente, IntPtr.Zero, out lista) != 0)
                return null;
            var quantidade = Marshal.ReadInt32(lista);
            var tamanhoItem = Marshal.SizeOf<WlanInterfaceInfo>();
            for (var indice = 0; indice < quantidade; indice++)
            {
                var item = Marshal.PtrToStructure<WlanInterfaceInfo>(IntPtr.Add(lista, 8 + indice * tamanhoItem));
                if (item.InterfaceGuid != interfaceGuid || item.State != 1) continue;
                if (WlanQueryInterface(cliente, ref interfaceGuid, WlanOpcodeCurrentConnection, IntPtr.Zero,
                    out _, out var dados, out _) != 0)
                    return null;
                try
                {
                    var conexao = Marshal.PtrToStructure<WlanConnectionAttributes>(dados);
                    var ssid = conexao.Association.Ssid;
                    if (ssid.Bytes == null || ssid.Length == 0 || ssid.Length > ssid.Bytes.Length) return null;
                    return Encoding.UTF8.GetString(ssid.Bytes, 0, (int)ssid.Length).TrimEnd('\0');
                }
                finally
                {
                    WlanFreeMemory(dados);
                }
            }
        }
        catch
        {
            return null;
        }
        finally
        {
            if (lista != IntPtr.Zero) WlanFreeMemory(lista);
            if (cliente != IntPtr.Zero) WlanCloseHandle(cliente, IntPtr.Zero);
        }
        return null;
    }

    private static Guid GetGuid(ManagementBaseObject item, string property)
    {
        return Guid.TryParse(LerTexto(item, property), out var guid) ? guid : Guid.Empty;
    }

    private static Snapshot ObterSnapshot(int adapterId)
    {
        using var busca = new ManagementObjectSearcher($"SELECT * FROM Win32_NetworkAdapterConfiguration WHERE Index = {adapterId}");
        using var adaptadores = busca.Get();
        var adaptador = adaptadores.Cast<ManagementObject>().FirstOrDefault()
            ?? throw new InvalidOperationException("O adaptador não está disponível ou não tem TCP/IP habilitado.");
        using (adaptador)
        {
            return new Snapshot
            {
                AdapterId = adapterId,
                Mac = LerTexto(adaptador, "MACAddress") ?? "",
                Dhcp = LerBooleano(adaptador, "DHCPEnabled"),
                Ip = LerArray(adaptador, "IPAddress").Where(e => IPAddress.TryParse(e, out var ip) && ip.AddressFamily == AddressFamily.InterNetwork).ToArray(),
                Mascara = LerArray(adaptador, "IPSubnet").Where(e => IPAddress.TryParse(e, out var ip) && ip.AddressFamily == AddressFamily.InterNetwork).ToArray(),
                Gateway = LerArray(adaptador, "DefaultIPGateway").Where(e => IPAddress.TryParse(e, out var ip) && ip.AddressFamily == AddressFamily.InterNetwork).ToArray(),
                Dns = LerArray(adaptador, "DNSServerSearchOrder")
            };
        }
    }

    private static void AplicarSnapshot(int adapterId, string macEsperado, bool dhcp, string[] ips, string[] mascaras, string[] gateways, string[] dns)
    {
        using var busca = new ManagementObjectSearcher($"SELECT * FROM Win32_NetworkAdapterConfiguration WHERE Index = {adapterId}");
        using var adaptadores = busca.Get();
        var adaptador = adaptadores.Cast<ManagementObject>().FirstOrDefault()
            ?? throw new InvalidOperationException("Adaptador sem TCP/IP habilitado.");
        using (adaptador)
        {
            var macAtual = LerTexto(adaptador, "MACAddress") ?? "";
            if (!string.Equals(macAtual, macEsperado, StringComparison.OrdinalIgnoreCase))
                throw new InvalidOperationException("O adaptador selecionado mudou desde a leitura. Atualize a lista e tente de novo.");

            if (dhcp)
            {
                Invocar(adaptador, "EnableDHCP");
                Invocar(adaptador, "SetDNSServerSearchOrder", new Dictionary<string, object?> { ["DNSServerSearchOrder"] = null });
                return;
            }

            Invocar(adaptador, "EnableStatic", new Dictionary<string, object?>
            {
                ["IPAddress"] = ips,
                ["SubnetMask"] = mascaras
            });
            Invocar(adaptador, "SetGateways", new Dictionary<string, object?>
            {
                ["DefaultIPGateway"] = gateways.Length == 0 ? null : gateways,
                ["GatewayCostMetric"] = gateways.Length == 0 ? null : Enumerable.Repeat((ushort)1, gateways.Length).ToArray()
            });
            Invocar(adaptador, "SetDNSServerSearchOrder", new Dictionary<string, object?>
            {
                ["DNSServerSearchOrder"] = dns.Length == 0 ? null : dns
            });
        }
    }

    private static void Invocar(ManagementObject adaptador, string metodo, Dictionary<string, object?>? valores = null)
    {
        using var parametros = adaptador.GetMethodParameters(metodo);
        if (valores != null)
            foreach (var par in valores) parametros[par.Key] = par.Value;
        using var resultado = adaptador.InvokeMethod(metodo, parametros, null);
        var codigo = Convert.ToUInt32(resultado?["ReturnValue"] ?? 0);
        if (codigo > 1) throw new InvalidOperationException($"Windows recusou a operação de rede ({metodo}, código {codigo}).");
    }

    private static void ValidarIPv4(string? valor, string nome)
    {
        if (!IPAddress.TryParse(valor, out var ip) || ip.AddressFamily != AddressFamily.InterNetwork || IPAddress.IsLoopback(ip) || ip.Equals(IPAddress.Any) || ip.GetAddressBytes()[0] >= 224)
            throw new InvalidOperationException($"{nome} precisa ser um endereço IPv4 válido e utilizável.");
    }

    private static void ValidarMascara(string? valor)
    {
        if (!IPAddress.TryParse(valor, out var mascara) || mascara.AddressFamily != AddressFamily.InterNetwork)
            throw new InvalidOperationException("Máscara IPv4 inválida.");
        var bits = string.Concat(mascara.GetAddressBytes().Select(b => Convert.ToString(b, 2).PadLeft(8, '0')));
        if (bits[0] != '1' || bits.IndexOf("01", StringComparison.Ordinal) >= 0)
            throw new InvalidOperationException("A máscara precisa conter bits contíguos (por exemplo, 255.255.255.0).");
    }

    private static bool NaMesmaSubrede(string ip, string gateway, string mascara)
    {
        var endereco = IPAddress.Parse(ip).GetAddressBytes();
        var salto = IPAddress.Parse(gateway).GetAddressBytes();
        var mask = IPAddress.Parse(mascara).GetAddressBytes();
        return Enumerable.Range(0, 4).All(i => (endereco[i] & mask[i]) == (salto[i] & mask[i]));
    }

    private static Dictionary<int, Snapshot> CarregarSnapshots()
    {
        lock (TravaSnapshots)
        {
            try
            {
                return File.Exists(CaminhoSnapshots)
                    ? JsonSerializer.Deserialize<Dictionary<int, Snapshot>>(File.ReadAllText(CaminhoSnapshots)) ?? new()
                    : new();
            }
            catch { return new(); }
        }
    }

    private static void SalvarSnapshot(Snapshot snapshot)
    {
        var snapshots = CarregarSnapshots();
        snapshots[snapshot.AdapterId] = snapshot;
        SalvarSnapshots(snapshots);
    }

    private static void SalvarSnapshots(Dictionary<int, Snapshot> snapshots)
    {
        lock (TravaSnapshots)
        {
            Directory.CreateDirectory(PastaDados);
            File.WriteAllText(CaminhoSnapshots, JsonSerializer.Serialize(snapshots, new JsonSerializerOptions { WriteIndented = true }));
        }
    }

    private static int LerInteiro(ManagementBaseObject item, string nome) => Convert.ToInt32(item[nome] ?? 0);
    private static bool LerBooleano(ManagementBaseObject item, string nome) => Convert.ToBoolean(item[nome] ?? false);
    private static string? LerTexto(ManagementBaseObject item, string nome) => item[nome]?.ToString();
    private static string[] LerArray(ManagementBaseObject item, string nome) => item[nome] as string[] ?? Array.Empty<string>();
}
