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

**Chave de API tem histórico, não é um valor único**: "Gerar nova
chave" em Ativos > Dashboard NÃO invalida a(s) chave(s) anterior(es) --
elas continuam funcionando até serem desativadas explicitamente na
tabela de histórico. Com "Enviar automaticamente pros agentes já
conectados" marcado (padrão), a chave nova é entregue a esses agentes
sozinha, via o campo `chave_api_atual` na resposta do heartbeat/checkin
-- o app de bandeja aplica e salva sozinho (`TrayApplicationContext.
AplicarNovaChaveApiSeNecessario`); o `.ps1` **não** faz isso (não tem
onde persistir a chave nova além de reescrever o próprio arquivo do
script, o que não é feito automaticamente) -- ativos rodando o `.ps1`
continuam na chave com que foram instalados até serem baixados de novo.
Só **desativar** uma chave (não gerar uma nova) é destrutivo de verdade
-- derruba na hora qualquer agente que ainda esteja usando
especificamente aquela chave.

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
   `X.Y.Z`) -- ou, se você roda o sistema em mais de um servidor, publique
   pelo git em vez de fazer upload em cada um (ver seção abaixo).
3. Cada agente já instalado confere essa versão a cada 12h (e também ao
   abrir) chamando `GET /api/ativos/agente/versao` com a chave de API.
   Se for mais nova que a própria, baixa o `.exe` novo, entrega a troca
   do arquivo pra um script auxiliar (um processo Windows não consegue
   sobrescrever o próprio `.exe` em execução) e reabre sozinho --
   nenhuma ação manual na máquina do usuário.

Se nenhum `.exe` for enviado ainda, essa checagem simplesmente não
encontra nada e não faz nada (sem erro visível pro usuário).

### Publicar via git (recomendado se você roda em vários servidores)

Se você mantém o RD Intranet em mais de um servidor (cada instalação
com seu próprio banco/config), fazer upload manual do `.exe` em cada
um é repetitivo. Alternativa: publicar o `.exe` no próprio repositório
git e, em cada servidor, clicar em **"Baixar do repositório"** (mesmo
card "Atualizar agente (.exe)") em vez de enviar o arquivo -- o botão
busca a versão publicada direto do git.

**Passo a passo, a cada novo build do agente:**

1. Compile/publique o `.exe` normalmente (seção "Publicar um .exe pra
   distribuir" acima).
2. Copie o arquivo gerado para `agente-windows/dist/RdIntranetAgente.exe`
   (sobrescrevendo o anterior) e crie/atualize
   `agente-windows/dist/VERSION.txt` com a mesma versão do `<Version>`
   no `.csproj` (ex: `1.0.13`, sem mais nada no arquivo).
3. Faça commit e `git push` desses dois arquivos pra branch `main`.
4. Em cada servidor, na tela **Ativos > Dashboard**, clique em
   **"Baixar do repositório"** -- ele busca o `.exe` e a versão direto
   do git (sem precisar selecionar arquivo nem digitar versão) e já
   deixa disponível pros agentes instalados se autoatualizarem no
   próximo check-in, do mesmo jeito do fluxo de upload manual.

**Configuração única por servidor** (só na primeira vez que este botão
for usado, não a cada atualização de agente): o botão depende de um
script auxiliar (`scripts/system/agente_baixar_git.sh`) sincronizado
pra `/opt/rdtecnologia/scripts/` -- depois que esse arquivo chegar no
servidor via `git pull`, rode uma vez:

```bash
sudo /var/www/rd.intranet/scripts/sync-system-scripts.sh
```

Isso só precisa ser feito de novo se algum script em `scripts/system/`
mudar no futuro -- não a cada novo `.exe` publicado.

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

## Explorador de arquivos e gerenciador de processos remoto

Na ficha do ativo (**Ativos > Lista > Ver**), aba "Volumes lógicos" tem
um botão "Explorar arquivos" por unidade, e a aba "Processos" lista o
que está rodando na hora -- os dois pedem uma leitura ao vivo pelo mesmo
canal do heartbeat (`SolicitacaoItem` na resposta, `ExploradorService`
faz a leitura local, `SolicitacaoClient` devolve o resultado por
`POST /api/ativos/solicitacoes/resultado` em segundos). Dá pra executar
um arquivo (o Windows decide como abrir, igual duplo clique) ou encerrar
um processo direto da tela -- os dois reaproveitam o sistema de comandos
remotos já existente (mesma fila/confirmação de Desligar/Reiniciar,
entrega forçada via `solicitarCheckin()` assim que o comando é
enviado).

Também dá pra **renomear** arquivo/pasta, **baixar** um arquivo da
máquina remota pro seu navegador e **enviar** um arquivo do seu
navegador pra pasta que está sendo explorada. Renomear/enviar
reaproveitam o sistema de comandos (`ativos_comandos`, nova coluna
`arquivo_anexo` guarda o upload do admin até o agente buscar);
baixar usa o mesmo canal de solicitação (`ativos_solicitacoes`, nova
coluna `arquivo_resultado`) -- `TransferenciaClient.cs` no agente cuida
das duas direções (`EnviarArquivoAsync`/`BaixarAnexoComandoAsync`).
Arquivo temporário no servidor é apagado assim que servido (upload pro
admin ou download pro agente) -- não fica cópia parada.

Também dá pra **executar comandos CMD ou PowerShell** direto na ficha
do ativo (card "Executar comando", abaixo do histórico de comandos), com
opção de **elevação** (como administrador). Sem UAC interativo -- a
elevação usa um truque padrão de Agendador de Tarefas (cria uma tarefa
temporária com "Executar com privilégios mais altos", dispara na hora,
apaga em seguida). **Desde a v1.0.12, o próprio agente sempre roda
elevado**: pede UAC uma vez no primeiro início (`Program.cs`, checa
`WindowsPrincipal.IsInRole(Administrator)` e relança com o verbo `runas`
se precisar) e depois se registra pra iniciar já elevado nos próximos
logins via Agendador de Tarefas, em vez do antigo `HKCU\...\Run` (que
sempre iniciava sem elevação, nível Médio, mesmo numa conta
administradora -- foi exatamente isso que causava "Access is denied"
ao tentar elevar comandos em versões anteriores). **Desde a v1.0.13**,
essa tarefa é criada via definição XML mirando o grupo local "Users"
(SID `S-1-5-32-545`), não uma conta específica -- confirmado ao vivo:
criar a tarefa com os parâmetros simples do `schtasks /create /sc
onlogon` (sem `/xml`) vincula ela só à conta que rodou o primeiro
início elevado, então só aquela conta específica tinha o agente
iniciando sozinho; outras contas (inclusive outras contas do Entra)
logando na mesma máquina não disparavam nada. Com o XML mirando o
grupo, qualquer conta que conseguir logar interativamente na máquina
(local ou do Entra) já dispara o agente elevado, sem precisar de
nenhum ajuste por conta. Com isso, o truque de Agendador de Tarefas
funciona sozinho na maioria das máquinas, sem precisar cadastrar mais
nada. Saída (stdout/stderr/
código) volta pelo mesmo canal de solicitação e fica registrada no
histórico (últimos 5 comandos, com saída completa, expansível clicando
na linha), junto com quem pediu (`solicitado_por`).

Ainda existe a opção de cadastrar uma **credencial de elevação** por
máquina direto na ficha do ativo (card "Executar comando",
`ativos.elevacao_usuario` / `elevacao_senha_cifrada`) -- **por máquina,
não uma só pra frota inteira**: cada Windows normalmente tem sua própria
conta de administrador local, com senha diferente (diferente da chave
de API do agente, que essa sim é única pra frota). Ainda útil quando: o
agente instalado é de uma versão anterior à v1.0.12 (sem elevação
própria), o usuário recusou o prompt de UAC no primeiro início, ou você
quer que o comando rode especificamente como OUTRA conta (não a que já
roda o agente). Usuário local ou de domínio (ex.: `DOMINIO\admin` ou
`.\admin`) + senha. A senha fica cifrada no banco (`CryptoService`,
AES-256-GCM, mesma chave já usada pra senha de conexão de banco de
clientes) e só é enviada ao agente, decifrada, no momento em que uma
solicitação com elevação marcada está pendente pra aquele ativo
específico -- nunca em todo heartbeat, nunca fica gravada em
log/auditoria (só o nome de usuário é auditado), nunca vaza pra outra
máquina. Quando essa credencial existe, a tarefa agendada roda com
`/ru "usuario" /rp "senha"` em vez de depender da conta do agente já
ser admin. Sem credencial cadastrada, cai de volta no comportamento
padrão (`/rl highest`, depende da conta do agente já estar elevada).

**Pegadinha confirmada ao vivo, numa VM de teste real**: se a credencial
cadastrada for de uma conta administradora local **com nome próprio**
(ex. `TI`, não a conta `Administrator` embutida do Windows), o
`schtasks /ru` pode recusar com `ERROR: Access is denied` mesmo com
usuário/senha certos -- é uma política do Windows chamada "token
filtrado pra contas locais" (UAC remote restrictions), que existe
mesmo em contas fora de domínio. A conta `Administrator` embutida é
isenta disso por padrão; qualquer outra conta admin local não é. Pra
liberar, roda uma vez na máquina afetada (não precisa reiniciar):
```
reg add "HKLM\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\System" /v LocalAccountTokenFilterPolicy /t REG_DWORD /d 1 /f
```

**Poder de verdade, use com critério**: quem tiver acesso ao módulo
Ativos com permissão de enviar comando (`ativos_novo`) consegue rodar
qualquer arquivo, comando CMD/PowerShell (com ou sem elevação),
encerrar qualquer processo ou mover arquivos em qualquer máquina com o
agente instalado -- é essencialmente controle remoto total. Não tem
confirmação em duas etapas nem aviso na tela do usuário (diferente de
Desligar/Reiniciar, que tem um contador de 5 minutos visível). Trate o
acesso a esse módulo com o mesmo cuidado que uma conta de administrador
de domínio.

Exclusivo do agente de bandeja (.exe) -- depende do heartbeat, que o
`.ps1` não tem (ver seção acima). A tela avisa isso quando o ativo
estiver rodando o script em vez do `.exe`.

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
  "estou ligado" -- ver seção própria acima. O mesmo canal entrega
  pedidos de leitura ao vivo da ficha do ativo: explorador de arquivos
  (aba Volumes lógicos > "Explorar arquivos" por unidade,
  `ExploradorService.ListarArquivos`) e gerenciador de processos (aba
  Processos, `ExploradorService.ListarProcessos`) -- listagem roda sob
  demanda, resultado volta em segundos por `SolicitacaoClient`. Exclusivo
  do agente de bandeja -- o `.ps1` não manda heartbeat, então não
  responde a esses pedidos (a tela avisa isso pro admin). Executar um
  arquivo ou encerrar um processo reaproveita o sistema de comandos
  remotos já existente (mesma entrega/confirmação de Desligar/Reiniciar).
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
- Confere se há uma versão nova do próprio `.exe` publicada no RD
  Intranet e se autoatualiza sozinho quando encontra -- a cada 12h em
  gatilhos automáticos (ciclo periódico, "Forçar coleta agora" vindo do
  portal), mas na hora sempre que alguém clica em "Coletar agora" ou dá
  duplo clique no ícone (não espera as 12h -- útil pra confirmar uma
  atualização sem ter que esperar ou reinstalar manualmente). Sem
  confirmação em duas etapas -- não pergunta permissão antes de trocar
  o próprio executável, de propósito: quem gerencia isso é o admin pelo
  portal, não o usuário da máquina (mesmo critério de Desligar/Reiniciar
  remoto, só com aviso, não com pedido de permissão).
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
