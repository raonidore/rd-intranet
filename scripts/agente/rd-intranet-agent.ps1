<#
    RD Intranet - Agente de Inventario (Windows)
    ==============================================

    O que faz:
      Coleta hardware, sistema operacional, uptime, programas instalados
      e alertas recentes do Visualizador de Eventos, e envia tudo por
      HTTPS para o RD Intranet (modulo Ativos de TI). Tambem busca e
      executa comandos remotos pendentes (desligar/reiniciar, enviados
      pela tela do ativo no RD Intranet) -- sempre com um aviso do
      Windows de 5 minutos antes de executar, que da tempo do usuario
      salvar o trabalho ou cancelar localmente (shutdown /a). Nao
      instala nada alem de si mesmo -- so usa modulos nativos do
      Windows (CIM/WMI, registro, Get-WinEvent, Task Scheduler).

    Como instalar:
      1. Baixe este arquivo pela tela Ativos > Dashboard do RD Intranet
         (ja vem com o endereco do servidor e a chave de API preenchidos).
      2. Clique com o botao direito nele e escolha "Executar com o
         PowerShell" -- OU abra um PowerShell como Administrador e rode:

           .\rd-intranet-agent.ps1

      Isso e tudo. Na primeira execucao, se estiver rodando como
      Administrador, o proprio script se copia pra
      C:\ProgramData\RDIntranetAgent\ e se registra como Tarefa
      Agendada rodando a cada 15 minutos (usuario SYSTEM). As execucoes
      seguintes (via agendamento) so fazem a coleta, sem reinstalar nada.

      Se a chave de API for regenerada no servidor, baixe o script de
      novo e rode de novo do mesmo jeito -- ele substitui a instalacao
      anterior (script + tarefa) pela nova.

      O log de cada execucao fica em C:\ProgramData\RDIntranetAgent\agente.log --
      confira ali se o ativo nao aparecer no RD Intranet.
#>

$ServerUrl = '__SERVER_URL__'
$ApiKey    = '__API_KEY__'

$NomeTarefa    = 'RD Intranet Agente'
$PastaAgente   = 'C:\ProgramData\RDIntranetAgent'
$ScriptDestino = Join-Path $PastaAgente 'rd-intranet-agent.ps1'
$ArquivoLog    = Join-Path $PastaAgente 'agente.log'
$ArquivoMarca  = Join-Path $PastaAgente 'ultimo_evento.txt'

function Escrever-Log([string]$Mensagem) {
    if (!(Test-Path $PastaAgente)) {
        New-Item -ItemType Directory -Path $PastaAgente -Force | Out-Null
    }
    $linha = (Get-Date -Format 'yyyy-MM-dd HH:mm:ss') + ' - ' + $Mensagem
    Add-Content -Path $ArquivoLog -Value $linha
}

function Rodando-Como-Administrador {
    $identidade = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($identidade)
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

# --------------------------------------------------------------------
# Auto-instalacao: só roda quando o script NAO está sendo executado a
# partir do local já instalado -- ou seja, roda na primeira vez (baixado
# manualmente) e a cada nova reinstalação (chave regenerada), mas nunca
# nas execuções normais disparadas pela própria Tarefa Agendada.
$jaInstalado = $PSCommandPath -and ($PSCommandPath -eq $ScriptDestino)

if (-not $jaInstalado) {
    if (-not (Rodando-Como-Administrador)) {
        Write-Host "Este script precisa ser executado como Administrador na primeira vez (para se instalar e criar a tarefa agendada)." -ForegroundColor Red
        Write-Host "Clique com o botao direito no arquivo e escolha 'Executar com o PowerShell', ou abra um PowerShell como Administrador e rode o script de novo." -ForegroundColor Yellow
        exit 1
    }

    try {
        if (!(Test-Path $PastaAgente)) {
            New-Item -ItemType Directory -Path $PastaAgente -Force | Out-Null
        }

        Copy-Item -Path $PSCommandPath -Destination $ScriptDestino -Force

        $acao = New-ScheduledTaskAction -Execute 'powershell.exe' `
            -Argument "-ExecutionPolicy Bypass -NoProfile -WindowStyle Hidden -File `"$ScriptDestino`""

        $gatilho = New-ScheduledTaskTrigger -Once (Get-Date) `
            -RepetitionInterval (New-TimeSpan -Minutes 15) `
            -RepetitionDuration ([TimeSpan]::MaxValue)

        $principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest

        $configuracoes = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable

        Register-ScheduledTask -TaskName $NomeTarefa -Action $acao -Trigger $gatilho `
            -Principal $principal -Settings $configuracoes -Force | Out-Null

        Write-Host "Instalado com sucesso em $ScriptDestino" -ForegroundColor Green
        Write-Host "Tarefa agendada '$NomeTarefa' criada -- roda a cada 15 minutos." -ForegroundColor Green
        Write-Host "Fazendo a primeira coleta agora..." -ForegroundColor Cyan

        Escrever-Log "Instalação concluída (script copiado + tarefa agendada registrada)."
    } catch {
        Write-Host "Falha ao instalar: $($_.Exception.Message)" -ForegroundColor Red
        Escrever-Log "ERRO na instalação: $($_.Exception.Message)"
        exit 1
    }
}

# O RD Intranet usa certificado autoassinado por padrao -- sem isso o
# Invoke-RestMethod recusa a conexao com erro de certificado nao
# confiavel. -SkipCertificateCheck so existe no PowerShell 7+, entao
# fazemos o bypass manualmente pra funcionar no Windows PowerShell 5.1
# (o que vem de fabrica no Windows 10/11/Server).
try {
    if (-not ([System.Management.Automation.PSTypeName]'RdIntranetCertBypass').Type) {
        Add-Type -TypeDefinition @'
using System.Net;
using System.Net.Security;
using System.Security.Cryptography.X509Certificates;
public static class RdIntranetCertBypass {
    public static bool Validar(object sender, X509Certificate cert, X509Chain chain, SslPolicyErrors erros) {
        return true;
    }
}
'@
    }
    [System.Net.ServicePointManager]::ServerCertificateValidationCallback = [RdIntranetCertBypass]::Validar
    [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12
} catch {
    Escrever-Log "Aviso: nao foi possivel configurar o bypass de certificado ($($_.Exception.Message))"
}

try {
    # --------------------------------------------------------------
    # Identificacao estavel da maquina -- preferimos o numero de
    # serie da BIOS (sobrevive a reinstalacao do Windows); se vier
    # vazio ou for um valor generico de fabricante/VM sem serial real,
    # caimos pro MachineGuid do registro (estavel por instalacao do
    # Windows, mas muda se reinstalar o SO).
    $serialBios = (Get-CimInstance Win32_BIOS -ErrorAction SilentlyContinue).SerialNumber
    $serialInvalido = @('', 'To be filled by O.E.M.', 'None', 'System Serial Number', '0', 'Default string')

    if ($serialBios -and ($serialInvalido -notcontains $serialBios.Trim())) {
        $machineGuid = 'BIOS-' + $serialBios.Trim()
    } else {
        $machineGuid = 'REG-' + (Get-ItemProperty 'HKLM:\SOFTWARE\Microsoft\Cryptography' -ErrorAction Stop).MachineGuid
    }

    # --------------------------------------------------------------
    # Hardware / sistema operacional
    $sistema = Get-CimInstance Win32_OperatingSystem
    $computador = Get-CimInstance Win32_ComputerSystem
    $processador = Get-CimInstance Win32_Processor | Select-Object -First 1
    $placaMae = Get-CimInstance Win32_BaseBoard

    # ProductType: 1 = estacao de trabalho, 2 = controlador de dominio, 3 = servidor
    $tipo = if ($sistema.ProductType -eq 1) { 'computador' } else { 'servidor' }

    $memoriaGb = [math]::Round($computador.TotalPhysicalMemory / 1GB, 1)

    $discos = Get-CimInstance Win32_DiskDrive | ForEach-Object {
        '{0} ({1} GB)' -f $_.Model, [math]::Round($_.Size / 1GB, 0)
    }
    $armazenamento = $discos -join ', '

    $ip = (Get-CimInstance Win32_NetworkAdapterConfiguration -Filter 'IPEnabled = True' |
        Select-Object -First 1 -ExpandProperty IPAddress) | Select-Object -First 1

    $payload = @{
        machine_guid   = $machineGuid
        tipo           = $tipo
        nome           = $env:COMPUTERNAME
        marca          = $computador.Manufacturer
        modelo         = $computador.Model
        numero_serie   = $serialBios
        ip             = $ip
        sistema_operacional = $sistema.Caption
        processador    = $processador.Name
        memoria_ram    = "$memoriaGb GB"
        armazenamento  = $armazenamento
        placa_mae      = ('{0} {1}' -f $placaMae.Manufacturer, $placaMae.Product).Trim()
        usuario_logado = $computador.UserName
        funcao         = if ($tipo -eq 'servidor') { $sistema.Caption } else { $null }
        virtualizado   = if ($computador.Model -match 'Virtual|VMware|KVM|VirtualBox') { 'Sim' } else { 'Não' }
        ligado_desde   = $sistema.LastBootUpTime.ToString('yyyy-MM-dd HH:mm:ss')
        programas      = @()
        alertas        = @()
    }

    # --------------------------------------------------------------
    # Programas instalados -- le direto do registro (Uninstall), NAO
    # usa Win32_Product (alem de lento, o Win32_Product e conhecido
    # por forcar uma reconfiguracao/reinstalacao do MSI so de ser
    # consultado).
    $chavesUninstall = @(
        'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\*',
        'HKLM:\SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall\*',
        'HKCU:\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\*'
    )

    $programas = foreach ($chave in $chavesUninstall) {
        Get-ItemProperty $chave -ErrorAction SilentlyContinue |
            Where-Object { $_.DisplayName -and -not $_.SystemComponent } |
            Select-Object -Property @{n='nome'; e={$_.DisplayName}}, @{n='versao'; e={$_.DisplayVersion}}
    }

    $payload.programas = @($programas | Sort-Object nome -Unique)

    # --------------------------------------------------------------
    # Alertas -- so os eventos NOVOS desde o ultimo checkin (guarda um
    # bookmark local, evita mandar o mesmo evento repetido toda vez e
    # evita ter que deduplicar do lado do servidor).
    $desde = (Get-Date).AddHours(-24)
    if (Test-Path $ArquivoMarca) {
        try {
            $desde = Get-Date (Get-Content $ArquivoMarca -Raw).Trim()
        } catch {
            Escrever-Log "Aviso: bookmark de eventos ilegível, usando janela padrão de 24h ($($_.Exception.Message))"
        }
    }

    $eventos = @()
    try {
        $eventos = Get-WinEvent -FilterHashtable @{
            LogName   = 'System', 'Application'
            Level     = 1, 2, 3
            StartTime = $desde
        } -MaxEvents 200 -ErrorAction SilentlyContinue
    } catch {
        # Get-WinEvent lanca excecao quando nao ha nenhum evento no
        # periodo -- nao e uma falha real, so significa "nada novo".
    }

    $payload.alertas = @($eventos | ForEach-Object {
        $nivel = switch ($_.Level) {
            1 { 'erro' }; 2 { 'erro' }; 3 { 'aviso' }; default { 'informacao' }
        }
        @{
            nivel         = $nivel
            origem_evento = $_.ProviderName
            mensagem      = ($_.Message -split "`n")[0]
            ocorrido_em   = $_.TimeCreated.ToString('yyyy-MM-dd HH:mm:ss')
        }
    })

    Set-Content -Path $ArquivoMarca -Value (Get-Date -Format 'yyyy-MM-dd HH:mm:ss')

    # --------------------------------------------------------------
    # Envio
    $json = $payload | ConvertTo-Json -Depth 6 -Compress

    $resposta = Invoke-RestMethod -Uri "$ServerUrl/api/ativos/checkin" `
        -Method Post `
        -Headers @{ 'X-RD-Agente-Chave' = $ApiKey } `
        -ContentType 'application/json; charset=utf-8' `
        -Body ([System.Text.Encoding]::UTF8.GetBytes($json))

    Escrever-Log "OK: checkin enviado (guid=$machineGuid, tipo=$tipo, $($payload.programas.Count) programas, $($payload.alertas.Count) alertas). Resposta: $($resposta.message)"

    if (-not $jaInstalado) {
        Write-Host "Primeira coleta enviada com sucesso. O ativo já deve aparecer no RD Intranet." -ForegroundColor Green
    }

    # --------------------------------------------------------------
    # Comandos remotos (desligar/reiniciar) -- vem junto da resposta do
    # checkin. Usa o shutdown.exe nativo do Windows com um tempo de
    # aviso (mostra um alerta do sistema pro usuário, com chance de
    # cancelar via "shutdown /a" localmente antes do prazo acabar).
    $tempoAvisoSegundos = 300

    foreach ($comando in $resposta.comandos) {
        Escrever-Log "Comando remoto recebido: $($comando.comando) (id=$($comando.id))"

        try {
            if ($comando.comando -eq 'desligar') {
                shutdown.exe /s /t $tempoAvisoSegundos /c "RD Intranet: este computador sera desligado remotamente pelo suporte de TI em 5 minutos. Salve seu trabalho."
                Escrever-Log "Desligamento agendado para daqui a $tempoAvisoSegundos segundos."
            } elseif ($comando.comando -eq 'reiniciar') {
                shutdown.exe /r /t $tempoAvisoSegundos /c "RD Intranet: este computador sera reiniciado remotamente pelo suporte de TI em 5 minutos. Salve seu trabalho."
                Escrever-Log "Reinicio agendado para daqui a $tempoAvisoSegundos segundos."
            }
        } catch {
            Escrever-Log "ERRO ao executar comando $($comando.comando): $($_.Exception.Message)"
        }
    }
} catch {
    Escrever-Log "ERRO: $($_.Exception.Message)"

    if (-not $jaInstalado) {
        Write-Host "A instalação foi concluída, mas a primeira coleta falhou: $($_.Exception.Message)" -ForegroundColor Yellow
        Write-Host "A tarefa agendada vai tentar de novo em até 15 minutos. Confira o log em $ArquivoLog se persistir." -ForegroundColor Yellow
    }
}
