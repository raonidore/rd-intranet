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

    /**
     * Lista todos os usuários Samba.
     */
    public function listar(): array
    {
        $stmt = $this->pdo->query("
            SELECT *
            FROM samba_usuarios
            ORDER BY nome
        ");

        return $stmt->fetchAll();
    }

    /**
     * Busca um usuário pelo ID.
     */
    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM samba_usuarios
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$id]);

        $usuario = $stmt->fetch();

        return $usuario ?: null;
    }

    /**
     * Busca um usuário pelo login.
     */
    public function buscarPorLogin(string $login): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM samba_usuarios
            WHERE login = ?
            LIMIT 1
        ");

        $stmt->execute([$login]);

        $usuario = $stmt->fetch();

        return $usuario ?: null;
    }

    /**
     * Atualiza o status do usuário.
     */
    public function atualizarStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE samba_usuarios
               SET status = ?
             WHERE id = ?
        ");

        return $stmt->execute([
            $status,
            $id
        ]);
    }

    /**
     * Conta o total de usuários.
     */
    public function contarTotal(): int
    {
        return (int)$this->pdo
            ->query("SELECT COUNT(*) FROM samba_usuarios")
            ->fetchColumn();
    }

    /**
     * Conta usuários ativos.
     */
    public function contarAtivos(): int
    {
        return (int)$this->pdo
            ->query("
                SELECT COUNT(*)
                FROM samba_usuarios
                WHERE status = 'ativo'
            ")
            ->fetchColumn();
    }

    /**
     * Conta usuários com SSH habilitado.
     */
    public function contarComSSH(): int
    {
        return (int)$this->pdo
            ->query("
                SELECT COUNT(*)
                FROM samba_usuarios
                WHERE ssh = 1
            ")
            ->fetchColumn();
    }
}
