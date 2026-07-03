<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class SambaUsuarioRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listar(): array
    {
        $stmt = $this->pdo->query("
            SELECT *
            FROM samba_usuarios
            ORDER BY nome
        ");

        return $stmt->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM samba_usuarios
            WHERE id = ?
        ");

        $stmt->execute([$id]);

        $usuario = $stmt->fetch();

        return $usuario ?: null;
    }

    public function contarTotal(): int
    {
        return (int)$this->pdo
            ->query("SELECT COUNT(*) FROM samba_usuarios")
            ->fetchColumn();
    }

    public function contarAtivos(): int
    {
        return (int)$this->pdo
            ->query("SELECT COUNT(*) FROM samba_usuarios WHERE status='ativo'")
            ->fetchColumn();
    }

    public function contarComSSH(): int
    {
        return (int)$this->pdo
            ->query("SELECT COUNT(*) FROM samba_usuarios WHERE ssh=1")
            ->fetchColumn();
    }
}
