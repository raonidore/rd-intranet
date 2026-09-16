using RdIntranetBridge;

var builder = Host.CreateApplicationBuilder(args);

// Host dual -- roda como Serviço do Windows OU unidade systemd, com o
// MESMO binário; qual dos dois se aplica é decidido em tempo de execução
// pelo próprio host genérico (nenhum efeito quando rodando solto via
// "dotnet run" pra testar). Sem isso, o processo não sobrevive a
// reboot/logoff em nenhum dos dois sistemas -- ver a nota em
// RdIntranetBridge.csproj.
builder.Services.AddWindowsService(options =>
{
    options.ServiceName = "RD Intranet Bridge";
});
builder.Services.AddSystemd();

builder.Services.AddSingleton<BridgeConfig>(_ => BridgeConfig.Carregar());
builder.Services.AddSingleton<ApiClient>();
builder.Services.AddSingleton<SnmpCollector>();
builder.Services.AddHostedService<Worker>();

var host = builder.Build();
host.Run();
