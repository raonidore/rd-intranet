<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class RdBridgeRepository
{
    private PDO $pdo;

    private const SELECT_ENRIQUECIDO = "
        SELECT b.*, u.nome AS unidade_nome, u.sigla AS unidade_sigla
        FROM rd_bridge_coletores b
        JOIN unidades u ON u.id = b.unidade_id
    ";

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listar(): array
    {
        $stmt = $this->pdo->query(self::SELECT_ENRIQUECIDO . " ORDER BY u.nome, b.nome");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT_ENRIQUECIDO . " WHERE b.id = ? LIMIT 1");
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function buscarPorToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT_ENRIQUECIDO . " WHERE b.token = ? AND b.ativo = 1 LIMIT 1");
        $stmt->execute([$token]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function criar(int $unidadeId, string $nome, string $token, string $modo): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO rd_bridge_coletores (unidade_id, nome, token, modo)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$unidadeId, $nome, $token, $modo]);

        return (int)$this->pdo->lastInsertId();
    }

    public function revogar(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE rd_bridge_coletores SET ativo = 0 WHERE id = ?");

        return $stmt->execute([$id]);
    }

    public function registrarCheckin(int $id, string $ip, ?string $versao): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE rd_bridge_coletores
               SET ip_ultimo_checkin = ?, ultimo_checkin_em = NOW(), versao = COALESCE(?, versao)
             WHERE id = ?
        ");
        $stmt->execute([$ip, $versao, $id]);
    }
}
