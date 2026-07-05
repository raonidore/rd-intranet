<?php

use App\Services\PermissionService;

$usuarioLogado = $_SESSION['usuario'] ?? ['nome' => 'Usuário'];
$titulo = $titulo ?? 'RD Intranet';
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

    <a href="<?= url('/dashboard') ?>">
        <i class="bi bi-speedometer2 me-2"></i> Dashboard
    </a>

    <div class="menu-section">Samba</div>

    <?php if (PermissionService::temAcesso('samba_dashboard')): ?>
    <a href="<?= url('/samba/dashboard') ?>">
        <i class="bi bi-speedometer2 me-2"></i> Dashboard Samba
    </a>
    <?php endif; ?>

    <?php if (PermissionService::temAcesso('samba_usuarios')): ?>
    <a href="<?= url('/samba/usuarios') ?>">
        <i class="bi bi-people me-2"></i> Usuários
    </a>
    <?php endif; ?>

    <?php if (PermissionService::temAcesso('samba_grupos')): ?>
    <a href="<?= url('/samba/grupos') ?>">
        <i class="bi bi-collection me-2"></i> Grupos
    </a>
    <?php endif; ?>

    <?php if (PermissionService::temAcesso('samba_compartilhamentos')): ?>
    <a href="<?= url('/samba/compartilhamentos') ?>">
        <i class="bi bi-folder2-open me-2"></i> Compartilhamentos
    </a>
    <?php endif; ?>

    <?php if (PermissionService::temAcesso('samba_monitor')): ?>
    <a href="<?= url('/samba/monitor') ?>">
        <i class="bi bi-display me-2"></i> Monitor
    </a>
    <?php endif; ?>

    <?php if (PermissionService::temAcesso('samba_arquivos')): ?>
    <a href="<?= url('/samba/arquivos') ?>">
        <i class="bi bi-folder2-open me-2"></i> Arquivos
    </a>
    <?php endif; ?>

    <?php if (PermissionService::temAcesso('samba_diagnostico')): ?>
    <a href="<?= url('/samba/diagnostico') ?>">
        <i class="bi bi-activity me-2"></i> Diagnóstico
    </a>
    <?php endif; ?>

    <?php if (PermissionService::temAcesso('samba_lixeira')): ?>
    <a href="<?= url('/samba/lixeira') ?>">
        <i class="bi bi-trash3 me-2"></i> Lixeira Administrativa
    </a>
    <?php endif; ?>

    <?php if (PermissionService::temAcesso('deploy')): ?>
    <a href="<?= url('/deploy') ?>">
        <i class="bi bi-rocket-takeoff me-2"></i> Central de Configurações
    </a>
    <?php endif; ?>

    <?php if (PermissionService::temAcesso('samba_config')): ?>
    <a href="<?= url('/samba/configuracao') ?>">
        <i class="bi bi-sliders me-2"></i> Config. Global Samba
    </a>
    <?php endif; ?>

    <?php if (
        PermissionService::temAcesso('apache_dashboard') ||
        PermissionService::temAcesso('apache_sites') ||
        PermissionService::temAcesso('apache_modulos') ||
        PermissionService::temAcesso('apache_config')
    ): ?>
    <div class="menu-section">Apache</div>

    <?php if (PermissionService::temAcesso('apache_dashboard')): ?>
    <a href="<?= url('/apache/dashboard') ?>">
        <i class="bi bi-speedometer2 me-2"></i> Dashboard Apache
    </a>
    <?php endif; ?>

    <?php if (PermissionService::temAcesso('apache_sites')): ?>
    <a href="<?= url('/apache/sites') ?>">
        <i class="bi bi-globe me-2"></i> Sites
    </a>
    <?php endif; ?>

    <?php if (PermissionService::temAcesso('apache_modulos')): ?>
    <a href="<?= url('/apache/modulos') ?>">
        <i class="bi bi-puzzle me-2"></i> Módulos
    </a>
    <?php endif; ?>

    <?php if (PermissionService::temAcesso('apache_config')): ?>
    <a href="<?= url('/apache/configuracao') ?>">
        <i class="bi bi-sliders me-2"></i> Config. Global Apache
    </a>
    <?php endif; ?>
    <?php endif; ?>

    <div class="menu-section">Infraestrutura</div>

    <?php if (PermissionService::temAcesso('infra_servidor')): ?>
    <a href="<?= url('/infraestrutura/servidor') ?>">
        <i class="bi bi-hdd-rack me-2"></i> Servidor
    </a>
    <?php endif; ?>

    <?php if (PermissionService::temAcesso('infra_servicos')): ?>
    <a href="<?= url('/infraestrutura/servicos') ?>">
        <i class="bi bi-hdd-network me-2"></i> Serviços
    </a>
    <?php endif; ?>

    <a href="#">
        <i class="bi bi-diagram-3 me-2"></i> VPN
    </a>

    <a href="#">
        <i class="bi bi-database-check me-2"></i> Backup
    </a>

    <div class="menu-section">Segurança</div>

    <?php if (PermissionService::temAcesso('auditoria')): ?>
    <a href="<?= url('/auditoria') ?>">
        <i class="bi bi-journal-text me-2"></i> Auditoria
    </a>
    <?php endif; ?>

    <?php if (PermissionService::ehAdmin()): ?>
    <a href="<?= url('/administracao/usuarios') ?>">
        <i class="bi bi-person-gear me-2"></i> Usuários do Sistema
    </a>
    <?php endif; ?>

    <div class="menu-section">Sessão</div>

    <a href="<?= url('/logout') ?>">
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
