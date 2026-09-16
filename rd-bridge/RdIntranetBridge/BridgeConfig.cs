using System.Text.Json;

namespace RdIntranetBridge;

/// <summary>
/// Diferente do agente Windows (Config.cs, guardado em LocalAppData do
/// usuário logado), o Bridge roda como serviço/systemd -- não tem sessão
/// de usuário garantida, e frequentemente roda como SYSTEM/root. Por
/// isso o config.json mora ao lado do próprio executável (mesma pasta),
/// escrito pelo instalador ou manualmente por quem faz a instalação.
/// </summary>
public class BridgeConfig
{
    public string ServerUrl { get; set; } = "";
    public string Token { get; set; } = "";

    /// <summary>Intervalo entre heartbeats, em segundos -- cada heartbeat já traz a lista de jobs de coleta pendentes.</summary>
    public int HeartbeatSegundos { get; set; } = 30;

    public bool EstaConfigurado => !string.IsNullOrWhiteSpace(ServerUrl) && !string.IsNullOrWhiteSpace(Token);

    private static string CaminhoArquivo => Path.Combine(AppContext.BaseDirectory, "config.json");

    public static BridgeConfig Carregar()
    {
        try
        {
            if (File.Exists(CaminhoArquivo))
            {
                var json = File.ReadAllText(CaminhoArquivo);
                return JsonSerializer.Deserialize<BridgeConfig>(json) ?? new BridgeConfig();
            }
        }
        catch
        {
            // config corrompido -- sobe com config vazio; o Worker loga e
            // fica parado esperando alguém corrigir o arquivo, em vez de
            // derrubar o serviço inteiro.
        }

        return new BridgeConfig();
    }
}
