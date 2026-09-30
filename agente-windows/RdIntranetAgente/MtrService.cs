using System.Diagnostics;
using System.Net;
using System.Net.NetworkInformation;
using System.Net.Sockets;
using System.Runtime.InteropServices;
using System.Text;

namespace RdIntranetAgente;

/// <summary>Opções do MTR -- as mesmas do WinMTR (intervalo, tamanho do ping, saltos, resolver nomes).</summary>
public sealed record OpcoesMtr(double IntervaloSegundos = 1.0, int TamanhoPing = 64, int MaxSaltos = 30, bool ResolverNomes = true);

/// <summary>Estatística de um salto da rota.</summary>
public sealed class SaltoMtr
{
    public int Numero { get; init; }
    public IPAddress? Endereco { get; set; }
    public string? Nome { get; set; }
    public int Enviados { get; set; }
    public int Recebidos { get; set; }
    public long Melhor { get; set; } = long.MaxValue;
    public long Pior { get; set; }
    public long Soma { get; set; }
    public long Ultimo { get; set; } = -1;
    /// <summary>Últimas respostas (ms), -1 = pacote perdido -- alimenta o minigráfico.</summary>
    public List<long> Historico { get; } = new();

    public double PerdaPercentual => Enviados == 0 ? 0 : 100.0 * (Enviados - Recebidos) / Enviados;
    public double Media => Recebidos == 0 ? 0 : (double)Soma / Recebidos;
    public string Host => Endereco == null ? "Sem resposta do host" : Nome is { Length: > 0 } ? Nome : Endereco.ToString();

    /// <summary>Cópia independente (a tela desenha a cópia enquanto a sondagem continua mexendo no original).</summary>
    public SaltoMtr Copiar()
    {
        var c = new SaltoMtr
        {
            Numero = Numero,
            Endereco = Endereco,
            Nome = Nome,
            Enviados = Enviados,
            Recebidos = Recebidos,
            Melhor = Melhor,
            Pior = Pior,
            Soma = Soma,
            Ultimo = Ultimo
        };
        c.Historico.AddRange(Historico);
        return c;
    }
}

/// <summary>
/// MTR (traceroute contínuo, como o WinMTR): a cada intervalo manda um ping
/// ICMP pro destino com TTL 1, 2, 3... -- cada roteador no caminho responde
/// "TTL expirado" e assim aparece como um salto -- e acumula perda e
/// latência de cada um. Roda até ser parado. Não precisa de administrador.
/// Usa IcmpSendEcho (iphlpapi) direto, como o WinMTR: a classe Ping do .NET
/// devolve 0 ms em toda resposta "TTL expirado" e só mede o destino final.
/// </summary>
public sealed class MtrService
{
    private const int HistoricoMaximo = 60;
    private readonly object _trava = new();
    private readonly List<SaltoMtr> _saltos = new();
    private readonly Dictionary<string, string?> _nomes = new();
    private int _saltoDestino = int.MaxValue;

    public IPAddress? Destino { get; private set; }
    public string HostDigitado { get; private set; } = "";
    public DateTime? IniciadoEm { get; private set; }

    /// <summary>Disparado a cada rodada (em thread de fundo) -- a tela copia com <see cref="Fotografia"/>.</summary>
    public event Action? Atualizado;

    public List<SaltoMtr> Fotografia()
    {
        lock (_trava)
        {
            return _saltos
                .Where(s => s.Numero <= _saltoDestino)
                .Select(s => s.Copiar())
                .ToList();
        }
    }

    public async Task ExecutarAsync(string host, OpcoesMtr opcoes, CancellationToken cancelar)
    {
        host = host.Trim();
        HostDigitado = host;
        Destino = await ResolverDestinoAsync(host, cancelar);
        IniciadoEm = DateTime.Now;
        lock (_trava)
        {
            _saltos.Clear();
            _saltoDestino = int.MaxValue;
            for (var ttl = 1; ttl <= opcoes.MaxSaltos; ttl++) _saltos.Add(new SaltoMtr { Numero = ttl });
        }

        var dados = new byte[Math.Clamp(opcoes.TamanhoPing, 8, 1472)];
        var intervalo = TimeSpan.FromSeconds(Math.Clamp(opcoes.IntervaloSegundos, 0.2, 30));
        // Timeout nunca menor que 1s (roteador lento) nem que o próprio intervalo.
        var timeout = (int)Math.Max(1000, intervalo.TotalMilliseconds);

        while (!cancelar.IsCancellationRequested)
        {
            var inicioRodada = Stopwatch.StartNew();
            int limite;
            lock (_trava) limite = Math.Min(_saltoDestino, opcoes.MaxSaltos);

            await Task.WhenAll(Enumerable.Range(1, limite).Select(ttl => SondarAsync(ttl, dados, timeout, opcoes.ResolverNomes)));
            if (cancelar.IsCancellationRequested) break;
            Atualizado?.Invoke();

            var restante = intervalo - inicioRodada.Elapsed;
            if (restante > TimeSpan.Zero)
            {
                try { await Task.Delay(restante, cancelar); } catch (TaskCanceledException) { break; }
            }
        }
    }

    private async Task SondarAsync(int ttl, byte[] dados, int timeout, bool resolverNomes)
    {
        // Chamada bloqueante numa thread própria: um salto que não responde
        // (espera o timeout inteiro) não atrasa a medição dos outros.
        var (respondeu, endereco, ms, chegou) = await Task.Factory.StartNew(
            () => Icmp.Enviar(Destino!, ttl, dados, timeout), TaskCreationOptions.LongRunning);

        lock (_trava)
        {
            var salto = _saltos[ttl - 1];
            salto.Enviados++;
            if (respondeu)
            {
                salto.Recebidos++;
                salto.Endereco = endereco;
                salto.Ultimo = ms;
                salto.Soma += ms;
                salto.Melhor = Math.Min(salto.Melhor, ms);
                salto.Pior = Math.Max(salto.Pior, ms);
                if (chegou) _saltoDestino = Math.Min(_saltoDestino, ttl);
                if (_nomes.TryGetValue(endereco!.ToString(), out var nome)) salto.Nome = nome;
            }
            salto.Historico.Add(respondeu ? ms : -1);
            if (salto.Historico.Count > HistoricoMaximo) salto.Historico.RemoveAt(0);
        }

        if (respondeu && resolverNomes) await ResolverNomeAsync(endereco!);
    }

    private async Task ResolverNomeAsync(IPAddress ip)
    {
        var chave = ip.ToString();
        lock (_trava)
        {
            if (_nomes.ContainsKey(chave)) return;
            _nomes[chave] = null; // marca "em andamento" -- resolve uma vez só por IP
        }

        string? nome = null;
        try
        {
            var busca = Dns.GetHostEntryAsync(ip);
            if (await Task.WhenAny(busca, Task.Delay(3000)) == busca && busca.Result.HostName != chave)
            {
                nome = busca.Result.HostName;
            }
        }
        catch
        {
            // sem PTR -- fica o IP
        }

        lock (_trava)
        {
            _nomes[chave] = nome;
            foreach (var s in _saltos.Where(s => chave.Equals(s.Endereco?.ToString()))) s.Nome = nome;
        }
    }

    private static async Task<IPAddress> ResolverDestinoAsync(string host, CancellationToken cancelar)
    {
        if (IPAddress.TryParse(host, out var ip)) return ip;
        var enderecos = await Dns.GetHostAddressesAsync(host, cancelar);
        return enderecos.FirstOrDefault(a => a.AddressFamily == AddressFamily.InterNetwork)
            ?? enderecos.FirstOrDefault()
            ?? throw new InvalidOperationException($"Não consegui resolver \"{host}\".");
    }

    // ================================================================ exportação (mesmo espírito do WinMTR)

    public string ComoTexto()
    {
        var saltos = Fotografia();
        var sb = new StringBuilder();
        var linha = new string('-', 100);
        sb.AppendLine("|" + linha + "|");
        sb.AppendLine($"| RD Intranet - MTR  ·  destino: {HostDigitado} ({Destino})  ·  {IniciadoEm:dd/MM/yyyy HH:mm:ss}".PadRight(101) + "|");
        sb.AppendLine("|" + linha + "|");
        sb.AppendLine($"| {"Nr",3} | {"Host",-42} | {"Perda%",6} | {"Env",4} | {"Rec",4} | {"Melhor",6} | {"Média",6} | {"Pior",5} | {"Último",6} |");
        sb.AppendLine("|" + linha + "|");
        foreach (var s in saltos)
        {
            var host = s.Host.Length > 42 ? s.Host[..42] : s.Host;
            sb.AppendLine($"| {s.Numero,3} | {host,-42} | {s.PerdaPercentual,6:0} | {s.Enviados,4} | {s.Recebidos,4} | {Ms(s.Recebidos > 0 ? s.Melhor : -1),6} | {Ms(s.Recebidos > 0 ? (long)Math.Round(s.Media) : -1),6} | {Ms(s.Recebidos > 0 ? s.Pior : -1),5} | {Ms(s.Ultimo),6} |");
        }
        sb.AppendLine("|" + linha + "|");
        return sb.ToString();
    }

    public string ComoHtml()
    {
        var saltos = Fotografia();
        var sb = new StringBuilder();
        sb.Append("<table border=\"1\" cellpadding=\"4\" cellspacing=\"0\" style=\"border-collapse:collapse;font-family:Segoe UI,Arial;font-size:13px\">");
        sb.Append($"<caption style=\"text-align:left;padding:4px 0\"><b>RD Intranet - MTR</b> · destino {WebUtility.HtmlEncode(HostDigitado)} ({Destino}) · {IniciadoEm:dd/MM/yyyy HH:mm:ss}</caption>");
        sb.Append("<tr style=\"background:#1f2937;color:#fff\"><th>Nr</th><th>Host</th><th>Perda %</th><th>Enviados</th><th>Recebidos</th><th>Melhor</th><th>Média</th><th>Pior</th><th>Último</th></tr>");
        foreach (var s in saltos)
        {
            var cor = s.PerdaPercentual >= 20 ? "#fde2e1" : s.PerdaPercentual > 0 ? "#fff4d6" : "#ffffff";
            sb.Append($"<tr style=\"background:{cor}\"><td>{s.Numero}</td><td>{WebUtility.HtmlEncode(s.Host)}</td><td>{s.PerdaPercentual:0}</td><td>{s.Enviados}</td><td>{s.Recebidos}</td>");
            sb.Append($"<td>{Ms(s.Recebidos > 0 ? s.Melhor : -1)}</td><td>{Ms(s.Recebidos > 0 ? (long)Math.Round(s.Media) : -1)}</td><td>{Ms(s.Recebidos > 0 ? s.Pior : -1)}</td><td>{Ms(s.Ultimo)}</td></tr>");
        }
        sb.Append("</table>");
        return sb.ToString();
    }

    private static string Ms(long valor) => valor < 0 ? "-" : valor.ToString();

    /// <summary>IcmpSendEcho da iphlpapi.dll -- mesmo caminho do WinMTR.</summary>
    private static class Icmp
    {
        private const uint IpSucesso = 0;
        private const uint IpTtlExpiradoTransito = 11013;
        private const uint IpTtlExpiradoRemontagem = 11014;

        [StructLayout(LayoutKind.Sequential)]
        private struct IP_OPTION_INFORMATION { public byte Ttl; public byte Tos; public byte Flags; public byte OptionsSize; public IntPtr OptionsData; }

        [StructLayout(LayoutKind.Sequential)]
        private struct ICMP_ECHO_REPLY { public uint Address; public uint Status; public uint RoundTripTime; public ushort DataSize; public ushort Reserved; public IntPtr Data; public IP_OPTION_INFORMATION Options; }

        [DllImport("iphlpapi.dll", SetLastError = true)]
        private static extern IntPtr IcmpCreateFile();

        [DllImport("iphlpapi.dll", SetLastError = true)]
        private static extern bool IcmpCloseHandle(IntPtr handle);

        [DllImport("iphlpapi.dll", SetLastError = true)]
        private static extern uint IcmpSendEcho(IntPtr handle, uint destino, byte[] dados, ushort tamanho, ref IP_OPTION_INFORMATION opcoes, IntPtr resposta, uint tamanhoResposta, uint timeout);

        /// <returns>respondeu, quem respondeu, tempo (ms), se foi o próprio destino</returns>
        public static (bool, IPAddress?, long, bool) Enviar(IPAddress destino, int ttl, byte[] dados, int timeout)
        {
            if (destino.AddressFamily != AddressFamily.InterNetwork)
            {
                throw new NotSupportedException("O MTR usa IPv4 -- informe um endereço ou nome com IPv4.");
            }

            var handle = IcmpCreateFile();
            if (handle == IntPtr.Zero || handle == new IntPtr(-1)) return (false, null, -1, false);

            var tamanhoResposta = Marshal.SizeOf<ICMP_ECHO_REPLY>() + dados.Length + 64;
            var resposta = Marshal.AllocHGlobal(tamanhoResposta);
            try
            {
                var opcoes = new IP_OPTION_INFORMATION { Ttl = (byte)ttl };
                var ip = BitConverter.ToUInt32(destino.GetAddressBytes(), 0);
                if (IcmpSendEcho(handle, ip, dados, (ushort)dados.Length, ref opcoes, resposta, (uint)tamanhoResposta, (uint)timeout) == 0)
                {
                    return (false, null, -1, false); // timeout ou rede inacessível: pacote perdido
                }

                var r = Marshal.PtrToStructure<ICMP_ECHO_REPLY>(resposta);
                if (r.Status is not (IpSucesso or IpTtlExpiradoTransito or IpTtlExpiradoRemontagem))
                {
                    return (false, null, -1, false);
                }
                return (true, new IPAddress(r.Address), r.RoundTripTime, r.Status == IpSucesso);
            }
            finally
            {
                Marshal.FreeHGlobal(resposta);
                IcmpCloseHandle(handle);
            }
        }
    }
}
