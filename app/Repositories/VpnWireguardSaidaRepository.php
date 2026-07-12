<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class VpnWireguardSaidaRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listar(): array
    {
        $stmt = $this->pdo->query('SELECT id, nome, ativo_no_boot, criado_em FROM vpn_wireguard_conexoes_saida ORDER BY nome');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM vpn_wireguard_conexoes_saida WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function buscarPorNome(string $nome): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM vpn_wireguard_conexoes_saida WHERE nome = ? LIMIT 1');
        $stmt->execute([$nome]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function criar(string $nome, string $arquivoConfCriptografado): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO vpn_wireguard_conexoes_saida (nome, arquivo_conf) VALUES (?, ?)');
        $stmt->execute([$nome, $arquivoConfCriptografado]);

        return (int)$this->pdo->lastInsertId();
    }

    public function marcarAtivoNoBoot(int $id, bool $ativo): void
    {
        $stmt = $this->pdo->prepare('UPDATE vpn_wireguard_conexoes_saida SET ativo_no_boot = ? WHERE id = ?');
        $stmt->execute([$ativo ? 1 : 0, $id]);
    }

    public function excluir(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM vpn_wireguard_conexoes_saida WHERE id = ?');
        $stmt->execute([$id]);
    }
}
