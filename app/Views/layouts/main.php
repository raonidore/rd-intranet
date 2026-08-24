<?php

use App\Services\AtivoService;
use App\Services\AvisoService;
use App\Services\PermissionService;
use App\Services\WhatsAppAtendimentoService;
use App\Services\ChamadoService;
use App\Services\ChatService;
use App\Services\ModuloCatalogo;

$usuarioLogado = $_SESSION['usuario'] ?? ['nome' => 'Usuário'];
$ativoServiceMenu = new AtivoService();
$logoEmpresaConfigurada = $ativoServiceMenu->logoEmpresaConfigurada();
$logoSistemaConfigurada = $ativoServiceMenu->logoSistemaConfigurada();
$titulo = $titulo ?? 'RD Intranet';

$uriAtual = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '/';
$baseUrl = rtrim(url(''), '/');
if ($baseUrl !== '' && str_starts_with($uriAtual, $baseUrl)) {
    $uriAtual = substr($uriAtual, strlen($baseUrl)) ?: '/';
}

$rdSecaoAtiva = function (array $prefixos) use ($uriAtual): bool {
    foreach ($prefixos as $p) {
        if (str_starts_with($uriAtual, $p)) {
            return true;
        }
    }
    return false;
};

$abrirApache = $rdSecaoAtiva(['/apache']);
$abrirBancoDados = $rdSecaoAtiva(['/banco-dados']);
$abrirSsh = $rdSecaoAtiva(['/ssh']);
$abrirInfraestrutura = $rdSecaoAtiva(['/infraestrutura']);
$abrirSamba = $rdSecaoAtiva(['/samba', '/deploy']);
$abrirSeguranca = $rdSecaoAtiva(['/seguranca']);
$abrirBackup = $rdSecaoAtiva(['/backup']);
$abrirVpn = $rdSecaoAtiva(['/vpn']);
$abrirSistema = $rdSecaoAtiva(['/administracao', '/auditoria']);
$abrirEntra = $rdSecaoAtiva(['/entra']);
$abrirBaseConhecimento = $rdSecaoAtiva(['/base-conhecimento']);
$abrirWhatsapp = $rdSecaoAtiva(['/whatsapp']);
$abrirChamados = $rdSecaoAtiva(['/chamados']);

$abrirHardware = $rdSecaoAtiva(['/infraestrutura/hardware']);
$abrirRede = $rdSecaoAtiva(['/infraestrutura/rede', '/infraestrutura/servidor/rede']);
$abrirInfraServicos = $rdSecaoAtiva(['/infraestrutura/servicos']);
$abrirVpnWireguard = $rdSecaoAtiva(['/vpn/wireguard']);
$abrirVpnOpenvpn = $rdSecaoAtiva(['/vpn/openvpn']);
$abrirVpnIkev2 = $rdSecaoAtiva(['/vpn/ikev2']);
$abrirAtivos = $rdSecaoAtiva(['/ativos']);
$abrirSistemaModulos = $rdSecaoAtiva(['/administracao/modulos']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($titulo) ?></title>

    <link rel="icon" href="<?= url('/favicon.ico') ?>" sizes="any">
    <link rel="icon" href="<?= url('/assets/img/favicon.png') ?>" type="image/png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset_url('/assets/css/rd-ui.css') ?>">

    <style>
        body { background:#f4f6f9; }
        .sidebar {
            width:260px;
            height:100vh;
            position:fixed;
            left:0;
            top:0;
            background:#111827;
            color:#fff;
            overflow-y:auto;
        }
        .sidebar a {
            color:#d1d5db;
            text-decoration:none;
            display:block;
            padding:10px 18px;
            border-radius:8px;
            margin:3px 12px;
        }
        .sidebar a:hover, .sidebar a.active {
            background:#1f2937;
            color:#fff;
        }
        .sidebar a.rd-menu-item-badge {
            display:flex;
            justify-content:space-between;
            align-items:center;
        }
        .rd-menu-badge {
            background:var(--rd-primary);
            color:#fff;
            font-size:11px;
            font-weight:700;
            min-width:20px;
            height:20px;
            line-height:20px;
            text-align:center;
            border-radius:10px;
            padding:0 6px;
            flex-shrink:0;
        }
        .menu-section {
            color:#6b7280;
            font-size:11px;
            font-weight:700;
            letter-spacing:.08em;
            text-transform:uppercase;
            padding:18px 24px 6px;
        }
        .menu-toggle {
            width:calc(100% - 24px);
            margin:6px 12px 2px;
            background:none;
            border:none;
            color:#9ca3af;
            font-size:11px;
            font-weight:700;
            letter-spacing:.08em;
            text-transform:uppercase;
            padding:10px 12px;
            display:flex;
            justify-content:space-between;
            align-items:center;
            border-radius:8px;
            cursor:pointer;
        }
        .menu-toggle:hover { background:#1f2937; color:#fff; }
        .menu-toggle[aria-expanded="true"] { color:#fff; }
        .menu-toggle .chevron { transition:transform .2s ease; font-size:10px; }
        .menu-toggle[aria-expanded="true"] .chevron { transform:rotate(90deg); }
        /* Item único de topo (sem submenu) que precisa ficar do mesmo tamanho/estilo dos cabeçalhos de grupo (.menu-toggle) -- Chat, Documentos, Fornecedores. */
        .sidebar a.menu-single {
            font-size:11px;
            font-weight:700;
            letter-spacing:.08em;
            text-transform:uppercase;
            color:#9ca3af;
        }
        .sidebar a.menu-single:hover, .sidebar a.menu-single.active { color:#fff; }
        .sidebar a.menu-single i { font-size:14px; }
        .menu-toggle-sub {
            font-size:10px;
            padding:8px 12px 8px 22px;
            margin:2px 12px 2px 12px;
        }
        .menu-sub a { padding-left:34px; font-size:13px; }
        .content {
            margin-left:260px;
            padding:28px;
        }
        .avatar {
            width:42px;
            height:42px;
            border-radius:50%;
            background:#2563eb;
            color:white;
            display:flex;
            align-items:center;
            justify-content:center;
            font-weight:700;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="p-4 text-center">
        <img src="<?= $logoSistemaConfigurada ? url('/administracao/empresa/logo-sistema') : url('/assets/img/logord.png') ?>" alt="RD Intranet" style="width:100%; max-width:220px; display:block; margin:0 auto; border-radius:14px;">
        <small class="d-block mt-2" style="color:#cbd5e1;">Painel Administrativo</small>
        <?php if ($logoEmpresaConfigurada): ?>
            <div class="mt-3" style="background:#fff; border-radius:12px; padding:10px 14px; display:inline-block; box-shadow:0 2px 6px rgba(0,0,0,.25);">
                <img src="<?= url('/administracao/empresa/logo') ?>" alt="" style="max-height:32px;max-width:160px;display:block;">
            </div>
        <?php endif; ?>
    </div>

    <a href="<?= url('/dashboard') ?>" class="<?= in_array($uriAtual, ['/', '/dashboard'], true) ? 'active' : '' ?>">
        <i class="bi bi-speedometer2 me-2"></i> Dashboard
    </a>

    <?php
    $temApache = PermissionService::temAcesso('apache_dashboard')
        || PermissionService::temAcesso('apache_sites')
        || PermissionService::temAcesso('apache_modulos')
        || PermissionService::temAcesso('apache_config');
    ?>
    <?php if ($temApache): ?>
    <button class="menu-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuApache"
            aria-expanded="<?= $abrirApache ? 'true' : 'false' ?>">
        <span><i class="bi bi-server me-2"></i>Apache</span>
        <i class="bi bi-chevron-right chevron"></i>
    </button>
    <div class="collapse <?= $abrirApache ? 'show' : '' ?>" id="menuApache">
        <?php if (PermissionService::temAcesso('apache_dashboard')): ?>
        <a href="<?= url('/apache/dashboard') ?>" class="<?= $uriAtual === '/apache/dashboard' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2 me-2"></i> Dashboard Apache
        </a>
        <?php endif; ?>

        <?php if (PermissionService::temAcesso('apache_config')): ?>
        <a href="<?= url('/apache/configuracao') ?>" class="<?= $uriAtual === '/apache/configuracao' ? 'active' : '' ?>">
            <i class="bi bi-sliders me-2"></i> Config. Global
        </a>
        <?php endif; ?>

        <?php if (PermissionService::temAcesso('apache_modulos')): ?>
        <a href="<?= url('/apache/modulos') ?>" class="<?= $uriAtual === '/apache/modulos' ? 'active' : '' ?>">
            <i class="bi bi-puzzle me-2"></i> Módulos
        </a>
        <?php endif; ?>

        <?php if (PermissionService::temAcesso('apache_sites')): ?>
        <a href="<?= url('/apache/sites') ?>" class="<?= $uriAtual === '/apache/sites' ? 'active' : '' ?>">
            <i class="bi bi-globe me-2"></i> Sites
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
    $temAtivos = PermissionService::temAcesso('ativos_dashboard')
        || PermissionService::temAcesso('ativos_lista')
        || PermissionService::temAcesso('ativos_novo')
        || PermissionService::temAcesso('ativos_cadastros')
        || PermissionService::temAcesso('ativos_acesso_remoto')
        || PermissionService::temAcesso('ativos_etiqueta_config')
        || PermissionService::temAcesso('ativos_politicas');
    ?>
    <?php if ($temAtivos): ?>
    <button class="menu-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuAtivos"
            aria-expanded="<?= $abrirAtivos ? 'true' : 'false' ?>">
        <span><i class="bi bi-boxes me-2"></i>Ativos</span>
        <i class="bi bi-chevron-right chevron"></i>
    </button>
    <div class="collapse <?= $abrirAtivos ? 'show' : '' ?>" id="menuAtivos">
        <?php if (PermissionService::temAcesso('ativos_dashboard')): ?>
        <a href="<?= url('/ativos') ?>" class="<?= $uriAtual === '/ativos' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2 me-2"></i> Dashboard
        </a>
        <?php endif; ?>

        <?php if (PermissionService::temAcesso('ativos_lista')): ?>
        <a href="<?= url('/ativos/lista') ?>" class="<?= str_starts_with($uriAtual, '/ativos/lista') || str_starts_with($uriAtual, '/ativos/ver') ? 'active' : '' ?>">
            <i class="bi bi-list-ul me-2"></i> Lista de Ativos
        </a>
        <?php endif; ?>

        <?php if (PermissionService::temAcesso('ativos_novo')): ?>
        <a href="<?= url('/ativos/novo') ?>" class="<?= str_starts_with($uriAtual, '/ativos/novo') || str_starts_with($uriAtual, '/ativos/editar') ? 'active' : '' ?>">
            <i class="bi bi-plus-lg me-2"></i> Novo Ativo
        </a>
        <?php endif; ?>

        <?php if (PermissionService::temAcesso('ativos_cadastros')): ?>
        <a href="<?= url('/ativos/cadastros') ?>" class="<?= str_starts_with($uriAtual, '/ativos/cadastros') ? 'active' : '' ?>">
            <i class="bi bi-tags me-2"></i> Cadastros
        </a>
        <?php endif; ?>

        <?php if (PermissionService::temAcesso('ativos_acesso_remoto')): ?>
        <a href="<?= url('/ativos/acesso-remoto') ?>" class="<?= str_starts_with($uriAtual, '/ativos/acesso-remoto') ? 'active' : '' ?>">
            <i class="bi bi-display me-2"></i> Acesso Remoto
        </a>
        <?php endif; ?>

        <?php if (PermissionService::temAcesso('ativos_etiqueta_config')): ?>
        <a href="<?= url('/ativos/etiqueta-config') ?>" class="<?= str_starts_with($uriAtual, '/ativos/etiqueta-config') ? 'active' : '' ?>">
            <i class="bi bi-qr-code me-2"></i> Configurações de Etiqueta
        </a>
        <?php endif; ?>

        <?php if (PermissionService::temAcesso('ativos_politicas')): ?>
        <a href="<?= url('/ativos/politicas') ?>" class="<?= str_starts_with($uriAtual, '/ativos/politicas') ? 'active' : '' ?>">
            <i class="bi bi-shield-check me-2"></i> Regras de Segurança
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
    $temBackup = PermissionService::temAcesso('backup_configuracao') || PermissionService::temAcesso('backup_historico');
    ?>
    <?php if ($temBackup): ?>
    <button class="menu-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuBackup"
            aria-expanded="<?= $abrirBackup ? 'true' : 'false' ?>">
        <span><i class="bi bi-cloud-arrow-up me-2"></i>Backup</span>
        <i class="bi bi-chevron-right chevron"></i>
    </button>
    <div class="collapse <?= $abrirBackup ? 'show' : '' ?>" id="menuBackup">
        <?php if (PermissionService::temAcesso('backup_configuracao')): ?>
        <a href="<?= url('/backup/configuracao') ?>" class="<?= $uriAtual === '/backup/configuracao' ? 'active' : '' ?>">
            <i class="bi bi-gear me-2"></i> Configuração
        </a>
        <?php endif; ?>
        <?php if (PermissionService::temAcesso('backup_historico')): ?>
        <a href="<?= url('/backup/historico') ?>" class="<?= $uriAtual === '/backup/historico' ? 'active' : '' ?>">
            <i class="bi bi-clock-history me-2"></i> Histórico
        </a>
        <a href="<?= url('/backup/explorador') ?>" class="<?= $uriAtual === '/backup/explorador' ? 'active' : '' ?>">
            <i class="bi bi-search me-2"></i> Explorador de Versões
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (PermissionService::temAcesso('bd_mysql')): ?>
    <button class="menu-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuBancoDados"
            aria-expanded="<?= $abrirBancoDados ? 'true' : 'false' ?>">
        <span><i class="bi bi-database me-2"></i>Banco de Dados</span>
        <i class="bi bi-chevron-right chevron"></i>
    </button>
    <div class="collapse <?= $abrirBancoDados ? 'show' : '' ?>" id="menuBancoDados">
        <a href="<?= url('/banco-dados/conexoes') ?>" class="<?= $uriAtual === '/banco-dados/conexoes' || str_starts_with($uriAtual, '/banco-dados/console') ? 'active' : '' ?>">
            <i class="bi bi-hdd-stack me-2"></i> Conexões / Console
        </a>
    </div>
    <?php endif; ?>

    <?php if (PermissionService::temAcesso('base_conhecimento_visualizar')): ?>
    <button class="menu-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuBaseConhecimento"
            aria-expanded="<?= $abrirBaseConhecimento ? 'true' : 'false' ?>">
        <span><i class="bi bi-journal-text me-2"></i>Base de Conhecimento</span>
        <i class="bi bi-chevron-right chevron"></i>
    </button>
    <div class="collapse <?= $abrirBaseConhecimento ? 'show' : '' ?>" id="menuBaseConhecimento">
        <a href="<?= url('/base-conhecimento') ?>" class="<?= str_starts_with($uriAtual, '/base-conhecimento') ? 'active' : '' ?>">
            <i class="bi bi-journal-text me-2"></i> Artigos
        </a>
    </div>
    <?php endif; ?>

    <?php
    $temChamados = PermissionService::temAcesso('chamados_atendimentos')
        || PermissionService::temAcesso('chamados_fila')
        || PermissionService::temAcesso('chamados_categorias')
        || PermissionService::temAcesso('chamados_setores')
        || PermissionService::temAcesso('chamados_estatisticas')
        || PermissionService::temAcesso('chamados_configuracoes');
    ?>
    <?php
    $chamadosAguardando = 0;
    if (PermissionService::temAcesso('chamados_atendimentos') && isset($_SESSION['usuario']['id'])) {
        $chamadosAguardando = (new ChamadoService())->contarAguardandoResposta((int)$_SESSION['usuario']['id']);
    }
    ?>
    <?php if ($temChamados): ?>
    <button class="menu-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuChamados"
            aria-expanded="<?= $abrirChamados ? 'true' : 'false' ?>">
        <span><i class="bi bi-ticket-perforated me-2"></i>Chamados</span>
        <i class="bi bi-chevron-right chevron"></i>
    </button>
    <div class="collapse <?= $abrirChamados ? 'show' : '' ?>" id="menuChamados">
        <?php if (PermissionService::temAcesso('chamados_atendimentos')): ?>
        <a href="<?= url('/chamados/atendimentos') ?>" class="rd-menu-item-badge <?= str_starts_with($uriAtual, '/chamados/atendimentos') ? 'active' : '' ?>">
            <span><i class="bi bi-ticket-detailed me-2"></i> Atendimentos</span>
            <span class="rd-menu-badge" id="rdChamadosBadgeAtendimentos" style="<?= $chamadosAguardando > 0 ? '' : 'display:none' ?>"><?= $chamadosAguardando ?></span>
        </a>
        <?php endif; ?>
        <?php if (PermissionService::temAcesso('chamados_fila')): ?>
        <a href="<?= url('/chamados/fila') ?>" class="<?= $uriAtual === '/chamados/fila' ? 'active' : '' ?>">
            <i class="bi bi-hourglass-split me-2"></i> Fila
        </a>
        <?php endif; ?>
        <?php if (PermissionService::temAcesso('chamados_externos_atendimentos')): ?>
        <a href="<?= url('/chamados-externos') ?>" class="<?= str_starts_with($uriAtual, '/chamados-externos') ? 'active' : '' ?>">
            <i class="bi bi-building-gear me-2"></i> Chamados Externos
        </a>
        <?php endif; ?>
        <?php if (PermissionService::temAcesso('chamados_categorias')): ?>
        <a href="<?= url('/chamados/categorias') ?>" class="<?= $uriAtual === '/chamados/categorias' ? 'active' : '' ?>">
            <i class="bi bi-tags me-2"></i> Categorias
        </a>
        <?php endif; ?>
        <?php if (PermissionService::temAcesso('chamados_setores')): ?>
        <a href="<?= url('/chamados/setores') ?>" class="<?= $uriAtual === '/chamados/setores' ? 'active' : '' ?>">
            <i class="bi bi-diagram-3 me-2"></i> Setores
        </a>
        <?php endif; ?>
        <?php if (PermissionService::temAcesso('chamados_estatisticas')): ?>
        <a href="<?= url('/chamados/estatisticas') ?>" class="<?= $uriAtual === '/chamados/estatisticas' ? 'active' : '' ?>">
            <i class="bi bi-bar-chart-line me-2"></i> Estatísticas
        </a>
        <?php endif; ?>
        <?php if (PermissionService::temAcesso('chamados_configuracoes')): ?>
        <a href="<?= url('/chamados/configuracoes') ?>" class="<?= $uriAtual === '/chamados/configuracoes' ? 'active' : '' ?>">
            <i class="bi bi-gear me-2"></i> Configurações
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
    $avisosNaoLidos = 0;
    if (ModuloCatalogo::grupoHabilitado('Avisos') && isset($_SESSION['usuario']['id'])) {
        $avisosNaoLidos = (new AvisoService())->contarNaoLidos((int)$_SESSION['usuario']['id']);
    }
    ?>
    <?php if (ModuloCatalogo::grupoHabilitado('Avisos') && isset($_SESSION['usuario']['id'])): ?>
    <a href="<?= url('/avisos') ?>" class="menu-single rd-menu-item-badge <?= str_starts_with($uriAtual, '/avisos') ? 'active' : '' ?>">
        <span><i class="bi bi-megaphone-fill me-2"></i> Avisos</span>
        <span class="rd-menu-badge" id="rdAvisosBadge" style="<?= $avisosNaoLidos > 0 ? '' : 'display:none' ?>"><?= $avisosNaoLidos ?></span>
    </a>
    <?php endif; ?>

    <?php
    $chatNaoLidas = 0;
    if (PermissionService::temAcesso('chat_conversas') && isset($_SESSION['usuario']['id'])) {
        $chatNaoLidas = (new ChatService())->contarNaoLidas((int)$_SESSION['usuario']['id']);
    }
    ?>
    <?php if (PermissionService::temAcesso('chat_conversas')): ?>
    <a href="<?= url('/chat') ?>" class="menu-single rd-menu-item-badge <?= str_starts_with($uriAtual, '/chat') ? 'active' : '' ?>">
        <span><i class="bi bi-chat-dots-fill me-2"></i> Chat</span>
        <span class="rd-menu-badge" id="rdChatBadge" style="<?= $chatNaoLidas > 0 ? '' : 'display:none' ?>"><?= $chatNaoLidas ?></span>
    </a>
    <?php endif; ?>

    <?php if (PermissionService::temAcesso('documentos_acessar') || PermissionService::temAcesso('documentos_categorias')): ?>
    <a href="<?= url('/documentos') ?>" class="menu-single <?= str_starts_with($uriAtual, '/documentos') ? 'active' : '' ?>">
        <i class="bi bi-folder2-open me-2"></i> Documentos
    </a>
    <?php endif; ?>

    <?php if (PermissionService::temAcesso('fornecedores_gerenciar')): ?>
    <a href="<?= url('/fornecedores') ?>" class="menu-single <?= str_starts_with($uriAtual, '/fornecedores') ? 'active' : '' ?>">
        <i class="bi bi-truck me-2"></i> Fornecedores
    </a>
    <?php endif; ?>

    <?php
    $temInfra = PermissionService::temAcesso('infra_servidor')
        || PermissionService::temAcesso('infra_hardware')
        || PermissionService::temAcesso('infra_rede')
        || PermissionService::temAcesso('infra_servicos')
        || PermissionService::temAcesso('infra_cron')
        || PermissionService::temAcesso('infra_iptables')
        || PermissionService::temAcesso('infra_certificado')
        || PermissionService::temAcesso('infra_speedtest')
        || PermissionService::temAcesso('infra_ddns')
        || PermissionService::temAcesso('infra_tuneis');
    ?>
    <?php if ($temInfra): ?>
    <button class="menu-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuInfra"
            aria-expanded="<?= $abrirInfraestrutura ? 'true' : 'false' ?>">
        <span><i class="bi bi-diagram-3 me-2"></i>Infraestrutura</span>
        <i class="bi bi-chevron-right chevron"></i>
    </button>
    <div class="collapse <?= $abrirInfraestrutura ? 'show' : '' ?>" id="menuInfra">
        <?php if (PermissionService::temAcesso('infra_servidor')): ?>
        <a href="<?= url('/infraestrutura/servidor') ?>" class="<?= $uriAtual === '/infraestrutura/servidor' ? 'active' : '' ?>">
            <i class="bi bi-hdd-rack me-2"></i> Servidor
        </a>
        <?php endif; ?>

        <?php if (PermissionService::temAcesso('infra_hardware')): ?>
        <button class="menu-toggle menu-toggle-sub" type="button" data-bs-toggle="collapse" data-bs-target="#menuHardware"
                aria-expanded="<?= $abrirHardware ? 'true' : 'false' ?>">
            <span><i class="bi bi-cpu me-2"></i>Hardware</span>
            <i class="bi bi-chevron-right chevron"></i>
        </button>
        <div class="collapse menu-sub <?= $abrirHardware ? 'show' : '' ?>" id="menuHardware">
            <a href="<?= url('/infraestrutura/hardware') ?>" class="<?= $uriAtual === '/infraestrutura/hardware' ? 'active' : '' ?>">
                <i class="bi bi-motherboard me-2"></i> CPU / RAM / Disco / Temp.
            </a>
        </div>
        <?php endif; ?>

        <?php if (PermissionService::temAcesso('infra_rede')): ?>
        <button class="menu-toggle menu-toggle-sub" type="button" data-bs-toggle="collapse" data-bs-target="#menuNetwork"
                aria-expanded="<?= $abrirRede ? 'true' : 'false' ?>">
            <span><i class="bi bi-diagram-2 me-2"></i>Network</span>
            <i class="bi bi-chevron-right chevron"></i>
        </button>
        <div class="collapse menu-sub <?= $abrirRede ? 'show' : '' ?>" id="menuNetwork">
            <a href="<?= url('/infraestrutura/rede') ?>" class="<?= $uriAtual === '/infraestrutura/rede' ? 'active' : '' ?>">
                <i class="bi bi-ethernet me-2"></i> Interfaces
            </a>
            <a href="<?= url('/infraestrutura/rede/rotas') ?>" class="<?= $uriAtual === '/infraestrutura/rede/rotas' ? 'active' : '' ?>">
                <i class="bi bi-signpost-split me-2"></i> Rotas
            </a>
            <a href="<?= url('/infraestrutura/rede/arp') ?>" class="<?= $uriAtual === '/infraestrutura/rede/arp' ? 'active' : '' ?>">
                <i class="bi bi-list-ul me-2"></i> ARP
            </a>
            <a href="<?= url('/infraestrutura/rede/ping') ?>" class="<?= $uriAtual === '/infraestrutura/rede/ping' ? 'active' : '' ?>">
                <i class="bi bi-broadcast me-2"></i> Ping
            </a>
            <a href="<?= url('/infraestrutura/rede/traceroute') ?>" class="<?= $uriAtual === '/infraestrutura/rede/traceroute' ? 'active' : '' ?>">
                <i class="bi bi-signpost me-2"></i> Traceroute
            </a>
            <a href="<?= url('/infraestrutura/rede/mtr') ?>" class="<?= $uriAtual === '/infraestrutura/rede/mtr' ? 'active' : '' ?>">
                <i class="bi bi-activity me-2"></i> MTR
            </a>
            <a href="<?= url('/infraestrutura/rede/dns') ?>" class="<?= $uriAtual === '/infraestrutura/rede/dns' ? 'active' : '' ?>">
                <i class="bi bi-search me-2"></i> Diagnóstico DNS
            </a>
            <a href="<?= url('/infraestrutura/rede/trafego') ?>" class="<?= $uriAtual === '/infraestrutura/rede/trafego' ? 'active' : '' ?>">
                <i class="bi bi-speedometer me-2"></i> Tráfego de Banda
            </a>
            <a href="<?= url('/infraestrutura/rede/trafego/historico') ?>" class="<?= $uriAtual === '/infraestrutura/rede/trafego/historico' ? 'active' : '' ?>">
                <i class="bi bi-bar-chart-line me-2"></i> Histórico de Tráfego
            </a>
        </div>
        <?php endif; ?>

        <?php if (PermissionService::temAcesso('infra_servicos')): ?>
        <button class="menu-toggle menu-toggle-sub" type="button" data-bs-toggle="collapse" data-bs-target="#menuInfraServicos"
                aria-expanded="<?= $abrirInfraServicos ? 'true' : 'false' ?>">
            <span><i class="bi bi-hdd-network me-2"></i>Serviços</span>
            <i class="bi bi-chevron-right chevron"></i>
        </button>
        <div class="collapse menu-sub <?= $abrirInfraServicos ? 'show' : '' ?>" id="menuInfraServicos">
            <a href="<?= url('/infraestrutura/servicos') ?>" class="<?= $uriAtual === '/infraestrutura/servicos' ? 'active' : '' ?>">
                <i class="bi bi-hdd-network me-2"></i> Serviços do Sistema
            </a>
        </div>
        <?php endif; ?>

        <?php if (PermissionService::temAcesso('infra_cron')): ?>
        <a href="<?= url('/infraestrutura/cron') ?>" class="<?= str_starts_with($uriAtual, '/infraestrutura/cron') ? 'active' : '' ?>">
            <i class="bi bi-clock-history me-2"></i> Cron
        </a>
        <?php endif; ?>

        <?php if (PermissionService::temAcesso('infra_iptables')): ?>
        <a href="<?= url('/infraestrutura/iptables') ?>" class="<?= str_starts_with($uriAtual, '/infraestrutura/iptables') ? 'active' : '' ?>">
            <i class="bi bi-hdd-network me-2"></i> Firewall
        </a>
        <?php endif; ?>

        <?php if (PermissionService::temAcesso('infra_certificado')): ?>
        <a href="<?= url('/infraestrutura/certificado') ?>" class="<?= str_starts_with($uriAtual, '/infraestrutura/certificado') ? 'active' : '' ?>">
            <i class="bi bi-shield-lock me-2"></i> Certificado Digital
        </a>
        <?php endif; ?>

        <?php if (PermissionService::temAcesso('infra_speedtest')): ?>
        <a href="<?= url('/infraestrutura/velocidade') ?>" class="<?= str_starts_with($uriAtual, '/infraestrutura/velocidade') ? 'active' : '' ?>">
            <i class="bi bi-speedometer2 me-2"></i> Teste de Velocidade
        </a>
        <?php endif; ?>

        <?php if (PermissionService::temAcesso('infra_ddns')): ?>
        <a href="<?= url('/infraestrutura/ddns') ?>" class="<?= str_starts_with($uriAtual, '/infraestrutura/ddns') ? 'active' : '' ?>">
            <i class="bi bi-globe2 me-2"></i> DNS Dinâmico
        </a>
        <?php endif; ?>

        <?php if (PermissionService::temAcesso('infra_tuneis')): ?>
        <a href="<?= url('/infraestrutura/tuneis') ?>" class="<?= str_starts_with($uriAtual, '/infraestrutura/tuneis') ? 'active' : '' ?>">
            <i class="bi bi-signpost-split me-2"></i> Túneis
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (PermissionService::temAcesso('entra_dashboard') || PermissionService::temAcesso('entra_usuarios') || PermissionService::temAcesso('entra_configuracao')): ?>
    <button class="menu-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuEntra"
            aria-expanded="<?= $abrirEntra ? 'true' : 'false' ?>">
        <span><i class="bi bi-microsoft me-2"></i>Microsoft Entra</span>
        <i class="bi bi-chevron-right chevron"></i>
    </button>
    <div class="collapse <?= $abrirEntra ? 'show' : '' ?>" id="menuEntra">
        <?php if (PermissionService::temAcesso('entra_dashboard')): ?>
        <a href="<?= url('/entra/dashboard') ?>" class="<?= $uriAtual === '/entra/dashboard' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2 me-2"></i> Dashboard
        </a>
        <?php endif; ?>
        <?php if (PermissionService::temAcesso('entra_usuarios')): ?>
        <a href="<?= url('/entra/usuarios') ?>" class="<?= str_starts_with($uriAtual, '/entra/usuarios') ? 'active' : '' ?>">
            <i class="bi bi-people me-2"></i> Usuários
        </a>
        <?php endif; ?>
        <?php if (PermissionService::temAcesso('entra_usuarios') && PermissionService::temAcesso('ativos_novo')): ?>
        <a href="<?= url('/entra/acesso-maquinas') ?>" class="<?= str_starts_with($uriAtual, '/entra/acesso-maquinas') ? 'active' : '' ?>">
            <i class="bi bi-shield-lock me-2"></i> Acesso às Máquinas
        </a>
        <?php endif; ?>
        <?php if (PermissionService::temAcesso('entra_dispositivos')): ?>
        <a href="<?= url('/entra/dispositivos') ?>" class="<?= str_starts_with($uriAtual, '/entra/dispositivos') ? 'active' : '' ?>">
            <i class="bi bi-laptop me-2"></i> Dispositivos (Intune)
        </a>
        <?php endif; ?>
        <?php if (PermissionService::temAcesso('entra_perfis_configuracao')): ?>
        <a href="<?= url('/entra/perfis-configuracao') ?>" class="<?= str_starts_with($uriAtual, '/entra/perfis-configuracao') ? 'active' : '' ?>">
            <i class="bi bi-sliders me-2"></i> Perfis de Configuração
        </a>
        <?php endif; ?>
        <?php if (PermissionService::temAcesso('entra_configuracao')): ?>
        <a href="<?= url('/entra/configuracao') ?>" class="<?= str_starts_with($uriAtual, '/entra/configuracao') ? 'active' : '' ?>">
            <i class="bi bi-gear me-2"></i> Configuração
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
    $temSamba = PermissionService::temAcesso('samba_dashboard')
        || PermissionService::temAcesso('samba_arquivos')
        || PermissionService::temAcesso('deploy')
        || PermissionService::temAcesso('samba_compartilhamentos')
        || PermissionService::temAcesso('samba_config')
        || PermissionService::temAcesso('samba_diagnostico')
        || PermissionService::temAcesso('samba_grupos')
        || PermissionService::temAcesso('samba_lixeira')
        || PermissionService::temAcesso('samba_monitor')
        || PermissionService::temAcesso('samba_usuarios');
    ?>
    <?php if ($temSamba): ?>
    <button class="menu-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuSamba"
            aria-expanded="<?= $abrirSamba ? 'true' : 'false' ?>">
        <span><i class="bi bi-hdd-network-fill me-2"></i>Samba</span>
        <i class="bi bi-chevron-right chevron"></i>
    </button>
    <div class="collapse <?= $abrirSamba ? 'show' : '' ?>" id="menuSamba">
        <?php if (PermissionService::temAcesso('samba_dashboard')): ?>
        <a href="<?= url('/samba/dashboard') ?>" class="<?= $uriAtual === '/samba/dashboard' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2 me-2"></i> Dashboard Samba
        </a>
        <?php endif; ?>

        <?php if (PermissionService::temAcesso('samba_arquivos')): ?>
        <a href="<?= url('/samba/arquivos') ?>" class="<?= $uriAtual === '/samba/arquivos' ? 'active' : '' ?>">
            <i class="bi bi-folder2-open me-2"></i> Arquivos
        </a>
        <?php endif; ?>

        <?php if (PermissionService::temAcesso('deploy')): ?>
        <a href="<?= url('/deploy') ?>" class="<?= $uriAtual === '/deploy' ? 'active' : '' ?>">
            <i class="bi bi-rocket-takeoff me-2"></i> Central de Configurações
        </a>
        <?php endif; ?>

        <?php if (PermissionService::temAcesso('samba_compartilhamentos')): ?>
        <a href="<?= url('/samba/compartilhamentos') ?>" class="<?= $uriAtual === '/samba/compartilhamentos' ? 'active' : '' ?>">
            <i class="bi bi-folder2-open me-2"></i> Compartilhamentos
        </a>
        <?php endif; ?>

        <?php if (PermissionService::temAcesso('samba_config')): ?>
        <a href="<?= url('/samba/configuracao') ?>" class="<?= $uriAtual === '/samba/configuracao' ? 'active' : '' ?>">
            <i class="bi bi-sliders me-2"></i> Config. Global
        </a>
        <?php endif; ?>

        <?php if (PermissionService::temAcesso('samba_diagnostico')): ?>
        <a href="<?= url('/samba/diagnostico') ?>" class="<?= $uriAtual === '/samba/diagnostico' ? 'active' : '' ?>">
            <i class="bi bi-activity me-2"></i> Diagnóstico
        </a>
        <?php endif; ?>

        <?php if (PermissionService::temAcesso('samba_grupos')): ?>
        <a href="<?= url('/samba/grupos') ?>" class="<?= $uriAtual === '/samba/grupos' ? 'active' : '' ?>">
            <i class="bi bi-collection me-2"></i> Grupos
        </a>
        <?php endif; ?>

        <?php if (PermissionService::temAcesso('samba_lixeira')): ?>
        <a href="<?= url('/samba/lixeira') ?>" class="<?= $uriAtual === '/samba/lixeira' ? 'active' : '' ?>">
            <i class="bi bi-trash3 me-2"></i> Lixeira Administrativa
        </a>
        <?php endif; ?>

        <?php if (PermissionService::temAcesso('samba_monitor')): ?>
        <a href="<?= url('/samba/monitor') ?>" class="<?= $uriAtual === '/samba/monitor' ? 'active' : '' ?>">
            <i class="bi bi-display me-2"></i> Monitor
        </a>
        <?php endif; ?>

        <?php if (PermissionService::temAcesso('samba_usuarios')): ?>
        <a href="<?= url('/samba/usuarios') ?>" class="<?= $uriAtual === '/samba/usuarios' ? 'active' : '' ?>">
            <i class="bi bi-people me-2"></i> Usuários
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
    $temSeguranca = PermissionService::temAcesso('seguranca_antivirus');
    ?>
    <?php if ($temSeguranca): ?>
    <button class="menu-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuSeguranca"
            aria-expanded="<?= $abrirSeguranca ? 'true' : 'false' ?>">
        <span><i class="bi bi-shield-lock me-2"></i>Segurança</span>
        <i class="bi bi-chevron-right chevron"></i>
    </button>
    <div class="collapse <?= $abrirSeguranca ? 'show' : '' ?>" id="menuSeguranca">
        <?php if (PermissionService::temAcesso('seguranca_antivirus')): ?>
        <a href="<?= url('/seguranca/antivirus') ?>" class="<?= str_starts_with($uriAtual, '/seguranca/antivirus') ? 'active' : '' ?>">
            <i class="bi bi-virus me-2"></i> Antivírus
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (PermissionService::ehAdmin()): ?>
    <button class="menu-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuSistema"
            aria-expanded="<?= $abrirSistema ? 'true' : 'false' ?>">
        <span><i class="bi bi-gear-wide-connected me-2"></i>Sistema</span>
        <i class="bi bi-chevron-right chevron"></i>
    </button>
    <div class="collapse <?= $abrirSistema ? 'show' : '' ?>" id="menuSistema">
        <a href="<?= url('/administracao/atualizacoes') ?>" class="<?= str_starts_with($uriAtual, '/administracao/atualizacoes') ? 'active' : '' ?>">
            <i class="bi bi-cloud-arrow-down me-2"></i> Atualizações do Sistema
        </a>
        <a href="<?= url('/auditoria') ?>" class="<?= $uriAtual === '/auditoria' ? 'active' : '' ?>">
            <i class="bi bi-journal-text me-2"></i> Auditoria
        </a>
        <a href="<?= url('/administracao/configuracoes') ?>" class="<?= str_starts_with($uriAtual, '/administracao/configuracoes') ? 'active' : '' ?>">
            <i class="bi bi-shield-lock me-2"></i> Configurações (Backup/Restauração)
        </a>
        <a href="<?= url('/administracao/empresa') ?>" class="<?= str_starts_with($uriAtual, '/administracao/empresa') ? 'active' : '' ?>">
            <i class="bi bi-building me-2"></i> Dados da Empresa
        </a>
        <a href="<?= url('/infraestrutura/dependencias') ?>" class="<?= str_starts_with($uriAtual, '/infraestrutura/dependencias') ? 'active' : '' ?>">
            <i class="bi bi-clipboard2-check me-2"></i> Dependências
        </a>
        <a href="<?= url('/administracao/integracoes') ?>" class="<?= str_starts_with($uriAtual, '/administracao/integracoes') ? 'active' : '' ?>">
            <i class="bi bi-plug me-2"></i> Integrações
        </a>

        <button class="menu-toggle menu-toggle-sub" type="button" data-bs-toggle="collapse" data-bs-target="#menuSistemaModulos"
                aria-expanded="<?= $abrirSistemaModulos ? 'true' : 'false' ?>">
            <span><i class="bi bi-grid-3x3-gap-fill me-2"></i>Módulos</span>
            <i class="bi bi-chevron-right chevron"></i>
        </button>
        <div class="collapse menu-sub <?= $abrirSistemaModulos ? 'show' : '' ?>" id="menuSistemaModulos">
            <a href="<?= url('/administracao/modulos') ?>" class="<?= str_starts_with($uriAtual, '/administracao/modulos') ? 'active' : '' ?>">
                Habilitar/Desabilitar
            </a>
        </div>

        <a href="<?= url('/administracao/usuarios') ?>" class="<?= str_starts_with($uriAtual, '/administracao/usuarios') ? 'active' : '' ?>">
            <i class="bi bi-person-gear me-2"></i> Usuários do Sistema
        </a>

        <a href="<?= url('/administracao/grupos') ?>" class="<?= str_starts_with($uriAtual, '/administracao/grupos') ? 'active' : '' ?>">
            <i class="bi bi-people-fill me-2"></i> Grupos
        </a>
    </div>
    <?php endif; ?>

    <?php if (PermissionService::temAcesso('ssh_conexoes')): ?>
    <button class="menu-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuSsh"
            aria-expanded="<?= $abrirSsh ? 'true' : 'false' ?>">
        <span><i class="bi bi-hdd-network me-2"></i>SSH</span>
        <i class="bi bi-chevron-right chevron"></i>
    </button>
    <div class="collapse <?= $abrirSsh ? 'show' : '' ?>" id="menuSsh">
        <a href="<?= url('/ssh/conexoes') ?>" class="<?= str_starts_with($uriAtual, '/ssh') ? 'active' : '' ?>">
            <i class="bi bi-terminal me-2"></i> Conexões
        </a>
    </div>
    <?php endif; ?>

    <?php
    $temVpn = PermissionService::temAcesso('vpn_dashboard')
        || PermissionService::temAcesso('vpn_wireguard_servidor')
        || PermissionService::temAcesso('vpn_wireguard_peers')
        || PermissionService::temAcesso('vpn_wireguard_trafego')
        || PermissionService::temAcesso('vpn_wireguard_saida')
        || PermissionService::temAcesso('vpn_openvpn_servidor')
        || PermissionService::temAcesso('vpn_openvpn_clientes')
        || PermissionService::temAcesso('vpn_openvpn_trafego')
        || PermissionService::temAcesso('vpn_openvpn_saida')
        || PermissionService::temAcesso('vpn_ikev2_servidor')
        || PermissionService::temAcesso('vpn_ikev2_clientes')
        || PermissionService::temAcesso('vpn_ikev2_trafego')
        || PermissionService::temAcesso('vpn_ikev2_saida');
    ?>
    <?php if ($temVpn): ?>
    <button class="menu-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuVpn"
            aria-expanded="<?= $abrirVpn ? 'true' : 'false' ?>">
        <span><i class="bi bi-shield-shaded me-2"></i>VPN</span>
        <i class="bi bi-chevron-right chevron"></i>
    </button>
    <div class="collapse <?= $abrirVpn ? 'show' : '' ?>" id="menuVpn">
        <?php if (PermissionService::temAcesso('vpn_dashboard')): ?>
        <a href="<?= url('/vpn') ?>" class="<?= $uriAtual === '/vpn' ? 'active' : '' ?>">
            <i class="bi bi-speedometer me-2"></i> Dashboard
        </a>
        <?php endif; ?>

        <?php if (PermissionService::temAcesso('vpn_wireguard_servidor') || PermissionService::temAcesso('vpn_wireguard_peers') || PermissionService::temAcesso('vpn_wireguard_trafego') || PermissionService::temAcesso('vpn_wireguard_saida')): ?>
        <button class="menu-toggle menu-toggle-sub" type="button" data-bs-toggle="collapse" data-bs-target="#menuVpnWireguard"
                aria-expanded="<?= $abrirVpnWireguard ? 'true' : 'false' ?>">
            <span><i class="bi bi-lock me-2"></i>WireGuard</span>
            <i class="bi bi-chevron-right chevron"></i>
        </button>
        <div class="collapse menu-sub <?= $abrirVpnWireguard ? 'show' : '' ?>" id="menuVpnWireguard">
            <?php if (PermissionService::temAcesso('vpn_wireguard_servidor')): ?>
            <a href="<?= url('/vpn/wireguard/servidor') ?>" class="<?= $uriAtual === '/vpn/wireguard/servidor' ? 'active' : '' ?>">
                <i class="bi bi-hdd-rack me-2"></i> Servidor
            </a>
            <?php endif; ?>
            <?php if (PermissionService::temAcesso('vpn_wireguard_peers')): ?>
            <a href="<?= url('/vpn/wireguard/peers') ?>" class="<?= $uriAtual === '/vpn/wireguard/peers' ? 'active' : '' ?>">
                <i class="bi bi-people me-2"></i> Peers
            </a>
            <?php endif; ?>
            <?php if (PermissionService::temAcesso('vpn_wireguard_trafego')): ?>
            <a href="<?= url('/vpn/wireguard/trafego') ?>" class="<?= $uriAtual === '/vpn/wireguard/trafego' ? 'active' : '' ?>">
                <i class="bi bi-bar-chart-line me-2"></i> Tráfego
            </a>
            <?php endif; ?>
            <?php if (PermissionService::temAcesso('vpn_wireguard_saida')): ?>
            <a href="<?= url('/vpn/wireguard/saida') ?>" class="<?= str_starts_with($uriAtual, '/vpn/wireguard/saida') ? 'active' : '' ?>">
                <i class="bi bi-box-arrow-up-right me-2"></i> Saída (cliente)
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (PermissionService::temAcesso('vpn_openvpn_servidor') || PermissionService::temAcesso('vpn_openvpn_clientes') || PermissionService::temAcesso('vpn_openvpn_trafego') || PermissionService::temAcesso('vpn_openvpn_saida')): ?>
        <button class="menu-toggle menu-toggle-sub" type="button" data-bs-toggle="collapse" data-bs-target="#menuVpnOpenvpn"
                aria-expanded="<?= $abrirVpnOpenvpn ? 'true' : 'false' ?>">
            <span><i class="bi bi-shield-lock me-2"></i>OpenVPN</span>
            <i class="bi bi-chevron-right chevron"></i>
        </button>
        <div class="collapse menu-sub <?= $abrirVpnOpenvpn ? 'show' : '' ?>" id="menuVpnOpenvpn">
            <?php if (PermissionService::temAcesso('vpn_openvpn_servidor')): ?>
            <a href="<?= url('/vpn/openvpn/servidor') ?>" class="<?= $uriAtual === '/vpn/openvpn/servidor' ? 'active' : '' ?>">
                <i class="bi bi-hdd-rack me-2"></i> Servidor
            </a>
            <?php endif; ?>
            <?php if (PermissionService::temAcesso('vpn_openvpn_clientes')): ?>
            <a href="<?= url('/vpn/openvpn/clientes') ?>" class="<?= $uriAtual === '/vpn/openvpn/clientes' ? 'active' : '' ?>">
                <i class="bi bi-people me-2"></i> Clientes
            </a>
            <?php endif; ?>
            <?php if (PermissionService::temAcesso('vpn_openvpn_trafego')): ?>
            <a href="<?= url('/vpn/openvpn/trafego') ?>" class="<?= $uriAtual === '/vpn/openvpn/trafego' ? 'active' : '' ?>">
                <i class="bi bi-bar-chart-line me-2"></i> Tráfego
            </a>
            <?php endif; ?>
            <?php if (PermissionService::temAcesso('vpn_openvpn_saida')): ?>
            <a href="<?= url('/vpn/openvpn/saida') ?>" class="<?= str_starts_with($uriAtual, '/vpn/openvpn/saida') ? 'active' : '' ?>">
                <i class="bi bi-box-arrow-up-right me-2"></i> Saída (cliente)
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (PermissionService::temAcesso('vpn_ikev2_servidor') || PermissionService::temAcesso('vpn_ikev2_clientes') || PermissionService::temAcesso('vpn_ikev2_trafego') || PermissionService::temAcesso('vpn_ikev2_saida')): ?>
        <button class="menu-toggle menu-toggle-sub" type="button" data-bs-toggle="collapse" data-bs-target="#menuVpnIkev2"
                aria-expanded="<?= $abrirVpnIkev2 ? 'true' : 'false' ?>">
            <span><i class="bi bi-globe-americas me-2"></i>IKEv2 / IPsec</span>
            <i class="bi bi-chevron-right chevron"></i>
        </button>
        <div class="collapse menu-sub <?= $abrirVpnIkev2 ? 'show' : '' ?>" id="menuVpnIkev2">
            <?php if (PermissionService::temAcesso('vpn_ikev2_servidor')): ?>
            <a href="<?= url('/vpn/ikev2/servidor') ?>" class="<?= $uriAtual === '/vpn/ikev2/servidor' ? 'active' : '' ?>">
                <i class="bi bi-hdd-rack me-2"></i> Servidor
            </a>
            <?php endif; ?>
            <?php if (PermissionService::temAcesso('vpn_ikev2_clientes')): ?>
            <a href="<?= url('/vpn/ikev2/clientes') ?>" class="<?= $uriAtual === '/vpn/ikev2/clientes' ? 'active' : '' ?>">
                <i class="bi bi-people me-2"></i> Clientes
            </a>
            <?php endif; ?>
            <?php if (PermissionService::temAcesso('vpn_ikev2_trafego')): ?>
            <a href="<?= url('/vpn/ikev2/trafego') ?>" class="<?= $uriAtual === '/vpn/ikev2/trafego' ? 'active' : '' ?>">
                <i class="bi bi-bar-chart-line me-2"></i> Tráfego
            </a>
            <?php endif; ?>
            <?php if (PermissionService::temAcesso('vpn_ikev2_saida')): ?>
            <a href="<?= url('/vpn/ikev2/saida') ?>" class="<?= str_starts_with($uriAtual, '/vpn/ikev2/saida') ? 'active' : '' ?>">
                <i class="bi bi-box-arrow-up-right me-2"></i> Saída (cliente)
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
    $temWhatsapp = PermissionService::temAcesso('whatsapp_atendimentos')
        || PermissionService::temAcesso('whatsapp_fila')
        || PermissionService::temAcesso('whatsapp_chatbot')
        || PermissionService::temAcesso('whatsapp_setores')
        || PermissionService::temAcesso('whatsapp_estatisticas')
        || PermissionService::temAcesso('whatsapp_configuracoes');
    ?>
    <?php
    $wppAguardando = 0;
    if (PermissionService::temAcesso('whatsapp_atendimentos') && isset($_SESSION['usuario']['id'])) {
        $wppAguardando = (new WhatsAppAtendimentoService())->contarAguardandoResposta((int)$_SESSION['usuario']['id']);
    }
    ?>
    <?php if ($temWhatsapp): ?>
    <button class="menu-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuWhatsapp"
            aria-expanded="<?= $abrirWhatsapp ? 'true' : 'false' ?>">
        <span><i class="bi bi-whatsapp me-2"></i>WhatsApp</span>
        <i class="bi bi-chevron-right chevron"></i>
    </button>
    <div class="collapse <?= $abrirWhatsapp ? 'show' : '' ?>" id="menuWhatsapp">
        <?php if (PermissionService::temAcesso('whatsapp_atendimentos')): ?>
        <a href="<?= url('/whatsapp/atendimentos') ?>" class="rd-menu-item-badge <?= str_starts_with($uriAtual, '/whatsapp/atendimentos') ? 'active' : '' ?>">
            <span><i class="bi bi-chat-dots me-2"></i> Atendimentos</span>
            <span class="rd-menu-badge" id="rdWppBadgeAtendimentos" style="<?= $wppAguardando > 0 ? '' : 'display:none' ?>"><?= $wppAguardando ?></span>
        </a>
        <?php endif; ?>
        <?php if (PermissionService::temAcesso('whatsapp_fila')): ?>
        <a href="<?= url('/whatsapp/fila') ?>" class="<?= $uriAtual === '/whatsapp/fila' ? 'active' : '' ?>">
            <i class="bi bi-hourglass-split me-2"></i> Fila
        </a>
        <?php endif; ?>
        <?php if (PermissionService::temAcesso('whatsapp_chatbot')): ?>
        <a href="<?= url('/whatsapp/chatbot') ?>" class="<?= $uriAtual === '/whatsapp/chatbot' ? 'active' : '' ?>">
            <i class="bi bi-robot me-2"></i> Chatbot
        </a>
        <?php endif; ?>
        <?php if (PermissionService::temAcesso('whatsapp_setores')): ?>
        <a href="<?= url('/whatsapp/setores') ?>" class="<?= $uriAtual === '/whatsapp/setores' ? 'active' : '' ?>">
            <i class="bi bi-diagram-3 me-2"></i> Setores
        </a>
        <?php endif; ?>
        <?php if (PermissionService::temAcesso('whatsapp_estatisticas')): ?>
        <a href="<?= url('/whatsapp/estatisticas') ?>" class="<?= $uriAtual === '/whatsapp/estatisticas' ? 'active' : '' ?>">
            <i class="bi bi-bar-chart-line me-2"></i> Estatísticas
        </a>
        <?php endif; ?>
        <?php if (PermissionService::temAcesso('whatsapp_configuracoes')): ?>
        <a href="<?= url('/whatsapp/configuracoes') ?>" class="<?= $uriAtual === '/whatsapp/configuracoes' ? 'active' : '' ?>">
            <i class="bi bi-gear me-2"></i> Configurações
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <a href="<?= url('/logout') ?>" class="mt-3">
        <i class="bi bi-box-arrow-right me-2"></i> Sair
    </a>
</div>

<div class="content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0"><?= htmlspecialchars($titulo) ?></h2>
            <small class="text-muted">RD Tecnologia</small>
        </div>

        <div class="d-flex align-items-center gap-2">
            <div class="text-end">
                <strong><?= htmlspecialchars($usuarioLogado['nome']) ?></strong><br>
                <small class="text-muted">Usuário logado</small>
            </div>
            <a href="<?= url('/perfil') ?>" class="btn btn-sm btn-outline-secondary" title="Meu perfil">
                <i class="bi bi-person-gear"></i>
            </a>
        </div>
    </div>

    <?= $conteudo ?? '' ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php if (PermissionService::temAcesso('whatsapp_atendimentos') && isset($_SESSION['usuario']['id'])): ?>
<script>
(function () {
    // Roda em toda tela do sistema (não só em Atendimentos) porque o
    // atendente pode estar em Fila, Chatbot etc. quando chega mensagem
    // nova -- o botão de liga/desliga fica só em Atendimentos, mas essa
    // checagem e o alerta sonoro valem pra qualquer página.
    const CHAVE_SOM = 'wppSomAtivo';
    const CHAVE_ULTIMA_MSG = 'wppUltimaMensagemId';

    function somAtivo() {
        return localStorage.getItem(CHAVE_SOM) === '1';
    }

    // Navegador só deixa um AudioContext tocar de verdade depois de uma
    // interação real do usuário na página (clique, tecla etc.) -- como
    // o alerta dispara sozinho (setInterval, sem gesto nenhum no
    // momento), criar um contexto novo a cada bip fica sempre "suspenso"
    // e mudo, sem erro nenhum avisando. Por isso criamos UM contexto só
    // por carregamento de página e destravamos ele no primeiro clique/
    // tecla/toque -- depois disso ele fica liberado pro resto da vida
    // dessa página (não sobrevive a navegar pra outra, cada página cria
    // o dela e destrava de novo, mas isso costuma acontecer em segundos
    // no uso normal do painel).
    let audioCtxWpp = null;
    async function obterAudioCtxWpp() {
        if (!audioCtxWpp) {
            const Ctor = window.AudioContext || window.webkitAudioContext;
            if (!Ctor) {
                return null;
            }
            audioCtxWpp = new Ctor();
        }
        if (audioCtxWpp.state === 'suspended') {
            // Espera o resume() de verdade terminar antes de devolver o
            // contexto -- ler o state logo em seguida, sem esperar,
            // ainda pega "suspended" no meio da transição.
            try {
                await audioCtxWpp.resume();
            } catch (e) {
                // bloqueado pela política de autoplay -- segue mudo mesmo
            }
        }
        return audioCtxWpp;
    }
    ['pointerdown', 'keydown'].forEach((evento) => {
        document.addEventListener(evento, () => { obterAudioCtxWpp(); }, { passive: true });
    });

    async function tocarBipWpp() {
        try {
            const ctx = await obterAudioCtxWpp();
            if (!ctx || ctx.state !== 'running') {
                return; // navegador não deixou destravar o áudio nessa página ainda
            }
            [880, 1108].forEach((freq, i) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.type = 'sine';
                osc.frequency.value = freq;
                const inicio = ctx.currentTime + i * 0.14;
                gain.gain.setValueAtTime(0.001, inicio);
                gain.gain.exponentialRampToValueAtTime(0.18, inicio + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.001, inicio + 0.28);
                osc.start(inicio);
                osc.stop(inicio + 0.3);
            });
        } catch (e) {
            // navegador sem suporte a Web Audio -- ignora, sem quebrar o resto
        }
    }

    // Exposto globalmente (não só "Wpp" no nome por acaso -- é o mesmo
    // motor de áudio compartilhado por qualquer módulo com fila/pendência,
    // Chamados incluso mais abaixo) pro botão liga/desliga de cada módulo
    // poder tocar um bip de teste na hora, dentro do próprio clique --
    // prova direta pro usuário (e pra gente) se o áudio funciona nesse
    // navegador, sem depender de esperar uma mensagem nova chegar.
    window.rdTocarBip = tocarBipWpp;

    // Guardado em sessionStorage (sobrevive a navegar entre páginas na
    // mesma aba, mas não a fechar/reabrir) pra não perder o "antes" só
    // porque o usuário trocou de tela entre uma checagem e outra.
    //
    // O bip usa o id da última mensagem recebida, não a contagem de
    // "aguardando resposta" -- essa contagem é por atendimento (0 ou 1),
    // então uma segunda mensagem numa conversa que já estava aguardando
    // resposta não muda o número e o alerta nunca dispararia de novo.
    // O id da mensagem, por outro lado, sempre cresce a cada mensagem
    // nova, então dá pra detectar toda chegada, mesmo repetida na mesma
    // conversa.
    let ultimaMensagemId = sessionStorage.getItem(CHAVE_ULTIMA_MSG);
    ultimaMensagemId = ultimaMensagemId === null ? null : parseInt(ultimaMensagemId, 10);

    const badgeMenu = document.getElementById('rdWppBadgeAtendimentos');

    async function verificarNovasMensagens() {
        try {
            const resp = await fetch('<?= url('/whatsapp/atendimentos/contador') ?>');
            const dados = await resp.json();
            if (!dados.success) {
                return;
            }

            if (ultimaMensagemId !== null && dados.ultimaMensagemId > ultimaMensagemId && somAtivo()) {
                tocarBipWpp();
            }
            ultimaMensagemId = dados.ultimaMensagemId;
            sessionStorage.setItem(CHAVE_ULTIMA_MSG, String(ultimaMensagemId));

            if (badgeMenu) {
                badgeMenu.textContent = dados.aguardando;
                badgeMenu.style.display = dados.aguardando > 0 ? '' : 'none';
            }
        } catch (e) {
            // rede instável -- tenta de novo no próximo ciclo
        }
    }

    verificarNovasMensagens();
    setInterval(verificarNovasMensagens, 15000);
})();
</script>
<?php endif; ?>

<?php if (PermissionService::temAcesso('chamados_atendimentos') && isset($_SESSION['usuario']['id'])): ?>
<script>
(function () {
    // Mesmo esquema do WhatsApp acima (contador + som), reaproveitando o
    // motor de áudio compartilhado (window.rdTocarBip) -- um contexto só,
    // já destravado pelo primeiro clique/tecla, tocando pros dois módulos.
    const CHAVE_SOM = 'chamadosSomAtivo';
    const CHAVE_ULTIMO_ID = 'chamadosUltimoId';

    function somAtivo() {
        return localStorage.getItem(CHAVE_SOM) === '1';
    }

    let ultimoId = sessionStorage.getItem(CHAVE_ULTIMO_ID);
    ultimoId = ultimoId === null ? null : parseInt(ultimoId, 10);

    const badgeMenu = document.getElementById('rdChamadosBadgeAtendimentos');

    async function verificarChamados() {
        try {
            const resp = await fetch('<?= url('/chamados/atendimentos/contador') ?>');
            const dados = await resp.json();
            if (!dados.success) {
                return;
            }

            if (ultimoId !== null && dados.ultimoId > ultimoId && somAtivo() && typeof window.rdTocarBip === 'function') {
                window.rdTocarBip();
            }
            ultimoId = dados.ultimoId;
            sessionStorage.setItem(CHAVE_ULTIMO_ID, String(ultimoId));

            if (badgeMenu) {
                badgeMenu.textContent = dados.aguardando;
                badgeMenu.style.display = dados.aguardando > 0 ? '' : 'none';
            }
        } catch (e) {
            // rede instável -- tenta de novo no próximo ciclo
        }
    }

    verificarChamados();
    setInterval(verificarChamados, 15000);
})();
</script>
<?php endif; ?>

<?php if (PermissionService::temAcesso('chat_conversas') && isset($_SESSION['usuario']['id'])): ?>
<script>
(function () {
    // Mesmo esquema do WhatsApp/Chamados acima -- reaproveita o motor de
    // áudio compartilhado (window.rdTocarBip) e o mesmo raciocínio de id
    // crescente (detecta mensagem nova mesmo repetida na mesma conversa).
    const CHAVE_SOM = 'chatSomAtivo';
    const CHAVE_ULTIMO_ID = 'chatUltimoId';

    function somAtivo() {
        return localStorage.getItem(CHAVE_SOM) === '1';
    }

    let ultimoId = sessionStorage.getItem(CHAVE_ULTIMO_ID);
    ultimoId = ultimoId === null ? null : parseInt(ultimoId, 10);

    const badgeMenu = document.getElementById('rdChatBadge');

    async function verificarChat() {
        try {
            const resp = await fetch('<?= url('/chat/contador') ?>');
            const dados = await resp.json();
            if (!dados.success) {
                return;
            }

            if (ultimoId !== null && dados.ultimaMensagemId > ultimoId && somAtivo() && typeof window.rdTocarBip === 'function') {
                window.rdTocarBip();
            }
            ultimoId = dados.ultimaMensagemId;
            sessionStorage.setItem(CHAVE_ULTIMO_ID, String(ultimoId));

            if (badgeMenu) {
                badgeMenu.textContent = dados.naoLidas;
                badgeMenu.style.display = dados.naoLidas > 0 ? '' : 'none';
            }
        } catch (e) {
            // rede instável -- tenta de novo no próximo ciclo
        }
    }

    verificarChat();
    setInterval(verificarChat, 15000);

    // Fase 2 -- acelerador via WebSocket (chat-bridge), puramente em
    // cima do polling acima: se o bridge não estiver instalado, ou o
    // proxy do Apache não estiver ativo, esta conexão nunca abre e nada
    // muda -- o polling de 15s continua entregando tudo normalmente,
    // exatamente como já funcionava antes da Fase 2 existir.
    async function conectarSocketChat() {
        try {
            const resp = await fetch('<?= url('/chat/socket-token') ?>');
            const dados = await resp.json();
            if (!dados.success) {
                return;
            }

            const protocolo = location.protocol === 'https:' ? 'wss:' : 'ws:';
            const ws = new WebSocket(protocolo + '//' + location.host + '/chat-ws?token=' + encodeURIComponent(dados.token));

            ws.addEventListener('message', function () {
                verificarChat();
            });

            ws.addEventListener('close', function () {
                setTimeout(conectarSocketChat, 10000);
            });
        } catch (e) {
            // sem bridge/proxy disponível -- o polling acima cobre normalmente
        }
    }

    conectarSocketChat();
})();
</script>
<?php endif; ?>

<?php if (ModuloCatalogo::grupoHabilitado('Avisos') && isset($_SESSION['usuario']['id'])): ?>
<script>
(function () {
    const badgeAvisos = document.getElementById('rdAvisosBadge');

    async function verificarAvisos() {
        try {
            const resp = await fetch('<?= url('/avisos/contador') ?>');
            const dados = await resp.json();
            if (!dados.success || !badgeAvisos) return;

            badgeAvisos.textContent = dados.nao_lidos;
            badgeAvisos.style.display = dados.nao_lidos > 0 ? '' : 'none';
        } catch (e) {
            // rede instável -- tenta de novo no próximo ciclo
        }
    }

    setInterval(verificarAvisos, 30000);
})();
</script>
<?php endif; ?>

</body>
</html>
