<?php

use App\Services\PermissionService;

$usuarioLogado = $_SESSION['usuario'] ?? ['nome' => 'Usuário'];
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
$abrirInfraestrutura = $rdSecaoAtiva(['/infraestrutura']);
$abrirSamba = $rdSecaoAtiva(['/samba', '/deploy']);
$abrirSeguranca = $rdSecaoAtiva(['/auditoria', '/administracao']);

$abrirHardware = $rdSecaoAtiva(['/infraestrutura/hardware']);
$abrirRede = $rdSecaoAtiva(['/infraestrutura/rede', '/infraestrutura/servidor/rede']);
$abrirInfraServicos = $rdSecaoAtiva(['/infraestrutura/servicos']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($titulo) ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('/assets/css/rd-ui.css') ?>">

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
    <div class="p-4">
        <h4 class="mb-0">RD Intranet</h4>
        <small class="text-secondary">Painel Administrativo</small>
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

    <?php
    $temInfra = PermissionService::temAcesso('infra_servidor')
        || PermissionService::temAcesso('infra_hardware')
        || PermissionService::temAcesso('infra_rede')
        || PermissionService::temAcesso('infra_servicos')
        || PermissionService::temAcesso('infra_cron')
        || PermissionService::temAcesso('infra_iptables')
        || PermissionService::temAcesso('infra_certificado')
        || PermissionService::temAcesso('infra_dependencias');
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

        <?php if (PermissionService::temAcesso('infra_dependencias')): ?>
        <a href="<?= url('/infraestrutura/dependencias') ?>" class="<?= str_starts_with($uriAtual, '/infraestrutura/dependencias') ? 'active' : '' ?>">
            <i class="bi bi-clipboard2-check me-2"></i> Dependências
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

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

    <?php
    $temSeguranca = PermissionService::temAcesso('auditoria') || PermissionService::ehAdmin();
    ?>
    <?php if ($temSeguranca): ?>
    <button class="menu-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#menuSeguranca"
            aria-expanded="<?= $abrirSeguranca ? 'true' : 'false' ?>">
        <span><i class="bi bi-shield-lock me-2"></i>Segurança</span>
        <i class="bi bi-chevron-right chevron"></i>
    </button>
    <div class="collapse <?= $abrirSeguranca ? 'show' : '' ?>" id="menuSeguranca">
        <?php if (PermissionService::temAcesso('auditoria')): ?>
        <a href="<?= url('/auditoria') ?>" class="<?= $uriAtual === '/auditoria' ? 'active' : '' ?>">
            <i class="bi bi-journal-text me-2"></i> Auditoria
        </a>
        <?php endif; ?>

        <?php if (PermissionService::ehAdmin()): ?>
        <a href="<?= url('/administracao/usuarios') ?>" class="<?= str_starts_with($uriAtual, '/administracao') ? 'active' : '' ?>">
            <i class="bi bi-person-gear me-2"></i> Usuários do Sistema
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

        <div class="text-end">
            <strong><?= htmlspecialchars($usuarioLogado['nome']) ?></strong><br>
            <small class="text-muted">Usuário logado</small>
        </div>
    </div>

    <?= $conteudo ?? '' ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
