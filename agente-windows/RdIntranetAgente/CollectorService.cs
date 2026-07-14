using System.Collections.Generic;
using System.Diagnostics;
using System.Linq;
using System.Management;
using Microsoft.Win32;
using RdIntranetAgente.Models;

namespace RdIntranetAgente;

public static class CollectorService
{
    private static readonly string[] SeriaisInvalidos =
    {
        "", "To be filled by O.E.M.", "None", "System Serial Number", "0", "Default string"
    };

    public static CheckinPayload Coletar(DateTime? eventosDesde)
    {
        var payload = new CheckinPayload
        {
            Nome = Environment.MachineName
        };

        var (machineGuid, serialBios) = ObterMachineGuidComSerial();
        payload.MachineGuid = machineGuid;
        payload.NumeroSerie = serialBios;

        // ----------------------------------------------------------
        // Sistema operacional / tipo do ativo / uptime / memoria livre
        // ProductType: 1 = estacao de trabalho, 2 = controlador de dominio, 3 = servidor
        double memoriaLivreGb = 0;
        using (var buscaSistema = new ManagementObjectSearcher("SELECT Caption, ProductType, LastBootUpTime, FreePhysicalMemory FROM Win32_OperatingSystem"))
        {
            foreach (ManagementObject so in buscaSistema.Get())
            {
                var caption = so["Caption"]?.ToString();
                var productType = so["ProductType"] != null ? Convert.ToUInt32(so["ProductType"]) : 1u;

                payload.SistemaOperacional = caption;
                payload.Tipo = productType == 1 ? "computador" : "servidor";
                payload.Funcao = payload.Tipo == "servidor" ? caption : null;

                if (so["LastBootUpTime"] is string ultimoBootCim)
                {
                    var ultimoBoot = ManagementDateTimeConverter.ToDateTime(ultimoBootCim);
                    payload.LigadoDesde = ultimoBoot.ToString("yyyy-MM-dd HH:mm:ss");
                }

                if (so["FreePhysicalMemory"] != null)
                {
                    // FreePhysicalMemory vem em KB
                    memoriaLivreGb = Convert.ToInt64(so["FreePhysicalMemory"]) / 1024.0 / 1024.0;
                }
            }
        }

        // ----------------------------------------------------------
        // Fabricante / modelo / memoria / usuario logado
        double memoriaTotalGb = 0;
        using (var buscaComputador = new ManagementObjectSearcher("SELECT Manufacturer, Model, TotalPhysicalMemory, UserName FROM Win32_ComputerSystem"))
        {
            foreach (ManagementObject cs in buscaComputador.Get())
            {
                payload.Marca = cs["Manufacturer"]?.ToString();
                payload.Modelo = cs["Model"]?.ToString();
                payload.UsuarioLogado = cs["UserName"]?.ToString();

                if (cs["TotalPhysicalMemory"] != null)
                {
                    var bytes = Convert.ToInt64(cs["TotalPhysicalMemory"]);
                    memoriaTotalGb = bytes / 1024.0 / 1024.0 / 1024.0;
                    payload.MemoriaRam = $"{Math.Round(memoriaTotalGb, 1)} GB";
                }

                var modeloTexto = payload.Modelo ?? "";
                payload.Virtualizado = (modeloTexto.Contains("Virtual") || modeloTexto.Contains("VMware")
                    || modeloTexto.Contains("KVM") || modeloTexto.Contains("VirtualBox")) ? "Sim" : "Não";
            }
        }

        if (memoriaTotalGb > 0)
        {
            payload.MemoriaUsada = $"{Math.Round(Math.Max(0, memoriaTotalGb - memoriaLivreGb), 1)} GB";
        }

        // ----------------------------------------------------------
        // Placa de video / som / tipo de memoria (Componentes)
        using (var buscaVideo = new ManagementObjectSearcher("SELECT Name FROM Win32_VideoController"))
        {
            foreach (ManagementObject v in buscaVideo.Get())
            {
                payload.PlacaVideo = v["Name"]?.ToString();
                break;
            }
        }

        using (var buscaSom = new ManagementObjectSearcher("SELECT Name FROM Win32_SoundDevice"))
        {
            foreach (ManagementObject s in buscaSom.Get())
            {
                payload.PlacaSom = s["Name"]?.ToString();
                break;
            }
        }

        var tiposMemoria = new Dictionary<int, string>
        {
            { 20, "DDR" }, { 21, "DDR2" }, { 22, "DDR2 FB-DIMM" }, { 24, "DDR3" }, { 26, "DDR4" }, { 34, "DDR5" }
        };
        using (var buscaMemoria = new ManagementObjectSearcher("SELECT SMBIOSMemoryType FROM Win32_PhysicalMemory"))
        {
            foreach (ManagementObject m in buscaMemoria.Get())
            {
                if (m["SMBIOSMemoryType"] != null)
                {
                    var codigo = Convert.ToInt32(m["SMBIOSMemoryType"]);
                    if (tiposMemoria.TryGetValue(codigo, out var nomeTipo))
                    {
                        payload.TipoMemoria = nomeTipo;
                    }
                }
                break;
            }
        }

        // ----------------------------------------------------------
        // Processador
        using (var buscaProcessador = new ManagementObjectSearcher("SELECT Name FROM Win32_Processor"))
        {
            foreach (ManagementObject cpu in buscaProcessador.Get())
            {
                payload.Processador = cpu["Name"]?.ToString();
                break;
            }
        }

        // ----------------------------------------------------------
        // Placa-mãe
        using (var buscaPlaca = new ManagementObjectSearcher("SELECT Manufacturer, Product FROM Win32_BaseBoard"))
        {
            foreach (ManagementObject bb in buscaPlaca.Get())
            {
                payload.PlacaMae = $"{bb["Manufacturer"]} {bb["Product"]}".Trim();
            }
        }

        // ----------------------------------------------------------
        // Discos
        var discos = new List<string>();
        using (var buscaDiscos = new ManagementObjectSearcher("SELECT Model, Size FROM Win32_DiskDrive"))
        {
            foreach (ManagementObject disco in buscaDiscos.Get())
            {
                if (disco["Size"] == null) continue;

                var tamanhoGb = Math.Round(Convert.ToInt64(disco["Size"]) / 1024.0 / 1024.0 / 1024.0, 0);
                discos.Add($"{disco["Model"]} ({tamanhoGb} GB)");
            }
        }
        payload.Armazenamento = string.Join(", ", discos);

        payload.Redes = ObterRedes();
        payload.Ip = payload.Redes.Count > 0 ? payload.Redes[0].Ip : null;
        payload.Volumes = ObterVolumes();
        payload.Portas = ObterPortas();
        payload.PortasRede = ObterPortasRede();
        payload.MemoriaModulos = ObterModulosMemoria();
        payload.Programas = ObterProgramasInstalados();
        payload.AtualizacoesWindows = ObterAtualizacoesWindows();
        payload.Alertas = ObterAlertas(eventosDesde ?? DateTime.Now.AddHours(-24));

        return payload;
    }

    /// <summary>
    /// Mesma identificação estável usada no checkin completo, mas isolada
    /// aqui pra poder ser chamada sozinha (sem o resto da coleta via WMI,
    /// que é cara) -- é assim que o heartbeat, chamado a cada poucos
    /// segundos, consegue ser barato: o valor é calculado uma vez só, no
    /// início do processo, e reaproveitado em todo ping seguinte.
    /// </summary>
    public static string ObterMachineGuid() => ObterMachineGuidComSerial().MachineGuid;

    private static (string MachineGuid, string? SerialBios) ObterMachineGuidComSerial()
    {
        // Identificacao estavel da maquina -- preferimos o numero de
        // serie da BIOS (sobrevive a reinstalacao do Windows); se vier
        // vazio ou for um valor generico de fabricante/VM sem serial
        // real, caimos pro MachineGuid do registro.
        string? serialBios = null;
        using (var buscaBios = new ManagementObjectSearcher("SELECT SerialNumber FROM Win32_BIOS"))
        {
            foreach (ManagementObject bios in buscaBios.Get())
            {
                serialBios = bios["SerialNumber"]?.ToString();
            }
        }

        var serialValido = serialBios != null && !SeriaisInvalidos.Contains(serialBios.Trim());
        var machineGuid = serialValido
            ? $"BIOS-{serialBios!.Trim()}"
            : $"REG-{ObterMachineGuidRegistro()}";

        return (machineGuid, serialBios);
    }

    private static string ObterMachineGuidRegistro()
    {
        using var chave = Registry.LocalMachine.OpenSubKey(@"SOFTWARE\Microsoft\Cryptography");
        return chave?.GetValue("MachineGuid")?.ToString() ?? Environment.MachineName;
    }

    /// <summary>Um item por combinação adaptador+IP (um adaptador com IPv4 e IPv6 vira duas linhas).</summary>
    private static List<RedeItem> ObterRedes()
    {
        var redes = new List<RedeItem>();

        using var busca = new ManagementObjectSearcher("SELECT Description, MACAddress, IPAddress FROM Win32_NetworkAdapterConfiguration WHERE IPEnabled = True");

        foreach (ManagementObject nic in busca.Get())
        {
            if (nic["IPAddress"] is not string[] enderecos) continue;

            var nome = nic["Description"]?.ToString();
            var mac = nic["MACAddress"]?.ToString();

            foreach (var endereco in enderecos)
            {
                redes.Add(new RedeItem { NomeAdaptador = nome, Mac = mac, Ip = endereco });
            }
        }

        return redes;
    }

    /// <summary>
    /// Volumes lógicos (unidades com letra, ex: C:, D:) -- diferente do
    /// Armazenamento acima, que é o disco físico inteiro. Tenta associar
    /// cada volume ao disco físico por trás dele (modelo/fabricante/série)
    /// via ManagementObject.GetRelated -- nem sempre disponível (discos
    /// removíveis, alguns controladores RAID/virtuais não expõem isso).
    /// </summary>
    private static List<VolumeItem> ObterVolumes()
    {
        var volumes = new List<VolumeItem>();

        using var busca = new ManagementObjectSearcher("SELECT DeviceID, Size, FreeSpace FROM Win32_LogicalDisk WHERE DriveType = 3");

        foreach (ManagementObject disco in busca.Get())
        {
            if (disco["Size"] == null) continue;

            var totalGb = Convert.ToInt64(disco["Size"]) / 1024.0 / 1024.0 / 1024.0;
            var livreGb = disco["FreeSpace"] != null ? Convert.ToInt64(disco["FreeSpace"]) / 1024.0 / 1024.0 / 1024.0 : 0;

            var volume = new VolumeItem
            {
                Unidade = disco["DeviceID"]?.ToString() ?? "",
                TotalGb = Math.Round(totalGb, 1),
                UsadoGb = Math.Round(Math.Max(0, totalGb - livreGb), 1)
            };

            try
            {
                foreach (ManagementObject particao in disco.GetRelated("Win32_DiskPartition"))
                {
                    foreach (ManagementObject discoFisico in particao.GetRelated("Win32_DiskDrive"))
                    {
                        volume.ModeloDisco = discoFisico["Model"]?.ToString();
                        volume.FabricanteDisco = discoFisico["Manufacturer"]?.ToString();
                        volume.SerialDisco = discoFisico["SerialNumber"]?.ToString()?.Trim();
                        break;
                    }
                    break;
                }
            }
            catch
            {
                // associacao disco-fisico -> volume pode falhar em discos
                // removiveis/de rede -- segue sem esses dados extras
            }

            volumes.Add(volume);
        }

        return volumes;
    }

    private static List<MemoriaItem> ObterModulosMemoria()
    {
        var modulos = new List<MemoriaItem>();

        using var busca = new ManagementObjectSearcher("SELECT Manufacturer, PartNumber, Capacity, ConfiguredClockSpeed, Speed, SerialNumber FROM Win32_PhysicalMemory");

        foreach (ManagementObject m in busca.Get())
        {
            int? frequencia = null;
            if (m["ConfiguredClockSpeed"] != null)
            {
                frequencia = Convert.ToInt32(m["ConfiguredClockSpeed"]);
            }
            else if (m["Speed"] != null)
            {
                frequencia = Convert.ToInt32(m["Speed"]);
            }

            modulos.Add(new MemoriaItem
            {
                Fabricante = m["Manufacturer"]?.ToString()?.Trim(),
                Modelo = m["PartNumber"]?.ToString()?.Trim(),
                CapacidadeGb = m["Capacity"] != null ? Math.Round(Convert.ToInt64(m["Capacity"]) / 1024.0 / 1024.0 / 1024.0, 1) : null,
                FrequenciaMhz = frequencia,
                NumeroSerie = m["SerialNumber"]?.ToString()?.Trim()
            });
        }

        return modulos;
    }

    /// <summary>
    /// Dispositivos USB conectados agora + portas seriais (COM)
    /// disponíveis. Portas de vídeo (HDMI/DP/VGA) ficam de fora -- o
    /// Windows não expõe isso de forma padronizada via WMI.
    /// </summary>
    private static List<PortaItem> ObterPortas()
    {
        var portas = new List<PortaItem>();

        using (var buscaUsb = new ManagementObjectSearcher("SELECT Name, PNPDeviceID FROM Win32_PnPEntity WHERE PNPDeviceID LIKE 'USB%'"))
        {
            foreach (ManagementObject dispositivo in buscaUsb.Get())
            {
                var nome = dispositivo["Name"]?.ToString();
                if (string.IsNullOrWhiteSpace(nome)) continue;

                portas.Add(new PortaItem { Tipo = "usb", Descricao = nome });
            }
        }

        using (var buscaSerial = new ManagementObjectSearcher("SELECT DeviceID, Description FROM Win32_SerialPort"))
        {
            foreach (ManagementObject porta in buscaSerial.Get())
            {
                portas.Add(new PortaItem
                {
                    Tipo = "serial",
                    Descricao = $"{porta["DeviceID"]} - {porta["Description"]}"
                });
            }
        }

        return portas;
    }

    /// <summary>
    /// Portas de rede em escuta (LISTENING) -- pra auditoria de seguranca,
    /// mostra o que a maquina esta expondo na rede. Sem uma classe WMI
    /// pronta pra isso com PID incluido, usa o proprio netstat.exe
    /// (ja vem com o Windows) e faz parsing da saida -- mesma filosofia
    /// de "melhor esforco" ja usada no restante do agente.
    /// </summary>
    private static List<PortaRedeItem> ObterPortasRede()
    {
        var portas = new List<PortaRedeItem>();

        try
        {
            using var processo = new Process
            {
                StartInfo = new ProcessStartInfo
                {
                    FileName = "netstat.exe",
                    Arguments = "-ano",
                    RedirectStandardOutput = true,
                    UseShellExecute = false,
                    CreateNoWindow = true
                }
            };

            processo.Start();
            var saida = processo.StandardOutput.ReadToEnd();
            processo.WaitForExit(5000);

            foreach (var linhaBruta in saida.Split('\n'))
            {
                var campos = linhaBruta.Trim().Split(new[] { ' ', '\t' }, StringSplitOptions.RemoveEmptyEntries);
                if (campos.Length < 4) continue;

                string protocolo;
                if (campos[0].Equals("TCP", StringComparison.OrdinalIgnoreCase)) protocolo = "tcp";
                else if (campos[0].Equals("UDP", StringComparison.OrdinalIgnoreCase)) protocolo = "udp";
                else continue;

                // TCP: Proto Local Estrangeiro Estado PID (5 campos) -- so LISTENING interessa.
                // UDP: Proto Local Estrangeiro PID (4 campos) -- sempre "em escuta".
                if (protocolo == "tcp" && (campos.Length < 5 || !campos[3].Equals("LISTENING", StringComparison.OrdinalIgnoreCase)))
                {
                    continue;
                }

                if (!int.TryParse(campos[^1], out var pid)) continue;

                var enderecoLocal = campos[1];
                var posDoisPontos = enderecoLocal.LastIndexOf(':');
                if (posDoisPontos < 0) continue;

                var endereco = enderecoLocal[..posDoisPontos].Trim('[', ']');
                if (!int.TryParse(enderecoLocal[(posDoisPontos + 1)..], out var porta)) continue;

                string? nomeProcesso = null;
                try { nomeProcesso = Process.GetProcessById(pid).ProcessName; } catch { /* processo pode ja ter encerrado */ }

                portas.Add(new PortaRedeItem
                {
                    Protocolo = protocolo,
                    PortaLocal = porta,
                    EnderecoLocal = endereco,
                    Processo = nomeProcesso,
                    Pid = pid
                });
            }
        }
        catch
        {
            // netstat pode falhar em ambiente restrito -- segue sem essa lista
        }

        return portas
            .GroupBy(p => (p.Protocolo, p.PortaLocal, p.EnderecoLocal))
            .Select(g => g.First())
            .OrderBy(p => p.Protocolo).ThenBy(p => p.PortaLocal)
            .ToList();
    }

    /// <summary>
    /// Le direto do registro (chaves Uninstall), NAO usa Win32_Product --
    /// alem de lento, Win32_Product e conhecido por forcar uma
    /// reconfiguracao/reparo do MSI so de ser consultado.
    /// </summary>
    private static List<ProgramaItem> ObterProgramasInstalados()
    {
        var caminhos = new (RegistryKey Raiz, string Caminho)[]
        {
            (Registry.LocalMachine, @"SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall"),
            (Registry.LocalMachine, @"SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall"),
            (Registry.CurrentUser, @"SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall"),
        };

        var programas = new List<ProgramaItem>();

        foreach (var (raiz, caminho) in caminhos)
        {
            using var chaveUninstall = raiz.OpenSubKey(caminho);
            if (chaveUninstall == null) continue;

            foreach (var nomeSubchave in chaveUninstall.GetSubKeyNames())
            {
                using var sub = chaveUninstall.OpenSubKey(nomeSubchave);
                if (sub == null) continue;

                var nome = sub.GetValue("DisplayName") as string;
                if (string.IsNullOrWhiteSpace(nome)) continue;

                if (sub.GetValue("SystemComponent") is int componenteDoSistema && componenteDoSistema == 1) continue;

                string? dataInstalacao = null;
                if (sub.GetValue("InstallDate") is string bruta && bruta.Length == 8
                    && DateTime.TryParseExact(bruta, "yyyyMMdd", null, System.Globalization.DateTimeStyles.None, out var data))
                {
                    dataInstalacao = data.ToString("yyyy-MM-dd");
                }

                programas.Add(new ProgramaItem
                {
                    Nome = nome,
                    Versao = sub.GetValue("DisplayVersion") as string,
                    DataInstalacao = dataInstalacao,
                    UninstallString = sub.GetValue("UninstallString") as string
                });
            }
        }

        return programas
            .GroupBy(p => p.Nome, StringComparer.OrdinalIgnoreCase)
            .Select(g => g.First())
            .OrderBy(p => p.Nome, StringComparer.OrdinalIgnoreCase)
            .ToList();
    }

    /// <summary>
    /// Atualizações do Windows instaladas (hotfixes/KBs) -- mesmo local
    /// que o Get-HotFix do PowerShell usa por baixo.
    /// </summary>
    private static List<AtualizacaoItem> ObterAtualizacoesWindows()
    {
        var atualizacoes = new List<AtualizacaoItem>();

        using var busca = new ManagementObjectSearcher("SELECT HotFixID, Description, InstalledOn FROM Win32_QuickFixEngineering");

        foreach (ManagementObject qfe in busca.Get())
        {
            var kb = qfe["HotFixID"]?.ToString();
            if (string.IsNullOrWhiteSpace(kb)) continue;

            string? instaladoEm = null;
            // InstalledOn vem ora como [DateTime], ora como texto -- essa
            // classe WMI é conhecida por ser inconsistente entre versões/
            // idiomas do Windows.
            if (qfe["InstalledOn"] is DateTime dataDireta)
            {
                instaladoEm = dataDireta.ToString("yyyy-MM-dd");
            }
            else if (qfe["InstalledOn"] is string dataTexto && DateTime.TryParse(dataTexto, out var dataConvertida))
            {
                instaladoEm = dataConvertida.ToString("yyyy-MM-dd");
            }

            atualizacoes.Add(new AtualizacaoItem
            {
                Kb = kb,
                Descricao = qfe["Description"]?.ToString(),
                InstaladoEm = instaladoEm
            });
        }

        return atualizacoes;
    }

    /// <summary>
    /// So os eventos de Erro/Aviso NOVOS desde o ultimo checkin. As
    /// entradas do EventLog ficam em ordem cronologica (mais antiga
    /// primeiro) -- percorremos de tras pra frente e paramos assim que
    /// achamos algo mais velho que "desde", em vez de varrer o log
    /// inteiro toda vez (log de Sistema/Aplicativo pode ter dezenas de
    /// milhares de entradas numa maquina com muito tempo de uso).
    /// </summary>
    private static List<AlertaItem> ObterAlertas(DateTime desde)
    {
        var alertas = new List<AlertaItem>();

        foreach (var nomeLog in new[] { "System", "Application" })
        {
            try
            {
                using var log = new System.Diagnostics.EventLog(nomeLog);
                var entradas = log.Entries;

                for (int i = entradas.Count - 1; i >= 0 && alertas.Count < 200; i--)
                {
                    var entrada = entradas[i];

                    if (entrada.TimeGenerated <= desde) break;

                    if (entrada.EntryType != System.Diagnostics.EventLogEntryType.Error
                        && entrada.EntryType != System.Diagnostics.EventLogEntryType.Warning)
                    {
                        continue;
                    }

                    alertas.Add(new AlertaItem
                    {
                        Nivel = entrada.EntryType == System.Diagnostics.EventLogEntryType.Error ? "erro" : "aviso",
                        OrigemEvento = entrada.Source,
                        Mensagem = (entrada.Message ?? "").Split('\n')[0],
                        OcorridoEm = entrada.TimeGenerated.ToString("yyyy-MM-dd HH:mm:ss")
                    });
                }
            }
            catch
            {
                // log inacessivel (permissao) -- ignora e segue pro proximo
            }
        }

        return alertas;
    }
}
