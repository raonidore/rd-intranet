using System.IO;
using System.Text.Json;

namespace RdIntranetAgente;

public class Config
{
    public string ServerUrl { get; set; } = "";
    public string ApiKey { get; set; } = "";
    public int IntervaloMinutos { get; set; } = 15;
    public int HeartbeatSegundos { get; set; } = 1;
    public string ImpressoraEtiqueta { get; set; } = "";

    /// <summary>
    /// Escape manual pra reformatação: o identificador automático da
    /// máquina (BIOS/SMBIOS/registro -- ver CollectorService) muda quando
    /// o Windows é reinstalado numa máquina sem serial de BIOS confiável,
    /// o que criaria um ativo NOVO no inventário em vez de continuar o
    /// mesmo. Preenchendo isso aqui (a mesma "Identificador da máquina"
    /// que aparece na tela do Ativo no portal) antes do primeiro check-in
    /// pós-reformatação, o agente usa esse valor ao pé da letra, sem
    /// tentar detectar nada -- a máquina reformatada volta a ser
    /// reconhecida como o mesmo ativo de antes.
    /// </summary>
    public string MachineGuidOverride { get; set; } = "";

    public bool EstaConfigurado => !string.IsNullOrWhiteSpace(ServerUrl) && !string.IsNullOrWhiteSpace(ApiKey);

    private static string PastaDados => Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
        "RDIntranetAgent");

    private static string CaminhoArquivo => Path.Combine(PastaDados, "config.json");

    /// <summary>
    /// Prioridade: config.json do usuário (LocalAppData, criado pela tela de
    /// configuração) &gt; config.json ao lado do .exe (útil pra distribuição em
    /// massa via GPO/Intune, cada máquina já recebe o arquivo pronto) &gt;
    /// valores vazios (dispara o formulário de primeira configuração).
    /// </summary>
    public static Config Carregar()
    {
        try
        {
            if (File.Exists(CaminhoArquivo))
            {
                var json = File.ReadAllText(CaminhoArquivo);
                return JsonSerializer.Deserialize<Config>(json) ?? new Config();
            }

            var caminhoJuntoDoExe = Path.Combine(AppContext.BaseDirectory, "config.json");
            if (File.Exists(caminhoJuntoDoExe))
            {
                var json = File.ReadAllText(caminhoJuntoDoExe);
                return JsonSerializer.Deserialize<Config>(json) ?? new Config();
            }
        }
        catch
        {
            // config corrompido -- cai pro formulário de configuração inicial
        }

        return new Config();
    }

    public void Salvar()
    {
        if (!Directory.Exists(PastaDados))
        {
            Directory.CreateDirectory(PastaDados);
        }

        File.WriteAllText(CaminhoArquivo, JsonSerializer.Serialize(this, new JsonSerializerOptions { WriteIndented = true }));
    }
}
