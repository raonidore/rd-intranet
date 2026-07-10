<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class AtualizacaoRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function registrar(
        string $tipo,
        ?string $commitAntes,
        ?string $commitDepois,
        bool $sucesso,
        string $saida,
        ?int $usuarioId
    ): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO atualizacoes_log (tipo, commit_antes, commit_depois, sucesso, saida, usuario_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([$tipo, $commitAntes, $commitDepois, $sucesso ? 1 : 0, $saida, $usuarioId]);

        return (int)$this->pdo->lastInsertId();
    }

    public function ultimoSucesso(string $tipo): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM atualizacoes_log
             WHERE tipo = ? AND sucesso = 1
             ORDER BY id DESC
             LIMIT 1
        ");
        $stmt->execute([$tipo]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function listar(int $limite = 20): array
    {
        $limite = max(1, min($limite, 100));

        $stmt = $this->pdo->query("
            SELECT a.*, u.nome AS usuario_nome
              FROM atualizacoes_log a
              LEFT JOIN usuarios u ON u.id = a.usuario_id
             ORDER BY a.id DESC
             LIMIT {$limite}
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
