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
     * Lista todos os usuários.
     */
    public function listar(): array
    {
        $stmt = $this->pdo->query("
            SELECT *
            FROM samba_usuarios
            ORDER BY nome
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

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

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        return $usuario ?: null;
    }

    /**
     * Cria um novo usuário.
     */
    public function criar(string $nome, string $login, string $departamento, bool $ssh, ?int $uidLinux): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO samba_usuarios (nome, login, departamento, ssh, uid_linux, status)
            VALUES (?, ?, ?, ?, ?, 'ativo')
        ");

        $stmt->execute([$nome, $login, $departamento, $ssh ? 1 : 0, $uidLinux]);

        return (int)$this->pdo->lastInsertId();
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
     * Atualiza os dados do usuário.
     */
    public function atualizar(
        int $id,
        string $nome,
        string $departamento,
        bool $ssh
    ): bool {

        $stmt = $this->pdo->prepare("
            UPDATE samba_usuarios
               SET nome = ?,
                   departamento = ?,
                   ssh = ?
             WHERE id = ?
        ");

        return $stmt->execute([
            $nome,
            $departamento,
            $ssh ? 1 : 0,
            $id
        ]);
    }

    /**
     * Remove o usuário do banco.
     */
    public function excluir(int $id): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE
              FROM samba_usuarios
             WHERE id = ?
        ");

        return $stmt->execute([$id]);
    }

    /**
     * Total de usuários.
     */
    public function contarTotal(): int
    {
        return (int)$this->pdo
            ->query("SELECT COUNT(*) FROM samba_usuarios")
            ->fetchColumn();
    }

    /**
     * Usuários ativos.
     */
    public function contarAtivos(): int
    {
        return (int)$this->pdo
            ->query("
                SELECT COUNT(*)
                  FROM samba_usuarios
                 WHERE status='ativo'
            ")
            ->fetchColumn();
    }

    /**
     * Usuários com SSH.
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

    /**
     * Grupos em uso hoje por compartilhamentos (são os que fazem sentido
     * atribuir a um usuário, já que só eles dão acesso a algum caminho real).
     */
    public function gruposEmUso(): array
    {
        $stmt = $this->pdo->query("
            SELECT DISTINCT grupo
              FROM samba_compartilhamentos
             ORDER BY grupo
        ");

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
