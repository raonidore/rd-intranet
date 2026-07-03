<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit;
}

$usuario = $_SESSION['usuario'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>RD Intranet</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="sidebar">
    <h2>RD Intranet</h2>

    <a href="dashboard.php">Dashboard</a>
    <a href="samba.php">Módulo Samba</a>
    <a href="#">Servidores</a>
    <a href="#">VPN</a>
    <a href="#">Backups</a>
    <a href="#">Monitoramento</a>
    <a href="logout.php">Sair</a>
</div>

<div class="content">
    <div class="topbar">
        <h1>Dashboard</h1>
        <p>Bem-vindo, <strong><?= htmlspecialchars($usuario['nome']) ?></strong></p>
    </div>

    <div class="card">
        <h3>Módulo Samba</h3>
        <p>Gerenciamento de usuários e compartilhamentos do servidor de arquivos.</p>
        <a href="samba.php">Acessar módulo</a>
    </div>

    <div class="card">
        <h3>Status do ambiente</h3>
        <p>Samba, Apache e MariaDB instalados e operacionais.</p>
    </div>
</div>

</body>
</html>
