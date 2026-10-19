using System.Diagnostics;
using System.IO;
using System.Management;
using System.Runtime.InteropServices;
using System.Text.Json;
using System.Text.Json.Serialization;
using System.Text.RegularExpressions;

namespace RdIntranetAgente;

/// <summary>
/// Alterações de rede pedidas pelo portal (aba Network): desativar placa,
/// desconectar o Wi-Fi sem reconectar sozinho e trocar IP/DNS. Todas com a
/// mesma janela de segurança do DHCP/Firewall do servidor: o agente responde
/// antes de mexer, aplica 2 s depois e, se o portal não confirmar dentro do
/// prazo (a placa desativada era a que levava ao servidor, por exemplo),
/// volta sozinho ao que estava. A pendência fica em ProgramData: se o agente
/// fechar ou a máquina reiniciar no meio, a próxima instância elevada reverte.
/// </summary>
public static class RedeControleService
{
    public const int JanelaPadraoSegundos = 120;

    public sealed class PerfilWifi
    {
        public string Nome { get; set; } = "";
        public string Modo { get; set; } = "auto";
    }

    public sealed class Pendencia
    {
        public string Id { get; set; } = "";
        public string Tipo { get; set; } = ""; // desativar | wifi_desconectar | ip
        public int AdapterId { get; set; }
        public string Mac { get; set; } = "";
        public string NomeConexao { get; set; } = "";
        public Guid InterfaceGuid { get; set; }
        public string? PerfilConectado { get; set; }
        public List<PerfilWifi> Perfis { get; set; } = new();
        public NetworkService.Snapshot? IpAnterior { get; set; }
        public string Descricao { get; set; } = "";
        public DateTime CriadoEm { get; set; }
        public DateTime ExpiraEm { get; set; }
    }

    private sealed class PedidoAdaptador
    {
        [JsonPropertyName("adapter_id")]
        public int AdapterId { get; set; }

        [JsonPropertyName("janela")]
        public int Janela { get; set; } = JanelaPadraoSegundos;
    }

    private static readonly string Pasta = Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData), "RDIntranetAgent");
    private static readonly string CaminhoPendencia = Path.Combine(Pasta, "rede-reversao-pendente.json");
    // Perfis de Wi-Fi que o portal deixou em "conectar manualmente" (por GUID da interface) -- "Reconectar" volta só eles.
    private static readonly string CaminhoWifiManual = Path.Combine(Pasta, "rede-wifi-manual.json");
    private static readonly object Trava = new();
    // Falha ao aplicar depois de já ter respondido "agendado": a confirmação devolve o motivo.
    private static readonly Dictionary<string, string> FalhasAoAplicar = new();
    private static System.Threading.Timer? _vigia;

    /// <summary>Só instância elevada: confere a cada 5 s se uma alteração passou do prazo sem confirmação.</summary>
    public static void IniciarVigia()
    {
        if (!Program.EstaElevado() || _vigia != null) return;
        _vigia = new System.Threading.Timer(_ => VerificarPrazo(), null, TimeSpan.FromSeconds(3), TimeSpan.FromSeconds(5));
    }

    public static Pendencia? PendenciaAtual()
    {
        lock (Trava)
        {
            try
            {
                return File.Exists(CaminhoPendencia)
                    ? JsonSerializer.Deserialize<Pendencia>(File.ReadAllText(CaminhoPendencia))
                    : null;
            }
            catch { return null; }
        }
    }

    public static HashSet<Guid> InterfacesComWifiManual() => CarregarWifiManual().Keys.ToHashSet();

    // ---------------------------------------------------------------- ações

    public static object Desativar(string? parametro)
    {
        var pedido = LerPedido(parametro);
        var placa = BuscarPlaca(pedido.AdapterId);
        if (!placa.Habilitada) throw new InvalidOperationException("Esta placa já está desativada.");

        var pendencia = NovaPendencia("desativar", placa, pedido.Janela, $"Placa \"{placa.NomeConexao}\" desativada");
        return Agendar(pendencia, () => InvocarPlaca(placa.Index, "Disable"));
    }

    public static object Ativar(string? parametro)
    {
        var pedido = LerPedido(parametro);
        var placa = BuscarPlaca(pedido.AdapterId);
        if (placa.Habilitada) return new { sucesso = true, mensagem = "A placa já está ativa." };

        InvocarPlaca(placa.Index, "Enable");
        LogAtividade.Registrar(NivelAtividade.Aviso, "REDE", $"Placa \"{placa.NomeConexao}\" ativada pelo portal.");
        return new { sucesso = true, mensagem = $"Placa \"{placa.NomeConexao}\" ativada." };
    }

    public static object DesconectarWifi(string? parametro)
    {
        var pedido = LerPedido(parametro);
        var placa = BuscarPlaca(pedido.AdapterId);
        if (!placa.Habilitada) throw new InvalidOperationException("Esta placa está desativada.");
        if (placa.InterfaceGuid == Guid.Empty) throw new InvalidOperationException("Não consegui identificar a interface Wi-Fi.");

        var perfis = ListarPerfisWifi(placa.InterfaceGuid);
        if (perfis.Count == 0 && NetworkService.PerfilWifiConectado(placa.InterfaceGuid) == null)
            throw new InvalidOperationException("Esta placa não tem redes Wi-Fi salvas nem conexão ativa.");

        var pendencia = NovaPendencia("wifi_desconectar", placa, pedido.Janela, $"Wi-Fi \"{placa.NomeConexao}\" desconectado (sem reconexão automática)");
        pendencia.Perfis = perfis;
        pendencia.PerfilConectado = NetworkService.PerfilWifiConectado(placa.InterfaceGuid);

        return Agendar(pendencia, () =>
        {
            foreach (var perfil in perfis.Where(p => p.Modo == "auto"))
            {
                Netsh($"wlan set profileparameter name=\"{perfil.Nome}\" interface=\"{placa.NomeConexao}\" connectionmode=manual");
            }
            Netsh($"wlan disconnect interface=\"{placa.NomeConexao}\"", exigirSucesso: true);
            GuardarWifiManual(placa.InterfaceGuid, perfis.Where(p => p.Modo == "auto").Select(p => p.Nome).ToList());
        });
    }

    public static object ReconectarWifi(string? parametro)
    {
        var pedido = LerPedido(parametro);
        var placa = BuscarPlaca(pedido.AdapterId);
        if (!placa.Habilitada) throw new InvalidOperationException("A placa está desativada -- ative antes de reconectar.");

        var guardados = CarregarWifiManual();
        var perfis = guardados.TryGetValue(placa.InterfaceGuid, out var lista) && lista.Count > 0
            ? lista
            : ListarPerfisWifi(placa.InterfaceGuid).Select(p => p.Nome).ToList();
        if (perfis.Count == 0) throw new InvalidOperationException("Não há redes Wi-Fi salvas nesta placa.");

        foreach (var perfil in perfis)
        {
            Netsh($"wlan set profileparameter name=\"{perfil}\" interface=\"{placa.NomeConexao}\" connectionmode=auto");
        }
        var conectou = perfis.Any(perfil => Netsh($"wlan connect name=\"{perfil}\" interface=\"{placa.NomeConexao}\"").Codigo == 0);
        RemoverWifiManual(placa.InterfaceGuid);

        LogAtividade.Registrar(NivelAtividade.Aviso, "REDE", $"Wi-Fi \"{placa.NomeConexao}\" voltou a conectar automaticamente (pedido do portal).");
        return new
        {
            sucesso = true,
            mensagem = conectou
                ? "Reconexão automática religada e conexão solicitada ao Windows."
                : "Reconexão automática religada; o Windows conecta quando alguma rede salva estiver ao alcance."
        };
    }

    /// <summary>Troca de IP/DNS pelo portal -- mesma janela; a reversão usa o retrato tirado antes.</summary>
    public static object AplicarIp(string? parametro, int janela, Func<string?, (NetworkService.Snapshot Anterior, Action Aplicar)> preparar)
    {
        var (anterior, aplicar) = preparar(parametro);
        var placa = BuscarPlaca(anterior.AdapterId);
        var pendencia = NovaPendencia("ip", placa, janela, $"Configuração IPv4 de \"{placa.NomeConexao}\" alterada");
        pendencia.IpAnterior = anterior;
        return Agendar(pendencia, aplicar);
    }

    public static object Confirmar(string? id)
    {
        id = (id ?? "").Trim();
        lock (Trava)
        {
            if (FalhasAoAplicar.Remove(id, out var falha))
                throw new InvalidOperationException("A alteração não foi aplicada: " + falha);
        }

        var pendencia = PendenciaAtual();
        if (pendencia == null || pendencia.Id != id)
            throw new InvalidOperationException("Não há alteração aguardando confirmação -- o prazo pode ter acabado e o agente já voltou a configuração anterior.");

        ApagarPendencia();
        LogAtividade.Registrar(NivelAtividade.Sucesso, "REDE", $"{pendencia.Descricao}: alteração confirmada pelo portal.");
        return new { sucesso = true, mensagem = "Alteração confirmada e mantida." };
    }

    public static object ReverterAgora(string? id)
    {
        var pendencia = PendenciaAtual();
        if (pendencia == null || pendencia.Id != (id ?? "").Trim())
            throw new InvalidOperationException("Não há alteração aguardando confirmação.");
        if (!Reivindicar(out pendencia)) throw new InvalidOperationException("A reversão já está em andamento.");

        Reverter(pendencia!, "a pedido do portal");
        return new { sucesso = true, mensagem = "Configuração anterior restaurada." };
    }

    // ------------------------------------------------------------- mecânica

    private static Pendencia NovaPendencia(string tipo, Placa placa, int janela, string descricao)
    {
        if (PendenciaAtual() != null)
            throw new InvalidOperationException("Já existe uma alteração de rede aguardando confirmação nesta máquina. Confirme ou reverta antes de fazer outra.");

        return new Pendencia
        {
            Id = Guid.NewGuid().ToString("N"),
            Tipo = tipo,
            AdapterId = placa.Index,
            Mac = placa.Mac,
            NomeConexao = placa.NomeConexao,
            InterfaceGuid = placa.InterfaceGuid,
            Descricao = descricao,
            CriadoEm = DateTime.UtcNow,
            ExpiraEm = DateTime.UtcNow.AddSeconds(Math.Clamp(janela, 60, 600))
        };
    }

    /// <summary>Responde antes de mexer: se a placa for a que leva ao servidor, a resposta não chegaria depois.</summary>
    private static object Agendar(Pendencia pendencia, Action aplicar)
    {
        SalvarPendencia(pendencia);
        _ = Task.Run(async () =>
        {
            await Task.Delay(2000);
            try
            {
                aplicar();
                LogAtividade.Registrar(NivelAtividade.Aviso, "REDE", $"{pendencia.Descricao} pelo portal; volta sozinho às {pendencia.ExpiraEm.ToLocalTime():HH:mm:ss} se não houver confirmação.");
            }
            catch (Exception ex)
            {
                lock (Trava) FalhasAoAplicar[pendencia.Id] = ex.Message;
                LogAtividade.Registrar(NivelAtividade.Erro, "REDE", $"{pendencia.Descricao}: falhou ao aplicar ({ex.Message}). Restaurando o que estava.");
                if (Reivindicar(out var reivindicada)) Reverter(reivindicada!, "falha ao aplicar");
            }
        });

        var segundos = (int)Math.Round((pendencia.ExpiraEm - DateTime.UtcNow).TotalSeconds);
        return new
        {
            sucesso = true,
            agendado = true,
            confirmacao_id = pendencia.Id,
            janela_segundos = segundos,
            reverte_em = pendencia.ExpiraEm.ToLocalTime().ToString("yyyy-MM-ddTHH:mm:ss"),
            mensagem = $"Aplicando. Se não for confirmado em {segundos} s, o agente volta a configuração anterior sozinho."
        };
    }

    private static void VerificarPrazo()
    {
        try
        {
            var pendencia = PendenciaAtual();
            if (pendencia == null || DateTime.UtcNow < pendencia.ExpiraEm) return;
            if (Reivindicar(out var reivindicada)) Reverter(reivindicada!, "prazo de confirmação esgotado");
        }
        catch
        {
            // tenta de novo no próximo ciclo
        }
    }

    /// <summary>Move o arquivo da pendência antes de reverter: com duas sessões elevadas (TS), só uma reverte.</summary>
    private static bool Reivindicar(out Pendencia? pendencia)
    {
        pendencia = null;
        lock (Trava)
        {
            var tomado = CaminhoPendencia + "." + Environment.ProcessId;
            try
            {
                File.Move(CaminhoPendencia, tomado);
                pendencia = JsonSerializer.Deserialize<Pendencia>(File.ReadAllText(tomado));
                File.Delete(tomado);
                return pendencia != null;
            }
            catch
            {
                return false;
            }
        }
    }

    private static void Reverter(Pendencia pendencia, string motivo)
    {
        try
        {
            switch (pendencia.Tipo)
            {
                case "desativar":
                    InvocarPlaca(pendencia.AdapterId, "Enable");
                    break;

                case "wifi_desconectar":
                    foreach (var perfil in pendencia.Perfis.Where(p => p.Modo == "auto"))
                    {
                        Netsh($"wlan set profileparameter name=\"{perfil.Nome}\" interface=\"{pendencia.NomeConexao}\" connectionmode=auto");
                    }
                    RemoverWifiManual(pendencia.InterfaceGuid);
                    var alvo = pendencia.PerfilConectado ?? pendencia.Perfis.FirstOrDefault(p => p.Modo == "auto")?.Nome;
                    if (alvo != null) Netsh($"wlan connect name=\"{alvo}\" interface=\"{pendencia.NomeConexao}\"");
                    break;

                case "ip":
                    if (pendencia.IpAnterior != null) NetworkService.RestaurarSnapshot(pendencia.IpAnterior);
                    break;
            }
            LogAtividade.Registrar(NivelAtividade.Aviso, "REDE", $"{pendencia.Descricao}: revertido ({motivo}).");
        }
        catch (Exception ex)
        {
            LogAtividade.Registrar(NivelAtividade.Erro, "REDE", $"{pendencia.Descricao}: falha ao reverter ({motivo}): {ex.Message}");
            throw;
        }
    }

    private static PedidoAdaptador LerPedido(string? parametro)
    {
        if (!Program.EstaElevado())
            throw new InvalidOperationException("O agente desta sessão não está elevado -- desativar placa e mexer no Wi-Fi exigem administrador.");
        PedidoAdaptador? pedido;
        try { pedido = JsonSerializer.Deserialize<PedidoAdaptador>(parametro ?? ""); }
        catch { pedido = null; }
        if (pedido == null || pedido.AdapterId <= 0) throw new InvalidOperationException("Adaptador inválido.");
        return pedido;
    }

    private sealed record Placa(int Index, string NomeConexao, string Mac, Guid InterfaceGuid, bool Habilitada);

    private static Placa BuscarPlaca(int index)
    {
        using var busca = new ManagementObjectSearcher($"SELECT Index, NetConnectionID, MACAddress, GUID, NetEnabled, ConfigManagerErrorCode FROM Win32_NetworkAdapter WHERE Index = {index}");
        using var itens = busca.Get();
        var item = itens.Cast<ManagementObject>().FirstOrDefault()
            ?? throw new InvalidOperationException("Placa não encontrada -- atualize a lista.");
        using (item)
        {
            var nome = item["NetConnectionID"]?.ToString();
            if (string.IsNullOrWhiteSpace(nome)) throw new InvalidOperationException("Esta placa não tem nome de conexão no Windows.");
            if (nome.Contains('"')) throw new InvalidOperationException("Nome de conexão com aspas não é suportado.");
            var desativada = Convert.ToUInt32(item["ConfigManagerErrorCode"] ?? 0u) == 22;
            return new Placa(
                index,
                nome,
                item["MACAddress"]?.ToString() ?? "",
                Guid.TryParse(item["GUID"]?.ToString(), out var guid) ? guid : Guid.Empty,
                !desativada && Convert.ToBoolean(item["NetEnabled"] ?? false));
        }
    }

    private static void InvocarPlaca(int index, string metodo)
    {
        using var busca = new ManagementObjectSearcher($"SELECT * FROM Win32_NetworkAdapter WHERE Index = {index}");
        using var itens = busca.Get();
        var item = itens.Cast<ManagementObject>().FirstOrDefault()
            ?? throw new InvalidOperationException("Placa não encontrada.");
        using (item)
        using (var resultado = item.InvokeMethod(metodo, null, null))
        {
            var codigo = Convert.ToUInt32(resultado?["ReturnValue"] ?? 0u);
            if (codigo != 0)
                throw new InvalidOperationException(codigo == 5
                    ? "O Windows negou a operação (acesso negado)."
                    : $"O Windows recusou {(metodo == "Disable" ? "desativar" : "ativar")} a placa (código {codigo}).");
        }
    }

    private static (int Codigo, string Saida) Netsh(string argumentos, bool exigirSucesso = false)
    {
        var info = new ProcessStartInfo("netsh.exe", argumentos)
        {
            UseShellExecute = false,
            CreateNoWindow = true,
            RedirectStandardOutput = true,
            RedirectStandardError = true
        };
        using var processo = Process.Start(info) ?? throw new InvalidOperationException("Não consegui executar o netsh.");
        var saida = processo.StandardOutput.ReadToEnd() + processo.StandardError.ReadToEnd();
        if (!processo.WaitForExit(20_000))
        {
            try { processo.Kill(); } catch { }
            throw new InvalidOperationException("O netsh não respondeu em 20 s.");
        }
        if (exigirSucesso && processo.ExitCode != 0)
            throw new InvalidOperationException("netsh: " + saida.Trim());
        return (processo.ExitCode, saida);
    }

    // ----------------------------------------------------------- persistência

    private static void SalvarPendencia(Pendencia pendencia)
    {
        lock (Trava)
        {
            Directory.CreateDirectory(Pasta);
            File.WriteAllText(CaminhoPendencia, JsonSerializer.Serialize(pendencia));
        }
    }

    private static void ApagarPendencia()
    {
        lock (Trava)
        {
            try { File.Delete(CaminhoPendencia); } catch { }
        }
    }

    private static Dictionary<Guid, List<string>> CarregarWifiManual()
    {
        lock (Trava)
        {
            try
            {
                return File.Exists(CaminhoWifiManual)
                    ? JsonSerializer.Deserialize<Dictionary<Guid, List<string>>>(File.ReadAllText(CaminhoWifiManual)) ?? new()
                    : new();
            }
            catch { return new(); }
        }
    }

    private static void GuardarWifiManual(Guid interfaceGuid, List<string> perfis)
    {
        lock (Trava)
        {
            var atuais = CarregarWifiManual();
            atuais[interfaceGuid] = perfis.Union(atuais.TryGetValue(interfaceGuid, out var antes) ? antes : new()).ToList();
            Directory.CreateDirectory(Pasta);
            File.WriteAllText(CaminhoWifiManual, JsonSerializer.Serialize(atuais));
        }
    }

    private static void RemoverWifiManual(Guid interfaceGuid)
    {
        lock (Trava)
        {
            var atuais = CarregarWifiManual();
            if (!atuais.Remove(interfaceGuid)) return;
            Directory.CreateDirectory(Pasta);
            File.WriteAllText(CaminhoWifiManual, JsonSerializer.Serialize(atuais));
        }
    }

    // ------------------------------------------------------- perfis do Wi-Fi

    [DllImport("wlanapi.dll")]
    private static extern int WlanOpenHandle(uint clientVersion, IntPtr reserved, out uint negotiatedVersion, out IntPtr clientHandle);

    [DllImport("wlanapi.dll")]
    private static extern int WlanGetProfileList(IntPtr clientHandle, ref Guid interfaceGuid, IntPtr reserved, out IntPtr profileList);

    [DllImport("wlanapi.dll", CharSet = CharSet.Unicode)]
    private static extern int WlanGetProfile(IntPtr clientHandle, ref Guid interfaceGuid, string profileName, IntPtr reserved, out IntPtr profileXml, ref uint flags, out uint grantedAccess);

    [DllImport("wlanapi.dll")]
    private static extern void WlanFreeMemory(IntPtr memory);

    [DllImport("wlanapi.dll")]
    private static extern int WlanCloseHandle(IntPtr clientHandle, IntPtr reserved);

    /// <summary>Redes salvas da interface e se cada uma conecta sozinha (connectionMode do XML do perfil).</summary>
    private static List<PerfilWifi> ListarPerfisWifi(Guid interfaceGuid)
    {
        var perfis = new List<PerfilWifi>();
        IntPtr cliente = IntPtr.Zero, lista = IntPtr.Zero;
        try
        {
            if (WlanOpenHandle(2, IntPtr.Zero, out _, out cliente) != 0) return perfis;
            if (WlanGetProfileList(cliente, ref interfaceGuid, IntPtr.Zero, out lista) != 0) return perfis;
            var quantidade = Marshal.ReadInt32(lista);
            const int tamanhoItem = 256 * 2 + 4; // WCHAR strProfileName[256] + DWORD dwFlags
            for (var i = 0; i < quantidade; i++)
            {
                var nome = Marshal.PtrToStringUni(IntPtr.Add(lista, 8 + i * tamanhoItem));
                if (string.IsNullOrWhiteSpace(nome) || nome.Contains('"')) continue;
                var modo = "auto";
                uint flags = 0;
                if (WlanGetProfile(cliente, ref interfaceGuid, nome, IntPtr.Zero, out var xml, ref flags, out _) == 0)
                {
                    try
                    {
                        var texto = Marshal.PtrToStringUni(xml) ?? "";
                        var m = Regex.Match(texto, @"<connectionMode>\s*(\w+)\s*</connectionMode>", RegexOptions.IgnoreCase);
                        if (m.Success) modo = m.Groups[1].Value.ToLowerInvariant();
                    }
                    finally { WlanFreeMemory(xml); }
                }
                perfis.Add(new PerfilWifi { Nome = nome, Modo = modo });
            }
        }
        catch
        {
            // sem wlanapi (serviço WLAN parado) -- segue sem perfis
        }
        finally
        {
            if (lista != IntPtr.Zero) WlanFreeMemory(lista);
            if (cliente != IntPtr.Zero) WlanCloseHandle(cliente, IntPtr.Zero);
        }
        return perfis;
    }
}
