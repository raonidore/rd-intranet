<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class UserRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listar(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, nome, login, perfil, ativo, criado_em
            FROM usuarios
            ORDER BY nome
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, nome, login, perfil, ativo, criado_em
            FROM usuarios
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function buscarPorLogin(string $login): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id FROM usuarios WHERE login = ? LIMIT 1");
        $stmt->execute([$login]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function criar(string $nome, string $login, string $senhaHash, string $perfil): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO usuarios (nome, login, senha_hash, perfil, ativo)
            VALUES (?, ?, ?, ?, 1)
        ");

        $stmt->execute([$nome, $login, $senhaHash, $perfil]);

        return (int)$this->pdo->lastInsertId();
    }

    public function atualizar(int $id, string $nome, string $perfil): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE usuarios
               SET nome = ?, perfil = ?
             WHERE id = ?
        ");

        return $stmt->execute([$nome, $perfil, $id]);
    }

    public function atualizarSenha(int $id, string $senhaHash): bool
    {
        $stmt = $this->pdo->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ?");

        return $stmt->execute([$senhaHash, $id]);
    }

    public function definirAtivo(int $id, bool $ativo): bool
    {
        $stmt = $this->pdo->prepare("UPDATE usuarios SET ativo = ? WHERE id = ?");

        return $stmt->execute([$ativo ? 1 : 0, $id]);
    }

    public function excluir(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM usuarios WHERE id = ?");

        return $stmt->execute([$id]);
    }

    public function contarAdmins(): int
    {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM usuarios WHERE perfil = 'admin'")->fetchColumn();
    }

    public function contarAdminsAtivos(): int
    {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM usuarios WHERE perfil = 'admin' AND ativo = 1")->fetchColumn();
    }

    public function modulosDoUsuario(int $id): array
    {
        $stmt = $this->pdo->prepare("SELECT modulo FROM usuario_modulos WHERE usuario_id = ?");
        $stmt->execute([$id]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function salvarModulos(int $id, array $modulos): void
    {
        $delete = $this->pdo->prepare("DELETE FROM usuario_modulos WHERE usuario_id = ?");
        $delete->execute([$id]);

        if (empty($modulos)) {
            return;
        }

        $insert = $this->pdo->prepare("INSERT INTO usuario_modulos (usuario_id, modulo) VALUES (?, ?)");

        foreach ($modulos as $modulo) {
            $insert->execute([$id, $modulo]);
        }
    }
}
