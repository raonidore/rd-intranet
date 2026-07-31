<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ConfigBackupRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function criar(string $tipo, ?int $usuarioId): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO config_backups (tipo, status, usuario_id)
            VALUES (?, 'executando', ?)
        ");
        $stmt->execute([$tipo, $usuarioId]);

        return (int)$this->pdo->lastInsertId();
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM config_backups WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function finalizar(int $id, string $status, ?string $arquivo, int $tamanhoBytes, bool $enviadoNuvem, ?string $mensagemErro): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE config_backups
            SET status = ?, arquivo = ?, tamanho_bytes = ?, enviado_nuvem = ?, mensagem_erro = ?, finalizado_em = NOW()
            WHERE id = ? AND status = 'executando'
        ");
        $stmt->execute([$status, $arquivo, $tamanhoBytes, $enviadoNuvem ? 1 : 0, $mensagemErro, $id]);
    }

    public function listar(int $limite = 30): array
    {
        $limite = max(1, min($limite, 200));

        $stmt = $this->pdo->query("
            SELECT * FROM config_backups
            ORDER BY id DESC
            LIMIT {$limite}
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
