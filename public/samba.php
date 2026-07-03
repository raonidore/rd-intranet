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
    <title>Módulo Samba - RD Intranet</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="sidebar">
    <h2>RD Intranet</h2>

    <a href="dashboard.php">Dashboard</a>
    <a href="samba.php">Módulo Samba</a>
    <a href="samba_usuarios.php">Usuários Samba</a>
    <a href="logout.php">Sair</a>
</div>

<div class="content">
    <h1>Módulo Samba</h1>

    <div class="card">
        <h3>Usuários</h3>
        <p>Criar, listar e administrar usuários Samba.</p>
        <a href="samba_usuarios.php">Gerenciar usuários</a>
    </div>

    <div class="card">
        <h3>Compartilhamentos</h3>
        <p>TI, Financeiro e Cobrança.</p>
    </div>

    <div class="card">
        <h3>Políticas de segurança</h3>
        <p>Bloqueio de arquivos executáveis em Financeiro e Cobrança.</p>
    </div>
</div>

</body>
</html>
