<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Unidades (filiais/sites) da empresa -- usadas no código de patrimônio
 * dos ativos e (mais adiante) na abertura de chamados.
 */
class UnidadeRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listar(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM unidades ORDER BY nome");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarAtivas(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM unidades WHERE ativo = 1 ORDER BY nome");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM unidades WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function buscarPadrao(): ?array
    {
        $stmt = $this->pdo->query("SELECT * FROM unidades WHERE padrao = 1 LIMIT 1");

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function existeSigla(string $sigla): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM unidades WHERE sigla = ? LIMIT 1");
        $stmt->execute([$sigla]);

        return (bool)$stmt->fetchColumn();
    }

    public function existeOutraSigla(string $sigla, int $idExcluido): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM unidades WHERE sigla = ? AND id <> ? LIMIT 1");
        $stmt->execute([$sigla, $idExcluido]);

        return (bool)$stmt->fetchColumn();
    }

    public function criar(string $nome, string $sigla): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO unidades (nome, sigla) VALUES (?, ?)");
        $stmt->execute([$nome, $sigla]);

        return (int)$this->pdo->lastInsertId();
    }

    public function atualizar(int $id, string $nome, string $sigla): bool
    {
        $stmt = $this->pdo->prepare("UPDATE unidades SET nome = ?, sigla = ? WHERE id = ?");

        return $stmt->execute([$nome, $sigla, $id]);
    }

    public function excluir(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM unidades WHERE id = ?");

        return $stmt->execute([$id]);
    }

    public function contarUsos(int $id): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM ativos WHERE unidade_id = ?");
        $stmt->execute([$id]);

        return (int)$stmt->fetchColumn();
    }
}
