<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class AuditoriaRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function ultimos(int $limite = 100): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM auditoria
            ORDER BY id DESC
            LIMIT ?
        ");

        $stmt->bindValue(1, $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
