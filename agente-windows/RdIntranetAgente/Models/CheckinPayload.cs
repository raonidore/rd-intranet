using System.Collections.Generic;
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

    [JsonPropertyName("memoria_usada")]
    public string? MemoriaUsada { get; set; }

    [JsonPropertyName("tipo_memoria")]
    public string? TipoMemoria { get; set; }

    [JsonPropertyName("armazenamento")]
    public string? Armazenamento { get; set; }

    [JsonPropertyName("placa_mae")]
    public string? PlacaMae { get; set; }

    [JsonPropertyName("placa_video")]
    public string? PlacaVideo { get; set; }

    [JsonPropertyName("placa_som")]
    public string? PlacaSom { get; set; }

    [JsonPropertyName("usuario_logado")]
    public string? UsuarioLogado { get; set; }

    [JsonPropertyName("funcao")]
    public string? Funcao { get; set; }

    [JsonPropertyName("virtualizado")]
    public string? Virtualizado { get; set; }

    [JsonPropertyName("ligado_desde")]
    public string? LigadoDesde { get; set; }

    [JsonPropertyName("redes")]
    public List<RedeItem> Redes { get; set; } = new();

    [JsonPropertyName("volumes")]
    public List<VolumeItem> Volumes { get; set; } = new();

    [JsonPropertyName("portas")]
    public List<PortaItem> Portas { get; set; } = new();

    [JsonPropertyName("memoria_modulos")]
    public List<MemoriaItem> MemoriaModulos { get; set; } = new();

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

    [JsonPropertyName("data_instalacao")]
    public string? DataInstalacao { get; set; }
}

public class RedeItem
{
    [JsonPropertyName("nome_adaptador")]
    public string? NomeAdaptador { get; set; }

    [JsonPropertyName("mac")]
    public string? Mac { get; set; }

    [JsonPropertyName("ip")]
    public string? Ip { get; set; }
}

public class VolumeItem
{
    [JsonPropertyName("unidade")]
    public string Unidade { get; set; } = "";

    [JsonPropertyName("total_gb")]
    public double TotalGb { get; set; }

    [JsonPropertyName("usado_gb")]
    public double UsadoGb { get; set; }

    [JsonPropertyName("modelo_disco")]
    public string? ModeloDisco { get; set; }

    [JsonPropertyName("fabricante_disco")]
    public string? FabricanteDisco { get; set; }

    [JsonPropertyName("serial_disco")]
    public string? SerialDisco { get; set; }
}

public class MemoriaItem
{
    [JsonPropertyName("fabricante")]
    public string? Fabricante { get; set; }

    [JsonPropertyName("modelo")]
    public string? Modelo { get; set; }

    [JsonPropertyName("capacidade_gb")]
    public double? CapacidadeGb { get; set; }

    [JsonPropertyName("frequencia_mhz")]
    public int? FrequenciaMhz { get; set; }

    [JsonPropertyName("numero_serie")]
    public string? NumeroSerie { get; set; }
}

public class PortaItem
{
    [JsonPropertyName("tipo")]
    public string Tipo { get; set; } = "usb";

    [JsonPropertyName("descricao")]
    public string Descricao { get; set; } = "";
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

/// <summary>Comando remoto (desligar/reiniciar) que veio na resposta do checkin.</summary>
public class ComandoItem
{
    [JsonPropertyName("id")]
    public int Id { get; set; }

    [JsonPropertyName("comando")]
    public string Comando { get; set; } = "";
}

public class RespostaCheckin
{
    [JsonPropertyName("success")]
    public bool Success { get; set; }

    [JsonPropertyName("message")]
    public string? Message { get; set; }

    [JsonPropertyName("comandos")]
    public List<ComandoItem> Comandos { get; set; } = new();
}
