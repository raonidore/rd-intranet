<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class SambaCompartilhamentoExcecaoRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listarPorCompartilhamento(int $compartilhamentoId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM samba_compartilhamento_excecoes
            WHERE compartilhamento_id = ?
            ORDER BY pasta
        ");

        $stmt->execute([$compartilhamentoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM samba_compartilhamento_excecoes WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function buscarPorNome(string $nome): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM samba_compartilhamento_excecoes WHERE nome_compartilhamento = ? LIMIT 1");
        $stmt->execute([$nome]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function criar(int $compartilhamentoId, string $pasta, string $nome, string $extensoes): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO samba_compartilhamento_excecoes
            (compartilhamento_id, pasta, nome_compartilhamento, extensoes)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([$compartilhamentoId, $pasta, $nome, $extensoes]);

        return (int)$this->pdo->lastInsertId();
    }

    public function excluir(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM samba_compartilhamento_excecoes WHERE id = ?");

        return $stmt->execute([$id]);
    }
}
