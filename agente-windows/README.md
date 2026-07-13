# RD Intranet - Agente (bandeja do Windows)

Versão em C#/WinForms do agente de inventário -- mesma função do script
`scripts/agente/rd-intranet-agent.ps1`, mas roda como um ícone fixo na
bandeja do Windows, com status visível e contadores de upload/download,
em vez de uma Tarefa Agendada silenciosa.

Os dois clientes falam com o **mesmo endpoint** do servidor
(`POST /api/ativos/checkin`, autenticado pelo header `X-RD-Agente-Chave`)
e mandam exatamente o mesmo formato de JSON -- nenhuma mudança no lado
PHP foi necessária. Use o `.ps1` para servidores/máquinas sem usuário
logado com frequência; use este app de bandeja para estações de
trabalho, onde faz sentido ter algo visível pro usuário/suporte.

## Como abrir no Visual Studio

Como este ambiente (onde o código foi escrito) é Linux, o projeto nunca
foi compilado nem testado aqui -- isso precisa acontecer no seu Windows.

1. Abra o Visual Studio 2022 (Community já serve).
2. **Arquivo > Abrir > Projeto/Solução** e selecione diretamente
   `agente-windows/RdIntranetAgente/RdIntranetAgente.csproj` (não precisa
   de um `.sln` -- o Visual Studio abre um `.csproj` sozinho).
3. Se pedir para instalar a carga de trabalho "Desenvolvimento para
   desktop com .NET", aceite (é o SDK do WinForms).
4. `F5` para rodar em modo debug. Na primeira execução ele vai abrir a
   tela de configuração pedindo o endereço do servidor e a chave de API
   (a mesma chave mostrada em **Ativos > Dashboard** no RD Intranet).

## Publicar um .exe pra distribuir

No Visual Studio: botão direito no projeto > **Publicar** > pasta local >
perfil com:

- Modo de implantação: **Autocontido** (self-contained) -- assim a
  máquina de destino não precisa ter o .NET instalado.
- Runtime de destino: **win-x64**.
- **Produzir arquivo único**: marcado.

Isso gera um único `RdIntranetAgente.exe` que já roda em qualquer
Windows 10/11/Server 2016+ sem instalar nada. O ícone (`assets/icone.ico`)
já vem embutido no `.exe` (propriedade `ApplicationIcon` do `.csproj`) --
aparece no Explorer, na barra de tarefas e no ícone da bandeja, sem
precisar de nenhum arquivo extra junto do `.exe`.

## Distribuição em massa (opcional)

Pra não precisar configurar servidor/chave manualmente em cada máquina,
coloque um `config.json` na mesma pasta do `.exe` antes de distribuir
(ex: via GPO, script de login, PDQ Deploy):

```json
{
  "ServerUrl": "https://rd.intranet",
  "ApiKey": "cole-aqui-a-chave-do-dashboard",
  "IntervaloMinutos": 15
}
```

O app usa esse arquivo como configuração inicial na primeira vez que
roda numa máquina (depois disso, a configuração "oficial" passa a viver
em `%LocalAppData%\RDIntranetAgent\config.json`, por usuário).

## O que ele faz sozinho

- Registra-se pra iniciar com o Windows (`HKCU\...\Run`, sem precisar
  de admin).
- A cada N minutos (configurável em Ativos > Dashboard no RD Intranet,
  padrão 15), coleta hardware/SO, uptime (ligado desde), componentes
  (processador, memória total/em uso, tipo de memória, placa-mãe,
  placa de vídeo, placa de som), módulos de memória (fabricante/
  modelo/frequência/série, um por pente), rede (MAC/IP por adaptador),
  volumes lógicos (uso por unidade + modelo/fabricante/série do disco
  físico associado, quando disponível), portas físicas (USB conectado
  + seriais), programas instalados (via registro, não via
  `Win32_Product`, com data de instalação e UninstallString quando
  disponíveis), atualizações do Windows instaladas (KBs) e alertas
  novos do Visualizador de Eventos (System/Application, Erro/Aviso), e
  envia pro servidor.
- Busca e executa comandos remotos pendentes (Desligar, Reiniciar,
  Desinstalar atualização, Desinstalar programa -- enviados pela ficha
  do ativo no RD Intranet). Desligar/Reiniciar sempre com um aviso
  nativo do Windows de 5 minutos antes de executar (`shutdown.exe /t`),
  que dá tempo do usuário salvar o trabalho ou cancelar localmente
  (`shutdown /a`). Desinstalação é melhor esforço: MSI sai silencioso
  (`msiexec /X{guid} /quiet`), instaladores não-MSI rodam como estão
  e podem abrir uma tela no computador remoto -- não há garantia de
  silêncio total nesse caso.
- Mostra no tooltip do ícone da bandeja: horário do último checkin e
  volume de dados enviado/recebido (última coleta + total acumulado).
- Menu de contexto (botão direito no ícone): "Coletar agora",
  "Configurações...", "Abrir pasta de logs", "Sair".

## Onde ficam os dados locais

`%LocalAppData%\RDIntranetAgent\`:
- `config.json` -- servidor + chave de API + intervalo.
- `state.json` -- horário/resultado do último checkin, contadores de
  bytes, bookmark de eventos.

Não existe log em arquivo de texto nesta versão (diferente do `.ps1`) --
o status fica no tooltip do ícone e nos balões de notificação quando
você clica em "Coletar agora" manualmente.

## Limitação conhecida

Este código não foi compilado nem testado em um Windows real ainda
(escrito e revisado manualmente, sem compilador C# disponível no
ambiente onde foi gerado). É bem provável que o primeiro `F5` no Visual
Studio revele algum erro de compilação pequeno -- normal, me manda o
erro que a gente corrige rápido.
