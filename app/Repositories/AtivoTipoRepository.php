<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Tipo de ativo (Computador, Monitor, Switch...) -- cadastro editável,
 * substitui o ENUM fixo que existia antes em `ativos.tipo`.
 */
class AtivoTipoRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listar(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM ativos_tipos ORDER BY nome");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarAtivos(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM ativos_tipos WHERE ativo = 1 ORDER BY nome");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ativos_tipos WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function buscarPorSlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ativos_tipos WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function existeNome(string $nome): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM ativos_tipos WHERE nome = ? LIMIT 1");
        $stmt->execute([$nome]);

        return (bool)$stmt->fetchColumn();
    }

    public function existeOutroNome(string $nome, int $idExcluido): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM ativos_tipos WHERE nome = ? AND id <> ? LIMIT 1");
        $stmt->execute([$nome, $idExcluido]);

        return (bool)$stmt->fetchColumn();
    }

    public function existeSigla(string $sigla): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM ativos_tipos WHERE sigla = ? LIMIT 1");
        $stmt->execute([$sigla]);

        return (bool)$stmt->fetchColumn();
    }

    public function existeOutraSigla(string $sigla, int $idExcluido): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM ativos_tipos WHERE sigla = ? AND id <> ? LIMIT 1");
        $stmt->execute([$sigla, $idExcluido]);

        return (bool)$stmt->fetchColumn();
    }

    public function criar(string $nome, string $sigla, string $icone, bool $snmpElegivel): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO ativos_tipos (nome, sigla, icone, snmp_elegivel) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$nome, $sigla, $icone, $snmpElegivel ? 1 : 0]);

        return (int)$this->pdo->lastInsertId();
    }

    public function atualizar(int $id, string $nome, string $sigla, string $icone, bool $snmpElegivel): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE ativos_tipos SET nome = ?, sigla = ?, icone = ?, snmp_elegivel = ? WHERE id = ?"
        );

        return $stmt->execute([$nome, $sigla, $icone, $snmpElegivel ? 1 : 0, $id]);
    }

    public function excluir(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM ativos_tipos WHERE id = ?");

        return $stmt->execute([$id]);
    }

    public function contarUsos(int $id): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM ativos WHERE tipo_id = ?");
        $stmt->execute([$id]);

        return (int)$stmt->fetchColumn();
    }
}
