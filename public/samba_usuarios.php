<?php
require_once __DIR__ . '/../app/bootstrap.php';

auth_required();

$stmt = $pdo->query("SELECT * FROM samba_usuarios ORDER BY nome");
$usuarios = $stmt->fetchAll();

$total = count($usuarios);
$ativos = count(array_filter($usuarios, fn($u) => $u['status'] === 'ativo'));
$sshTotal = count(array_filter($usuarios, fn($u) => (int)$u['ssh'] === 1));

view('samba/usuarios', compact('usuarios', 'total', 'ativos', 'sshTotal'));
