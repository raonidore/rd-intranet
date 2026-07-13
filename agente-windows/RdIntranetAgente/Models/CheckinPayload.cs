using System.Text.Json.Serialization;

namespace RdIntranetAgente.Models;

/// <summary>
/// Mesmo contrato JSON do script PowerShell -- o servidor (AtivoService::checkinAgente)
/// não sabe nem precisa saber qual dos dois clientes enviou o checkin.
/// </summary>
public class CheckinPayload
{
    [JsonPropertyName("machine_guid")]
    public string MachineGuid { get; set; } = "";

    [JsonPropertyName("tipo")]
    public string Tipo { get; set; } = "computador";

    [JsonPropertyName("nome")]
    public string Nome { get; set; } = "";

    [JsonPropertyName("marca")]
    public string? Marca { get; set; }

    [JsonPropertyName("modelo")]
    public string? Modelo { get; set; }

    [JsonPropertyName("numero_serie")]
    public string? NumeroSerie { get; set; }

    [JsonPropertyName("ip")]
    public string? Ip { get; set; }

    [JsonPropertyName("sistema_operacional")]
    public string? SistemaOperacional { get; set; }

    [JsonPropertyName("processador")]
    public string? Processador { get; set; }

    [JsonPropertyName("memoria_ram")]
    public string? MemoriaRam { get; set; }

    [JsonPropertyName("armazenamento")]
    public string? Armazenamento { get; set; }

    [JsonPropertyName("placa_mae")]
    public string? PlacaMae { get; set; }

    [JsonPropertyName("usuario_logado")]
    public string? UsuarioLogado { get; set; }

    [JsonPropertyName("funcao")]
    public string? Funcao { get; set; }

    [JsonPropertyName("virtualizado")]
    public string? Virtualizado { get; set; }

    [JsonPropertyName("programas")]
    public List<ProgramaItem> Programas { get; set; } = new();

    [JsonPropertyName("alertas")]
    public List<AlertaItem> Alertas { get; set; } = new();
}

public class ProgramaItem
{
    [JsonPropertyName("nome")]
    public string Nome { get; set; } = "";

    [JsonPropertyName("versao")]
    public string? Versao { get; set; }
}

public class AlertaItem
{
    [JsonPropertyName("nivel")]
    public string Nivel { get; set; } = "informacao";

    [JsonPropertyName("origem_evento")]
    public string? OrigemEvento { get; set; }

    [JsonPropertyName("mensagem")]
    public string Mensagem { get; set; } = "";

    [JsonPropertyName("ocorrido_em")]
    public string? OcorridoEm { get; set; }
}
