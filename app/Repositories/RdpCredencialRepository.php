<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class RdpCredencialRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function buscarPorAtivo(int $ativoId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ativos_rdp_credenciais WHERE ativo_id = ? LIMIT 1');
        $stmt->execute([$ativoId]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function salvar(int $ativoId, string $host, int $porta, string $usuario, string $senhaCifrada): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO ativos_rdp_credenciais (ativo_id, host, porta, usuario, senha_cifrada)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                host = VALUES(host),
                porta = VALUES(porta),
                usuario = VALUES(usuario),
                senha_cifrada = VALUES(senha_cifrada)
        ');

        $stmt->execute([$ativoId, $host, $porta, $usuario, $senhaCifrada]);
    }

    public function remover(int $ativoId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM ativos_rdp_credenciais WHERE ativo_id = ?');
        $stmt->execute([$ativoId]);
    }
}
