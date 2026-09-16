using System.Reflection;

namespace RdIntranetBridge;

/// <summary>
/// Laço principal: heartbeat (traz a lista de ativos da unidade que
/// precisam de coleta SNMP) -> coleta local -> envia resultado -> espera
/// -> repete. Tudo em conexão de saída, nunca porta aberta -- mesmo
/// espírito do agente Windows, só que coletando de OUTROS equipamentos
/// da rede, não da própria máquina onde o Bridge roda.
/// </summary>
public class Worker : BackgroundService
{
    private readonly ILogger<Worker> _logger;
    private readonly BridgeConfig _config;
    private readonly ApiClient _api;
    private readonly SnmpCollector _snmp;

    public Worker(ILogger<Worker> logger, BridgeConfig config, ApiClient api, SnmpCollector snmp)
    {
        _logger = logger;
        _config = config;
        _api = api;
        _snmp = snmp;
    }

    private static string Versao()
    {
        var versao = Assembly.GetExecutingAssembly().GetName().Version;
        return versao == null ? "?" : $"{versao.Major}.{versao.Minor}.{versao.Build}";
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        if (!_config.EstaConfigurado)
        {
            _logger.LogError(
                "config.json ausente ou incompleto (ServerUrl/Token) -- crie {Caminho} antes de iniciar o serviço.",
                Path.Combine(AppContext.BaseDirectory, "config.json"));
            return;
        }

        _logger.LogInformation("RD.Bridge v{Versao} iniciado -- servidor: {Servidor}", Versao(), _config.ServerUrl);

        while (!stoppingToken.IsCancellationRequested)
        {
            try
            {
                await ExecutarCicloAsync();
            }
            catch (Exception ex)
            {
                // Um ciclo falhar (rede instável, servidor fora do ar por um
                // instante) não deve derrubar o serviço -- só loga e tenta
                // de novo no próximo ciclo.
                _logger.LogWarning(ex, "Falha num ciclo de coleta -- tentando de novo no próximo.");
            }

            await Task.Delay(TimeSpan.FromSeconds(Math.Max(5, _config.HeartbeatSegundos)), stoppingToken);
        }
    }

    private async Task ExecutarCicloAsync()
    {
        var resposta = await _api.HeartbeatAsync(Versao());
        if (resposta == null || !resposta.Success)
        {
            _logger.LogWarning("Heartbeat falhou (servidor inalcançável ou token inválido).");
            return;
        }

        if (resposta.Jobs.Count == 0)
        {
            _logger.LogDebug("Heartbeat OK -- nenhum job de coleta pendente.");
            return;
        }

        _logger.LogInformation("{Total} job(s) de coleta recebido(s).", resposta.Jobs.Count);

        foreach (var job in resposta.Jobs)
        {
            if (string.IsNullOrWhiteSpace(job.Ip))
            {
                continue;
            }

            var dados = _snmp.Coletar(job.Ip, job.Community, job.TipoSlug);
            var enviado = await _api.EnviarResultadoColetaAsync(job.AtivoId, dados);

            _logger.LogInformation(
                "Ativo #{AtivoId} ({Ip}): {Campos} campo(s) coletado(s), envio {Status}.",
                job.AtivoId, job.Ip, dados.Count, enviado ? "OK" : "falhou");
        }
    }
}
