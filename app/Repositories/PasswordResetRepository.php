<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PasswordResetRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function criar(int $usuarioId, string $tokenHash, string $expiraEm): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO redefinicao_senha_tokens (usuario_id, token_hash, expira_em)
            VALUES (?, ?, ?)
        ");

        $stmt->execute([$usuarioId, $tokenHash, $expiraEm]);
    }

    public function buscarValido(string $tokenHash): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT t.id, t.usuario_id, u.nome, u.login, u.email
            FROM redefinicao_senha_tokens t
            INNER JOIN usuarios u ON u.id = t.usuario_id
            WHERE t.token_hash = ?
              AND t.usado_em IS NULL
              AND t.expira_em > NOW()
              AND u.ativo = 1
            LIMIT 1
        ");

        $stmt->execute([$tokenHash]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function marcarUsado(int $tokenId): void
    {
        $stmt = $this->pdo->prepare("UPDATE redefinicao_senha_tokens SET usado_em = NOW() WHERE id = ?");
        $stmt->execute([$tokenId]);
    }

    public function invalidarPendentes(int $usuarioId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE redefinicao_senha_tokens
               SET usado_em = NOW()
             WHERE usuario_id = ? AND usado_em IS NULL
        ");

        $stmt->execute([$usuarioId]);
    }
}
