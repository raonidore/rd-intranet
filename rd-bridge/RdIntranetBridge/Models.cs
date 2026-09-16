using System.Text.Json.Serialization;

namespace RdIntranetBridge;

public class ColetaJob
{
    [JsonPropertyName("ativo_id")]
    public int AtivoId { get; set; }

    [JsonPropertyName("ip")]
    public string Ip { get; set; } = "";

    [JsonPropertyName("community")]
    public string Community { get; set; } = "";

    [JsonPropertyName("tipo_slug")]
    public string TipoSlug { get; set; } = "";
}

public class HeartbeatResposta
{
    [JsonPropertyName("success")]
    public bool Success { get; set; }

    [JsonPropertyName("jobs")]
    public List<ColetaJob> Jobs { get; set; } = new();

    [JsonPropertyName("message")]
    public string? Message { get; set; }
}

public class ColetaResultadoResposta
{
    [JsonPropertyName("success")]
    public bool Success { get; set; }

    [JsonPropertyName("message")]
    public string? Message { get; set; }
}
