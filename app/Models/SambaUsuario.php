<?php

namespace App\Models;

use App\Core\Database;

class SambaUsuario
{
    public static function listar(): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->query("SELECT * FROM samba_usuarios ORDER BY nome");

        return $stmt->fetchAll();
    }

    public static function contarTotal(): int
    {
        return count(self::listar());
    }

    public static function contarAtivos(): int
    {
        return count(array_filter(self::listar(), fn($u) => $u['status'] === 'ativo'));
    }

    public static function contarComSsh(): int
    {
        return count(array_filter(self::listar(), fn($u) => (int)$u['ssh'] === 1));
    }
}
