<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

$nome = trim($_POST['nome'] ?? '');
$login = strtolower(trim($_POST['login'] ?? ''));
$grupo = $_POST['grupo'] ?? '';
$ssh = $_POST['ssh'] ?? 'nao';
$senha = $_POST['senha'] ?? '';

$cmd = sprintf(
    "sudo /opt/rdtecnologia/scripts/cria_usuario_web.sh %s %s %s %s %s 2>&1",
    escapeshellarg($nome),
    escapeshellarg($login),
    escapeshellarg($grupo),
    escapeshellarg($ssh),
    escapeshellarg($senha)
);

exec($cmd, $saida, $retorno);

if ($retorno === 0) {
    $uid = trim(shell_exec("id -u " . escapeshellarg($login)));

    $stmt = $pdo->prepare("
        INSERT INTO samba_usuarios 
        (nome, login, departamento, ssh, uid_linux, status)
        VALUES (?, ?, ?, ?, ?, 'ativo')
    ");

    $stmt->execute([
        $nome,
        $login,
        $grupo,
        $ssh === 'sim' ? 1 : 0,
        $uid ?: null
    ]);

    $msg = urlencode("Usuário {$login} criado com sucesso.");
} else {
    $msg = urlencode("Erro ao criar usuário {$login}. Verifique os logs do servidor.");
}

header("Location: samba_usuarios.php?msg={$msg}");
exit;
