<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class SpeedtestRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function criar(array $dados): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO speedtest_historico
                (status, download_mbps, upload_mbps, ping_ms, jitter_ms, servidor, isp, mensagem_erro)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $dados['status'],
            $dados['download_mbps'] ?? null,
            $dados['upload_mbps'] ?? null,
            $dados['ping_ms'] ?? null,
            $dados['jitter_ms'] ?? null,
            $dados['servidor'] ?? null,
            $dados['isp'] ?? null,
            $dados['mensagem_erro'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function ultimoConcluido(): ?array
    {
        $stmt = $this->pdo->query("
            SELECT * FROM speedtest_historico
             WHERE status = 'concluido'
             ORDER BY id DESC
             LIMIT 1
        ");

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function listar(int $limite = 20): array
    {
        $limite = max(1, min($limite, 100));

        $stmt = $this->pdo->query("SELECT * FROM speedtest_historico ORDER BY id DESC LIMIT {$limite}");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
