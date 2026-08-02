<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class BackupExecucaoArquivoRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /**
     * @param array<int, array{compartilhamento: string, caminho: string, tipo: string, tamanho_anterior: ?int, tamanho_novo: ?int, timestamp_versao?: ?string}> $linhas
     */
    public function inserirLote(int $execucaoId, array $linhas): void
    {
        if (empty($linhas)) {
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO backup_execucao_arquivos
                (execucao_id, compartilhamento, caminho_relativo, tipo, timestamp_versao, tamanho_anterior, tamanho_novo)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($linhas as $linha) {
            $stmt->execute([
                $execucaoId,
                $linha['compartilhamento'],
                $linha['caminho'],
                $linha['tipo'],
                $linha['timestamp_versao'] ?? null,
                $linha['tamanho_anterior'],
                $linha['tamanho_novo'],
            ]);
        }
    }

    public function jaRegistrado(int $execucaoId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM backup_execucao_arquivos WHERE execucao_id = ? LIMIT 1");
        $stmt->execute([$execucaoId]);

        return (bool)$stmt->fetchColumn();
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM backup_execucao_arquivos WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function listarPorExecucao(int $execucaoId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM backup_execucao_arquivos
            WHERE execucao_id = ?
            ORDER BY compartilhamento, tipo, caminho_relativo
        ");
        $stmt->execute([$execucaoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
