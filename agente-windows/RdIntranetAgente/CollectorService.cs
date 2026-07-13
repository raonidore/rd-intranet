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

        // ----------------------------------------------------------
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
        payload.MachineGuid = serialValido
            ? $"BIOS-{serialBios!.Trim()}"
            : $"REG-{ObterMachineGuidRegistro()}";
        payload.NumeroSerie = serialBios;

        // ----------------------------------------------------------
        // Sistema operacional / tipo do ativo
        // ProductType: 1 = estacao de trabalho, 2 = controlador de dominio, 3 = servidor
        using (var buscaSistema = new ManagementObjectSearcher("SELECT Caption, ProductType FROM Win32_OperatingSystem"))
        {
            foreach (ManagementObject so in buscaSistema.Get())
            {
                var caption = so["Caption"]?.ToString();
                var productType = so["ProductType"] != null ? Convert.ToUInt32(so["ProductType"]) : 1u;

                payload.SistemaOperacional = caption;
                payload.Tipo = productType == 1 ? "computador" : "servidor";
                payload.Funcao = payload.Tipo == "servidor" ? caption : null;
            }
        }

        // ----------------------------------------------------------
        // Fabricante / modelo / memoria / usuario logado
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
                    payload.MemoriaRam = $"{Math.Round(bytes / 1024.0 / 1024.0 / 1024.0, 1)} GB";
                }

                var modeloTexto = payload.Modelo ?? "";
                payload.Virtualizado = (modeloTexto.Contains("Virtual") || modeloTexto.Contains("VMware")
                    || modeloTexto.Contains("KVM") || modeloTexto.Contains("VirtualBox")) ? "Sim" : "Não";
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

        payload.Ip = ObterPrimeiroIp();
        payload.Programas = ObterProgramasInstalados();
        payload.Alertas = ObterAlertas(eventosDesde ?? DateTime.Now.AddHours(-24));

        return payload;
    }

    private static string ObterMachineGuidRegistro()
    {
        using var chave = Registry.LocalMachine.OpenSubKey(@"SOFTWARE\Microsoft\Cryptography");
        return chave?.GetValue("MachineGuid")?.ToString() ?? Environment.MachineName;
    }

    private static string? ObterPrimeiroIp()
    {
        using var busca = new ManagementObjectSearcher("SELECT IPAddress FROM Win32_NetworkAdapterConfiguration WHERE IPEnabled = True");

        foreach (ManagementObject nic in busca.Get())
        {
            if (nic["IPAddress"] is string[] enderecos && enderecos.Length > 0)
            {
                return enderecos[0];
            }
        }

        return null;
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

                programas.Add(new ProgramaItem
                {
                    Nome = nome,
                    Versao = sub.GetValue("DisplayVersion") as string
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
