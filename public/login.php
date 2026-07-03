<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$login = $_POST['login'] ?? '';
$senha = $_POST['senha'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE login = ? AND ativo = 1 LIMIT 1");
$stmt->execute([$login]);
$usuario = $stmt->fetch();

if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
    $_SESSION['usuario'] = [
        'id' => $usuario['id'],
        'nome' => $usuario['nome'],
        'login' => $usuario['login'],
        'perfil' => $usuario['perfil']
    ];

    header('Location: dashboard.php');
    exit;
}

header('Location: index.php?erro=1');
exit;
