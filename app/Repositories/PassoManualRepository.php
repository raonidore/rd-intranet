<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PassoManualRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function confirmacoes(): array
    {
        $stmt = $this->pdo->query("
            SELECT p.*, u.nome AS confirmado_por_nome
              FROM passos_manuais_confirmacoes p
              LEFT JOIN usuarios u ON u.id = p.confirmado_por
        ");

        $porChave = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            $porChave[$linha['chave']] = $linha;
        }

        return $porChave;
    }

    public function confirmar(string $chave, ?int $usuarioId): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO passos_manuais_confirmacoes (chave, confirmado_por, confirmado_em)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE confirmado_por = VALUES(confirmado_por), confirmado_em = VALUES(confirmado_em)
        ");
        $stmt->execute([$chave, $usuarioId]);
    }

    public function desconfirmar(string $chave): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM passos_manuais_confirmacoes WHERE chave = ?");
        $stmt->execute([$chave]);
    }
}
