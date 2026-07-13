<#
    RD Intranet - Agente de Inventario (Windows)
    ==============================================

    O que faz:
      Coleta hardware, sistema operacional, programas instalados e
      alertas recentes do Visualizador de Eventos, e envia tudo por
      HTTPS para o RD Intranet (modulo Ativos de TI). Roda sem
      instalar nada alem do proprio script -- so usa modulos nativos
      do Windows (CIM/WMI, registro, Get-WinEvent).

    Como instalar (rodar como Administrador):
      1. Copie este arquivo para C:\ProgramData\RDIntranetAgent\rd-intranet-agent.ps1
         (crie a pasta se nao existir).
      2. Registre como Tarefa Agendada rodando a cada 15 minutos:

         schtasks /create /tn "RD Intranet Agente" ^
           /tr "powershell.exe -ExecutionPolicy Bypass -NoProfile -File C:\ProgramData\RDIntranetAgent\rd-intranet-agent.ps1" ^
           /sc minute /mo 15 /ru SYSTEM /rl HIGHEST /f

      3. Pra testar na hora, sem esperar o agendamento:

         powershell.exe -ExecutionPolicy Bypass -NoProfile -File C:\ProgramData\RDIntranetAgent\rd-intranet-agent.ps1

      O log de cada execucao fica em C:\ProgramData\RDIntranetAgent\agente.log --
      confira ali se o ativo nao aparecer no RD Intranet.

    Este arquivo ja vem com o endereco do servidor e a chave de API
    preenchidos (baixado direto da tela Ativos > Dashboard do RD
    Intranet). Se a chave for regenerada no servidor, baixe o script
    de novo e reinstale.
#>

$ServerUrl = '__SERVER_URL__'
$ApiKey    = '__API_KEY__'

$PastaAgente   = 'C:\ProgramData\RDIntranetAgent'
$ArquivoLog    = Join-Path $PastaAgente 'agente.log'
$ArquivoMarca  = Join-Path $PastaAgente 'ultimo_evento.txt'

if (!(Test-Path $PastaAgente)) {
    New-Item -ItemType Directory -Path $PastaAgente -Force | Out-Null
}

function Escrever-Log([string]$Mensagem) {
    $linha = (Get-Date -Format 'yyyy-MM-dd HH:mm:ss') + ' - ' + $Mensagem
    Add-Content -Path $ArquivoLog -Value $linha
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
} catch {
    Escrever-Log "ERRO: $($_.Exception.Message)"
}
