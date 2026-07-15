<#
    RD Intranet - Agente de Inventario (Windows)
    ==============================================

    O que faz:
      Coleta hardware (processador, memoria total/em uso, modulos de
      memoria com fabricante/modelo/frequencia/serie, placa-mae, placa
      de video/som, tipo de memoria), sistema operacional, uptime, rede
      (MAC/IP por adaptador), volumes logicos (uso por unidade + modelo/
      fabricante/serie do disco fisico associado, quando disponivel),
      portas fisicas (USB conectado + seriais), portas de rede em
      escuta (auditoria de seguranca), programas instalados
      (com data de instalacao, quando disponivel), atualizacoes do
      Windows instaladas (KBs) e alertas recentes do Visualizador de
      Eventos, e envia tudo por HTTPS para o RD Intranet (modulo Ativos
      de TI). Tambem busca e executa comandos remotos pendentes
      (desligar/reiniciar, desinstalar atualizacao, desinstalar
      programa -- enviados pela ficha do ativo no RD Intranet).
      Desligar/reiniciar sempre com um aviso do Windows de 5 minutos
      antes de executar (shutdown /a cancela localmente); desinstalacao
      de programa e melhor esforco pra instaladores nao-MSI (pode abrir
      uma tela no computador remoto). Nao instala nada alem de si mesmo
      -- so usa modulos nativos do Windows (CIM/WMI, registro,
      Get-WinEvent, Task Scheduler).

    Limitacao conhecida (status Ligado/Desligado):
      Este script roda como Tarefa Agendada (nasce, coleta, envia,
      termina) -- nao tem como manter o heartbeat de "estou ligado" a
      cada poucos segundos que o agente de bandeja (.exe, veja
      agente-windows/) manda, ja que isso exige um processo residente.
      Maquinas rodando so este .ps1 tem o status Ligado/Desligado
      aproximado pelo horario do ultimo checkin completo (janela de 2x
      o intervalo configurado), nao em tempo real. Se precisar de
      deteccao ao vivo, use o agente de bandeja nessa maquina.
      Pelo mesmo motivo, a coluna "Versao do Agente" na lista de ativos
      mostra so "ps1" pra maquinas usando este script -- ele e baixado
      direto do repositorio a cada instalacao, nao tem numero de versao
      proprio como o .exe (<Version> do .csproj).

    Como instalar:
      1. Baixe este arquivo pela tela Ativos > Dashboard do RD Intranet
         (ja vem com o endereco do servidor e a chave de API preenchidos).
      2. Clique com o botao direito nele e escolha "Executar com o
         PowerShell" -- OU abra um PowerShell como Administrador e rode:

           .\rd-intranet-agent.ps1

      Isso e tudo. Na primeira execucao, se estiver rodando como
      Administrador, o proprio script se copia pra
      C:\ProgramData\RDIntranetAgent\ e se registra como Tarefa Agendada
      rodando no intervalo configurado em Ativos > Dashboard no RD
      Intranet (usuario SYSTEM). As execucoes seguintes (via
      agendamento) so fazem a coleta, sem reinstalar nada.

      Se a chave de API for regenerada no servidor, baixe o script de
      novo e rode de novo do mesmo jeito -- ele substitui a instalacao
      anterior (script + tarefa) pela nova.

      O log de cada execucao fica em C:\ProgramData\RDIntranetAgent\agente.log --
      confira ali se o ativo nao aparecer no RD Intranet.
#>

$ServerUrl        = '__SERVER_URL__'
$ApiKey           = '__API_KEY__'
$IntervaloMinutos = [int]'__INTERVALO_MINUTOS__'

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
            -RepetitionInterval (New-TimeSpan -Minutes $IntervaloMinutos) `
            -RepetitionDuration ([TimeSpan]::MaxValue)

        $principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest

        $configuracoes = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable

        Register-ScheduledTask -TaskName $NomeTarefa -Action $acao -Trigger $gatilho `
            -Principal $principal -Settings $configuracoes -Force | Out-Null

        Write-Host "Instalado com sucesso em $ScriptDestino" -ForegroundColor Green
        Write-Host "Tarefa agendada '$NomeTarefa' criada -- roda a cada $IntervaloMinutos minutos." -ForegroundColor Green
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
    $memoriaLivreGb = [math]::Round($sistema.FreePhysicalMemory / 1MB, 1)
    $memoriaUsadaGb = [math]::Round($memoriaGb - $memoriaLivreGb, 1)

    $discos = Get-CimInstance Win32_DiskDrive | ForEach-Object {
        '{0} ({1} GB)' -f $_.Model, [math]::Round($_.Size / 1GB, 0)
    }
    $armazenamento = $discos -join ', '

    # Placa de video / som / tipo de memoria (Componentes)
    $placaVideo = (Get-CimInstance Win32_VideoController -ErrorAction SilentlyContinue | Select-Object -First 1 -ExpandProperty Name)
    $placaSom = (Get-CimInstance Win32_SoundDevice -ErrorAction SilentlyContinue | Select-Object -First 1 -ExpandProperty Name)

    $tiposMemoria = @{ 20 = 'DDR'; 21 = 'DDR2'; 22 = 'DDR2 FB-DIMM'; 24 = 'DDR3'; 26 = 'DDR4'; 34 = 'DDR5' }
    $codigoMemoria = (Get-CimInstance Win32_PhysicalMemory -ErrorAction SilentlyContinue | Select-Object -First 1 -ExpandProperty SMBIOSMemoryType)
    $tipoMemoria = if ($codigoMemoria -and $tiposMemoria.ContainsKey([int]$codigoMemoria)) { $tiposMemoria[[int]$codigoMemoria] } else { $null }

    # Rede -- todos os adaptadores habilitados, com MAC e IP(s)
    $redes = @(Get-CimInstance Win32_NetworkAdapterConfiguration -Filter 'IPEnabled = True' | ForEach-Object {
        foreach ($enderecoIp in $_.IPAddress) {
            @{ nome_adaptador = $_.Description; mac = $_.MACAddress; ip = $enderecoIp }
        }
    })
    $ip = if ($redes.Count -gt 0) { $redes[0].ip } else { $null }

    $payload = @{
        machine_guid   = $machineGuid
        tipo           = $tipo
        nome           = $env:COMPUTERNAME
        marca          = $computador.Manufacturer
        modelo         = $computador.Model
        numero_serie   = $serialBios
        ip             = $ip
        versao_agente  = 'ps1'
        sistema_operacional = $sistema.Caption
        processador    = $processador.Name
        memoria_ram    = "$memoriaGb GB"
        memoria_usada  = "$memoriaUsadaGb GB"
        tipo_memoria   = $tipoMemoria
        armazenamento  = $armazenamento
        placa_mae      = ('{0} {1}' -f $placaMae.Manufacturer, $placaMae.Product).Trim()
        placa_video    = $placaVideo
        placa_som      = $placaSom
        usuario_logado = $computador.UserName
        funcao         = if ($tipo -eq 'servidor') { $sistema.Caption } else { $null }
        virtualizado   = if ($computador.Model -match 'Virtual|VMware|KVM|VirtualBox') { 'Sim' } else { 'Não' }
        ligado_desde   = $sistema.LastBootUpTime.ToString('yyyy-MM-dd HH:mm:ss')
        redes          = $redes
        volumes        = @()
        portas         = @()
        portas_rede    = @()
        memoria_modulos = @()
        programas      = @()
        atualizacoes_windows = @()
        alertas        = @()
    }

    # --------------------------------------------------------------
    # Volumes logicos (unidades com letra, ex: C:, D:) -- diferente do
    # "armazenamento" acima, que e o disco fisico inteiro. Tenta
    # associar cada volume ao disco fisico por tras dele (modelo/
    # fabricante/serie) -- nem sempre disponivel (discos removiveis,
    # alguns controladores RAID/virtuais nao expoe isso).
    $payload.volumes = @(Get-CimInstance Win32_LogicalDisk -Filter 'DriveType = 3' | ForEach-Object {
        $totalGb = [math]::Round($_.Size / 1GB, 1)
        $livreGb = [math]::Round($_.FreeSpace / 1GB, 1)

        $volume = @{ unidade = $_.DeviceID; total_gb = $totalGb; usado_gb = [math]::Round($totalGb - $livreGb, 1) }

        try {
            $particao = Get-CimAssociatedInstance -InputObject $_ -ResultClassName Win32_DiskPartition -ErrorAction SilentlyContinue | Select-Object -First 1
            if ($particao) {
                $discoFisico = Get-CimAssociatedInstance -InputObject $particao -ResultClassName Win32_DiskDrive -ErrorAction SilentlyContinue | Select-Object -First 1
                if ($discoFisico) {
                    $volume.modelo_disco = $discoFisico.Model
                    $volume.fabricante_disco = $discoFisico.Manufacturer
                    $volume.serial_disco = if ($discoFisico.SerialNumber) { $discoFisico.SerialNumber.Trim() } else { $null }
                }
            }
        } catch {
            # associacao disco-fisico -> volume pode falhar em discos
            # removiveis/de rede -- segue sem esses dados extras
        }

        $volume
    })

    # --------------------------------------------------------------
    # Modulos de memoria fisica (um por pente de RAM instalado)
    $payload.memoria_modulos = @(Get-CimInstance Win32_PhysicalMemory -ErrorAction SilentlyContinue | ForEach-Object {
        @{
            fabricante     = $_.Manufacturer
            modelo         = if ($_.PartNumber) { $_.PartNumber.Trim() } else { $null }
            capacidade_gb  = [math]::Round($_.Capacity / 1GB, 1)
            frequencia_mhz = if ($_.ConfiguredClockSpeed) { $_.ConfiguredClockSpeed } else { $_.Speed }
            numero_serie   = if ($_.SerialNumber) { $_.SerialNumber.Trim() } else { $null }
        }
    })

    # --------------------------------------------------------------
    # Portas fisicas -- dispositivos USB conectados agora + portas
    # seriais (COM) disponiveis. Portas de video (HDMI/DP/VGA) ficam de
    # fora -- o Windows nao expoe isso de forma padronizada via WMI.
    $portasUsb = @()
    try {
        $portasUsb = Get-PnpDevice -Class USB -Status OK -ErrorAction SilentlyContinue |
            Where-Object { $_.FriendlyName } |
            ForEach-Object { @{ tipo = 'usb'; descricao = $_.FriendlyName } }
    } catch {
        # Get-PnpDevice pode nao existir em versoes muito antigas do PowerShell -- ignora
    }

    $portasSerial = @(Get-CimInstance Win32_SerialPort -ErrorAction SilentlyContinue | ForEach-Object {
        @{ tipo = 'serial'; descricao = "$($_.DeviceID) - $($_.Description)" }
    })

    $payload.portas = @($portasUsb) + @($portasSerial)

    # --------------------------------------------------------------
    # Portas de rede em escuta (LISTENING) -- pra auditoria de
    # seguranca, mostra o que a maquina esta expondo na rede. So
    # existe em Windows com o modulo NetTCPIP (Windows 8/2012 em
    # diante) -- em versoes mais antigas simplesmente fica vazio.
    $portasRede = @()
    try {
        $portasRede += Get-NetTCPConnection -State Listen -ErrorAction SilentlyContinue | ForEach-Object {
            $proc = Get-Process -Id $_.OwningProcess -ErrorAction SilentlyContinue
            @{
                protocolo      = 'tcp'
                porta_local    = $_.LocalPort
                endereco_local = $_.LocalAddress
                processo       = if ($proc) { $proc.ProcessName } else { $null }
                pid            = $_.OwningProcess
            }
        }
    } catch {
        # Get-NetTCPConnection pode nao existir em Windows antigos -- ignora
    }

    try {
        $portasRede += Get-NetUDPEndpoint -ErrorAction SilentlyContinue | ForEach-Object {
            $proc = Get-Process -Id $_.OwningProcess -ErrorAction SilentlyContinue
            @{
                protocolo      = 'udp'
                porta_local    = $_.LocalPort
                endereco_local = $_.LocalAddress
                processo       = if ($proc) { $proc.ProcessName } else { $null }
                pid            = $_.OwningProcess
            }
        }
    } catch {
        # Get-NetUDPEndpoint pode nao existir em Windows antigos -- ignora
    }

    $payload.portas_rede = @($portasRede)

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
            Select-Object -Property @{n='nome'; e={$_.DisplayName}}, @{n='versao'; e={$_.DisplayVersion}}, @{n='data_instalacao'; e={
                # InstallDate vem como texto "yyyyMMdd" (ex: 20250601) --
                # nem todo instalador preenche esse valor.
                if ($_.InstallDate -and $_.InstallDate -match '^\d{8}$') {
                    [datetime]::ParseExact($_.InstallDate, 'yyyyMMdd', $null).ToString('yyyy-MM-dd')
                } else {
                    $null
                }
            }}, @{n='uninstall_string'; e={ $_.UninstallString }}
    }

    $payload.programas = @($programas | Sort-Object nome -Unique)

    # --------------------------------------------------------------
    # Atualizacoes do Windows instaladas (hotfixes/KBs) -- o mesmo local
    # que o comando "wmic qfe list" ou o cmdlet Get-HotFix usam por
    # baixo. InstalledOn nem sempre vem preenchido pelo Windows.
    $payload.atualizacoes_windows = @(Get-CimInstance Win32_QuickFixEngineering -ErrorAction SilentlyContinue | ForEach-Object {
        # InstalledOn vem ora como [datetime], ora como texto (inconsistencia
        # conhecida dessa classe WMI, varia por versao/idioma do Windows) --
        # o cast [datetime] aceita os dois casos.
        $instaladoEm = $null
        if ($_.InstalledOn) {
            try { $instaladoEm = ([datetime]$_.InstalledOn).ToString('yyyy-MM-dd') } catch { $instaladoEm = $null }
        }

        @{
            kb            = $_.HotFixID
            descricao     = $_.Description
            instalado_em  = $instaladoEm
        }
    })

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

    Escrever-Log "OK: checkin enviado (guid=$machineGuid, tipo=$tipo, $($payload.programas.Count) programas, $($payload.alertas.Count) alertas, $($payload.redes.Count) redes, $($payload.volumes.Count) volumes, $($payload.portas.Count) portas, $($payload.portas_rede.Count) portas de rede, $($payload.memoria_modulos.Count) modulos de memoria, $($payload.atualizacoes_windows.Count) atualizacoes do Windows). Resposta: $($resposta.message)"

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
        Escrever-Log "Comando remoto recebido: $($comando.comando) (id=$($comando.id), alvo=$($comando.alvo))"

        try {
            switch ($comando.comando) {
                'desligar' {
                    shutdown.exe /s /t $tempoAvisoSegundos /c "RD Intranet: este computador sera desligado remotamente pelo suporte de TI em 5 minutos. Salve seu trabalho."
                    Escrever-Log "Desligamento agendado para daqui a $tempoAvisoSegundos segundos."
                }
                'reiniciar' {
                    shutdown.exe /r /t $tempoAvisoSegundos /c "RD Intranet: este computador sera reiniciado remotamente pelo suporte de TI em 5 minutos. Salve seu trabalho."
                    Escrever-Log "Reinicio agendado para daqui a $tempoAvisoSegundos segundos."
                }
                'desinstalar_atualizacao' {
                    # wusa.exe quer so o numero, sem o prefixo "KB". Nem toda
                    # atualizacao pode ser removida (algumas ja foram
                    # substituidas por atualizacoes cumulativas mais novas --
                    # limitacao do proprio Windows, o comando so falha nesse caso).
                    $kb = $comando.alvo -replace '^KB', ''
                    Start-Process -FilePath 'wusa.exe' -ArgumentList "/uninstall /kb:$kb /quiet /norestart" -Wait -ErrorAction Stop
                    Escrever-Log "Desinstalacao da atualizacao KB$kb solicitada ao Windows."
                }
                'desinstalar_programa' {
                    # UninstallString bruta do registro. Se for instalador MSI,
                    # trocamos /I (instalar/reparar) por /X (desinstalar) e
                    # forcamos modo silencioso. Instaladores nao-MSI rodam como
                    # estao -- podem abrir uma tela no computador remoto, nao ha
                    # como garantir silencio total nesse caso.
                    $alvo = $comando.alvo
                    if ($alvo -match '\{[0-9A-Fa-f-]{36}\}') {
                        $guid = $matches[0]
                        Start-Process -FilePath 'msiexec.exe' -ArgumentList "/X$guid /quiet /norestart" -Wait -ErrorAction Stop
                        Escrever-Log "Desinstalacao via MSI ($guid) executada."
                    } else {
                        Start-Process -FilePath 'cmd.exe' -ArgumentList "/c `"$alvo`"" -Wait -ErrorAction Stop
                        Escrever-Log "Desinstalacao via comando nao-MSI executada (pode ter aberto uma tela no computador remoto)."
                    }
                }
            }
        } catch {
            Escrever-Log "ERRO ao executar comando $($comando.comando): $($_.Exception.Message)"
        }
    }
} catch {
    Escrever-Log "ERRO: $($_.Exception.Message)"

    if (-not $jaInstalado) {
        Write-Host "A instalação foi concluída, mas a primeira coleta falhou: $($_.Exception.Message)" -ForegroundColor Yellow
        Write-Host "A tarefa agendada vai tentar de novo em até $IntervaloMinutos minutos. Confira o log em $ArquivoLog se persistir." -ForegroundColor Yellow
    }
}
