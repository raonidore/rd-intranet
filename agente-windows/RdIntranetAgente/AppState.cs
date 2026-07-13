using System.IO;
using System.Text.Json;

namespace RdIntranetAgente;

/// <summary>
/// Estado persistido entre execuções: horário/resultado do último checkin,
/// contadores acumulados de upload/download (mostrados na bandeja), e o
/// bookmark de eventos (só manda pro servidor os alertas novos desde a
/// última coleta bem-sucedida).
/// </summary>
public class AppState
{
    public DateTime? UltimoCheckinEm { get; set; }
    public bool UltimoCheckinSucesso { get; set; }
    public string UltimaMensagem { get; set; } = "";
    public long TotalBytesEnviados { get; set; }
    public long TotalBytesRecebidos { get; set; }
    public long UltimoEnvioBytes { get; set; }
    public long UltimoRecebimentoBytes { get; set; }
    public DateTime? MarcaEventos { get; set; }
    public DateTime? UltimaVerificacaoAtualizacao { get; set; }

    private static string PastaDados => Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
        "RDIntranetAgent");

    private static string CaminhoArquivo => Path.Combine(PastaDados, "state.json");

    public static AppState Carregar()
    {
        try
        {
            if (File.Exists(CaminhoArquivo))
            {
                var json = File.ReadAllText(CaminhoArquivo);
                return JsonSerializer.Deserialize<AppState>(json) ?? new AppState();
            }
        }
        catch
        {
            // estado corrompido -- comeca do zero
        }

        return new AppState();
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
