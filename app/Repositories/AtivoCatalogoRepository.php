<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Cadastros de apoio do módulo Ativos (Setor, Localização) -- uma
 * tabela só, discriminada por `tipo`, pra evitar duplicar duas tabelas
 * quase idênticas.
 */
class AtivoCatalogoRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listarPorTipo(string $tipo): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ativos_catalogos WHERE tipo = ? ORDER BY nome");
        $stmt->execute([$tipo]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ativos_catalogos WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function existe(string $tipo, string $nome): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM ativos_catalogos WHERE tipo = ? AND nome = ? LIMIT 1");
        $stmt->execute([$tipo, $nome]);

        return (bool)$stmt->fetchColumn();
    }

    public function criar(string $tipo, string $nome): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO ativos_catalogos (tipo, nome) VALUES (?, ?)");
        $stmt->execute([$tipo, $nome]);

        return (int)$this->pdo->lastInsertId();
    }

    public function existeOutro(string $tipo, string $nome, int $idExcluido): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM ativos_catalogos WHERE tipo = ? AND nome = ? AND id <> ? LIMIT 1");
        $stmt->execute([$tipo, $nome, $idExcluido]);

        return (bool)$stmt->fetchColumn();
    }

    public function atualizar(int $id, string $nome): bool
    {
        $stmt = $this->pdo->prepare("UPDATE ativos_catalogos SET nome = ? WHERE id = ?");

        return $stmt->execute([$nome, $id]);
    }

    public function excluir(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM ativos_catalogos WHERE id = ?");

        return $stmt->execute([$id]);
    }

    public function contarUsos(int $id): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM ativos WHERE setor_id = ? OR localizacao_id = ?
        ");
        $stmt->execute([$id, $id]);

        return (int)$stmt->fetchColumn();
    }
}
