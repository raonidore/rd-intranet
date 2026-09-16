# RD.Bridge

Coletor pra unidades remotas atrás de NAT (ex: uma fábrica, atrás do roteador dela, sem rota nenhuma até o RD.Intranet na nuvem) -- roda numa Raspberry Pi, VM Linux ou Windows já existente na rede local do cliente, fala com a nuvem por conexão de saída (nunca porta aberta, nunca configuração no roteador do cliente) e coleta, na própria rede local dele, os ativos que o servidor não alcançaria sozinho.

Decisão de arquitetura completa (por que este componente existe, as opções descartadas, os dois modos de conexão) documentada num artifact publicado -- ver a memória `coletor_multisite_arquitetura.md` deste projeto pro link.

## v1 -- o que já funciona (testado ao vivo)

- **Checkin de saída** (`ApiClient.cs`): `POST /api/rd-bridge/heartbeat`, autenticado por `X-RD-Bridge-Chave` -- mesmo modelo do agente Windows (`X-RD-Agente-Chave`). Testado de ponta a ponta contra um servidor real (`srv-sidore`): token criado pelo portal, heartbeat recebido, `ip_ultimo_checkin`/`ultimo_checkin_em`/`versao` gravados no banco.
- **Coleta SNMP v2c** (`SnmpCollector.cs`, via `Lextm.SharpSnmpLib` -- puro gerenciado, funciona igual no Windows e no Linux): porta exata dos mesmos OIDs de `App\Services\SnmpService.php` (sysDescr, sysUpTime, contadores de página/toner de impressora). Testado o pipeline inteiro (heartbeat traz o job → coleta → `POST /api/rd-bridge/coleta/resultado`) contra um ativo real cadastrado; a leitura SNMP em si só foi validada contra um IP inalcançável de propósito (timeout tratado sem derrubar o serviço) -- **ainda não testada contra um switch/roteador SNMP de verdade**.
- **Host dual** (`Program.cs`): mesmo binário roda como Serviço do Windows ou unidade systemd (`Microsoft.Extensions.Hosting.WindowsServices`/`.Systemd`) -- ver seção de instalação abaixo. Só compilado/testado via `dotnet run` até agora, não instalado como serviço de verdade em nenhum dos dois sistemas ainda.

## O que falta (próxima versão)

- **Coleta de DVR/NVR Intelbras**: de propósito **não** portada ainda. `App\Services\IntelbrasDvrService.php` tem mais de mil linhas de comportamento de API descoberto na prática, testando contra firmware real (ver memória `intelbras_dvr_api_quirks.md`) -- portar isso às cegas pro C#, sem hardware pra validar contra, arriscaria produzir código que parece certo mas falha silencioso no equipamento real. Fica como próximo passo, testado contra um DVR/NVR de verdade antes de ir pra produção.
- **Modo VPN**: o script de NAT/masquerade (`scripts/configurar_vpn_masquerade.sh`) existe e foi revisado, mas não testado ao vivo contra um túnel WireGuard de verdade (nenhuma Raspberry disponível neste ambiente). A extensão do lado do servidor (`VpnWireguardService`, sub-rede por peer) foi testada isoladamente (geração de `AllowedIPs` com a rota extra), mas não o fluxo ponta-a-ponta com um peer real do outro lado.
- Instalação como serviço/systemd de verdade (`sc.exe create`, unit file) -- hoje só documentado em intenção no `Program.cs`, não empacotado num script de instalação ainda.

## Rodando localmente pra testar

```bash
cd RdIntranetBridge
dotnet build
```

Crie `bin/Debug/net8.0/config.json` (nunca commitado -- está no `.gitignore`):

```json
{
  "ServerUrl": "https://seu-servidor/rd.intranet",
  "Token": "token gerado em Administração > Integrações > RD.Bridge",
  "HeartbeatSegundos": 30
}
```

```bash
cd bin/Debug/net8.0
dotnet RdIntranetBridge.dll
```

Roda em console (Ctrl+C pra parar) -- sem instalar como serviço, útil só pra testar contra um servidor real antes de empacotar.

## Publicar

Mesmo comando do agente Windows, trocando o runtime conforme o destino:

```bash
# Linux (Raspberry Pi / VM)
dotnet publish -c Release -r linux-arm64 --self-contained true -p:PublishSingleFile=true -o publicar-linux
# ou linux-x64 pra VM x86

# Windows
dotnet publish -c Release -r win-x64 --self-contained false -p:PublishSingleFile=true -o publicar-windows
```
