using System.IO;
using System.Text.Json;

namespace RdIntranetAgente;

public class Config
{
    public string ServerUrl { get; set; } = "";
    public string ApiKey { get; set; } = "";
    public int IntervaloMinutos { get; set; } = 15;

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
