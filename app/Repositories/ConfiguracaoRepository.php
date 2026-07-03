<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ConfiguracaoRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function buscar(string $chave, ?string $padrao = null): ?string
    {
        $stmt = $this->pdo->prepare("
            SELECT valor
            FROM configuracoes
            WHERE chave = ?
            LIMIT 1
        ");

        $stmt->execute([$chave]);

        $valor = $stmt->fetchColumn();

        return $valor !== false ? $valor : $padrao;
    }

    public function todos(): array
    {
        $stmt = $this->pdo->query("
            SELECT chave, valor
            FROM configuracoes
            ORDER BY chave
        ");

        return $stmt->fetchAll();
    }

    public function atualizar(string $chave, string $valor): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO configuracoes (chave, valor)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE valor = VALUES(valor)
        ");

        $stmt->execute([$chave, $valor]);
    }
}
