<?php

namespace App\Services;

use App\Core\Database;

class AuditService
{
    public static function registrar(string $modulo, string $acao, string $descricao = ''): void
    {
        $pdo = Database::connection();

        $usuario = $_SESSION['usuario'] ?? null;

        $stmt = $pdo->prepare("
            INSERT INTO auditoria
            (usuario_id, usuario_nome, modulo, acao, descricao, ip_origem, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $usuario['id'] ?? null,
            $usuario['nome'] ?? 'Sistema',
            $modulo,
            $acao,
            $descricao,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
}
