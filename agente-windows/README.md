# RD Intranet - Agente (bandeja do Windows)

Versão em C#/WinForms do agente de inventário -- mesma função do script
`scripts/agente/rd-intranet-agent.ps1`, mas roda como um ícone fixo na
bandeja do Windows, com status visível e contadores de upload/download,
em vez de uma Tarefa Agendada silenciosa.

Os dois clientes falam com o **mesmo endpoint** de checkin completo
(`POST /api/ativos/checkin`, autenticado pelo header `X-RD-Agente-Chave`)
e mandam exatamente o mesmo formato de JSON -- nenhuma mudança no lado
PHP foi necessária. Use o `.ps1` para servidores/máquinas sem usuário
logado com frequência; use este app de bandeja para estações de
trabalho, onde faz sentido ter algo visível pro usuário/suporte.

**Diferença importante**: só este app de bandeja manda o heartbeat de
"estou ligado" (ver seção própria abaixo) -- o `.ps1` roda como uma
Tarefa Agendada de tempos em tempos (um processo que nasce, coleta,
envia e morre), não como um processo residente, então não tem como
mandar um ping a cada 1-60 segundos sem virar outra coisa. Pra um ativo
aparecer com o status Ligado/Desligado em tempo real, ele precisa estar
rodando o `.exe`, não o `.ps1`.

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

Duas formas de publicar -- a diferença é só se a máquina de destino já
tem o **.NET 8 Desktop Runtime** instalado ou não (não confundir com o
".NET Runtime" genérico -- o Desktop Runtime é a versão com suporte a
WinForms, tem instalador próprio no site da Microsoft).

**Framework-dependent (recomendado se você já instala o Desktop Runtime
nas máquinas)** -- `.exe` bem menor (poucos MB, só o código da
aplicação):

```powershell
dotnet publish -c Release -r win-x64 --self-contained false -p:PublishSingleFile=true -o .\publicar
```

**Autocontido (self-contained)** -- `.exe` maior (~60-100MB, empacota o
runtime inteiro junto), mas roda em qualquer Windows 10/11/Server 2016+
sem precisar instalar nada antes:

```powershell
dotnet publish -c Release -r win-x64 --self-contained true -p:PublishSingleFile=true -o .\publicar
```

(O mesmo dá pra fazer pelo Visual Studo: botão direito no projeto >
**Publicar** > pasta local > escolher "Autocontido" ou "Dependente de
framework" no assistente, runtime **win-x64**, **Produzir arquivo
único** marcado.)

Os dois geram um único `RdIntranetAgente.exe`. O ícone
(`assets/icone.ico`) já vem embutido nele (propriedade
`ApplicationIcon` do `.csproj`) -- aparece no Explorer, na barra de
tarefas e no ícone da bandeja, sem precisar de nenhum arquivo extra
junto do `.exe`.

## Atualizando os agentes já instalados (sem reinstalar máquina por máquina)

O agente se autoatualiza. O fluxo:

1. O número de versão em `<Version>` no `RdIntranetAgente.csproj` já vem
   atualizado a cada mudança no código do agente (ex: `1.0.1` pra
   `1.0.2`) -- é essa versão que o agente compara com a do servidor, e
   que aparece na tela de abertura e em Configurações. Só falta você
   publicar o `.exe` (seção acima).
2. No RD Intranet, em **Ativos > Dashboard**, no card "Atualizar agente
   (.exe)", envie o `.exe` publicado e informe a mesma versão (formato
   `X.Y.Z`).
3. Cada agente já instalado confere essa versão a cada 12h (e também ao
   abrir) chamando `GET /api/ativos/agente/versao` com a chave de API.
   Se for mais nova que a própria, baixa o `.exe` novo, entrega a troca
   do arquivo pra um script auxiliar (um processo Windows não consegue
   sobrescrever o próprio `.exe` em execução) e reabre sozinho --
   nenhuma ação manual na máquina do usuário.

Se nenhum `.exe` for enviado ainda, essa checagem simplesmente não
encontra nada e não faz nada (sem erro visível pro usuário).

## Detecção de ligado/desligado em tempo real (heartbeat)

Separado do checkin completo (hardware/programas/alertas, que é pesado e
roda a cada N minutos), o agente manda um ping bem leve --
`POST /api/ativos/heartbeat` só com o `machine_guid` -- num intervalo
curto e configurável em segundos (**Ativos > Dashboard**, padrão 1s).
No servidor isso é só uma `UPDATE` numa coluna indexada por chave única,
por isso aguenta esse intervalo curto sem pesar.

- O `machine_guid` é calculado uma vez só, no início do processo
  (`CollectorService.ObterMachineGuid()`), e reaproveitado em todo
  heartbeat -- o ping em si nunca chama WMI.
- A resposta do heartbeat também carrega `forcar_checkin`: se um admin
  clicou em "Forçar coleta agora" na ficha do ativo, o próximo ping já
  volta com esse aviso e o agente roda o checkin completo na hora, sem
  esperar o ciclo normal de N minutos. Isso resolve o problema de ter
  que esperar até 2x o intervalo de checkin só pra confirmar que uma
  máquina está ligada ou pra atualizar os dados dela sob demanda.
- O badge "Ligado"/"Desligado" na lista e na ficha do ativo passa a
  usar o heartbeat (janela de 3x o intervalo configurado, mínimo 5s) em
  vez do checkin completo -- muito mais próximo de tempo real do que os
  até 30-60 minutos de antes.
- Best-effort: falha de rede num heartbeat não gera erro visível pro
  usuário nem derruba o agente, só tenta de novo no próximo tick (1s
  depois, por padrão).

## Impressão de etiquetas Zebra (TLP2844, GC420t, ZD420t)

O agente também funciona como "ponte" entre o navegador e uma impressora
Zebra ligada por USB nesta máquina -- mesmo princípio do Zebra Browser
Print oficial, só que nosso:

1. Em **Configurações...** (menu do ícone da bandeja), escolha a
   impressora Zebra na lista (aparecem as mesmas instaladas no Windows).
2. O agente sobe um servidor local, só em `127.0.0.1:8734`, nunca
   acessível pela rede.
3. Na ficha do ativo no RD Intranet, o botão "Imprimir etiqueta (Zebra)"
   busca o ZPL pronto (gerado conforme **Ativos > Configurações de
   Etiqueta**) e manda direto pro `127.0.0.1:8734/imprimir` -- o agente
   grava esse ZPL cru (RAW, sem passar pelo driver "desenhar página")
   direto na fila de impressão via a API do Windows (`winspool.drv`,
   `RawPrinterHelper.cs`), igual sistemas de ponto-de-venda fazem há
   décadas. Funciona nos 3 modelos porque todos falam ZPL.

Só aceita pedidos cujo `Origin` bate com o `ServerUrl` configurado --
outra aba/site não consegue mandar imprimir sem querer. Se a porta 8734
já estiver em uso (outra instância, outro programa), o agente
simplesmente não sobe esse listener e segue funcionando normal pro
resto (checkin/coleta).

**Não testado em hardware real** (sem Windows nem impressora Zebra
neste ambiente) -- o ZPL gerado pelo servidor foi validado
visualmente via [Labelary](http://labelary.com/) (renderizador ZPL
público), mas o envio via `winspool.drv`/`HttpListener` em si só vai
ser confirmado quando alguém testar numa máquina de verdade.

## Distribuição em massa (opcional)

Pra não precisar configurar servidor/chave manualmente em cada máquina,
coloque um `config.json` na mesma pasta do `.exe` antes de distribuir
(ex: via GPO, script de login, PDQ Deploy):

```json
{
  "ServerUrl": "https://rd.intranet",
  "ApiKey": "cole-aqui-a-chave-do-dashboard",
  "IntervaloMinutos": 15,
  "HeartbeatSegundos": 1
}
```

O app usa esse arquivo como configuração inicial na primeira vez que
roda numa máquina (depois disso, a configuração "oficial" passa a viver
em `%LocalAppData%\RDIntranetAgent\config.json`, por usuário).

## O que ele faz sozinho

- Mostra uma tela de abertura (~2s, `SplashForm.cs`) com o logo, uma
  barra de progresso e a versão em execução -- só pra deixar claro,
  visualmente, quando uma atualização entrou em ação de verdade (evita
  a dúvida "será que atualizou mesmo?"). A versão também aparece em
  Configurações (menu do ícone da bandeja) e vai junto em todo checkin
  completo (`versao_agente`) -- aparece na coluna "Versão do Agente" em
  **Ativos > Lista**, com aviso visual quando estiver diferente da
  versão cadastrada em Ativos > Dashboard.
- Registra-se pra iniciar com o Windows (`HKCU\...\Run`, sem precisar
  de admin).
- A cada N segundos (configurável, padrão 1), manda o heartbeat de
  "estou ligado" -- ver seção própria acima.
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
- Confere a cada 12h se há uma versão nova do próprio `.exe` publicada
  no RD Intranet e se autoatualiza sozinho quando encontra (ver seção
  acima).
- Mostra no tooltip do ícone da bandeja: horário do último checkin e
  volume de dados enviado/recebido (última coleta + total acumulado).
- Menu de contexto (botão direito no ícone): "Coletar agora",
  "Configurações...", "Abrir pasta de logs", "Sair".
- Se uma impressora Zebra estiver configurada, escuta local
  (`127.0.0.1:8734`) pra imprimir etiquetas sob demanda vindas do RD
  Intranet (ver seção própria abaixo).

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
