<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class VpnIkev2Repository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function config(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM vpn_ikev2_config WHERE id = 1');

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function salvarConfig(array $dados): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE vpn_ikev2_config
               SET subnet_cidr = ?, dns_push = ?, endpoint_publico = ?
             WHERE id = 1
        ');
        $stmt->execute([$dados['subnet_cidr'], $dados['dns_push'], $dados['endpoint_publico']]);
    }

    public function marcarInstalado(): void
    {
        $this->pdo->exec('UPDATE vpn_ikev2_config SET instalado = 1 WHERE id = 1');
    }

    public function marcarPkiInicializada(): void
    {
        $this->pdo->exec('UPDATE vpn_ikev2_config SET pki_inicializada = 1 WHERE id = 1');
    }

    public function marcarExposto(bool $exposto): void
    {
        $stmt = $this->pdo->prepare('UPDATE vpn_ikev2_config SET exposto_internet = ? WHERE id = 1');
        $stmt->execute([$exposto ? 1 : 0]);
    }

    public function listarClientes(bool $apenasAtivos = false): array
    {
        $sql = 'SELECT * FROM vpn_ikev2_clientes';
        if ($apenasAtivos) {
            $sql .= ' WHERE ativo = 1';
        }
        $sql .= ' ORDER BY nome';

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarCliente(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM vpn_ikev2_clientes WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function buscarClientePorNome(string $nome): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM vpn_ikev2_clientes WHERE nome = ? LIMIT 1');
        $stmt->execute([$nome]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function criarCliente(string $nome, string $senhaCriptografada): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO vpn_ikev2_clientes (nome, senha) VALUES (?, ?)');
        $stmt->execute([$nome, $senhaCriptografada]);

        return (int)$this->pdo->lastInsertId();
    }

    public function marcarConfigEntregue(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE vpn_ikev2_clientes SET config_entregue = 1, config_entregue_em = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function revogarCliente(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE vpn_ikev2_clientes SET ativo = 0, revogado_em = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function registrarTrafego(int $clienteId, int $rxBytes, int $txBytes): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO vpn_ikev2_trafego_historico (cliente_id, rx_bytes, tx_bytes) VALUES (?, ?, ?)');
        $stmt->execute([$clienteId, $rxBytes, $txBytes]);
    }

    public function historicoTrafego(int $clienteId, int $limite = 50): array
    {
        $limite = max(1, min($limite, 200));

        $stmt = $this->pdo->prepare("
            SELECT * FROM vpn_ikev2_trafego_historico
             WHERE cliente_id = ?
             ORDER BY id DESC
             LIMIT {$limite}
        ");
        $stmt->execute([$clienteId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function trafegoAgregadoHoje(): array
    {
        $stmt = $this->pdo->query('
            SELECT COALESCE(SUM(rx_bytes), 0) AS rx_total, COALESCE(SUM(tx_bytes), 0) AS tx_total
              FROM vpn_ikev2_trafego_historico
             WHERE DATE(coletado_em) = CURDATE()
        ');

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['rx_total' => 0, 'tx_total' => 0];
    }
}
