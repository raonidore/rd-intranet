<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class VpnWireguardRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function config(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM vpn_wireguard_config WHERE id = 1');

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function salvarConfig(array $dados): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE vpn_wireguard_config
               SET interface_nome = ?, porta = ?, subnet_cidr = ?, servidor_ip_interno = ?,
                   dns_push = ?, endpoint_publico = ?, mtu = ?
             WHERE id = 1
        ');
        $stmt->execute([
            $dados['interface_nome'],
            $dados['porta'],
            $dados['subnet_cidr'],
            $dados['servidor_ip_interno'],
            $dados['dns_push'],
            $dados['endpoint_publico'],
            $dados['mtu'],
        ]);
    }

    public function salvarChaves(string $privada, string $publica): void
    {
        $stmt = $this->pdo->prepare('UPDATE vpn_wireguard_config SET chave_privada = ?, chave_publica = ? WHERE id = 1');
        $stmt->execute([$privada, $publica]);
    }

    public function marcarInstalado(): void
    {
        $this->pdo->exec('UPDATE vpn_wireguard_config SET instalado = 1 WHERE id = 1');
    }

    public function marcarExposto(bool $exposto): void
    {
        $stmt = $this->pdo->prepare('UPDATE vpn_wireguard_config SET exposto_internet = ? WHERE id = 1');
        $stmt->execute([$exposto ? 1 : 0]);
    }

    public function listarPeers(bool $apenasAtivos = false): array
    {
        $sql = 'SELECT * FROM vpn_wireguard_peers';
        if ($apenasAtivos) {
            $sql .= ' WHERE ativo = 1';
        }
        $sql .= ' ORDER BY nome';

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPeer(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM vpn_wireguard_peers WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function ipsAtribuidos(): array
    {
        return $this->pdo->query('SELECT ip_atribuido FROM vpn_wireguard_peers')->fetchAll(PDO::FETCH_COLUMN);
    }

    public function criarPeer(string $nome, string $chavePublica, string $ip): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO vpn_wireguard_peers (nome, chave_publica, ip_atribuido)
            VALUES (?, ?, ?)
        ');
        $stmt->execute([$nome, $chavePublica, $ip]);

        return (int)$this->pdo->lastInsertId();
    }

    public function marcarConfigEntregue(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE vpn_wireguard_peers SET config_entregue = 1, config_entregue_em = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function revogarPeer(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE vpn_wireguard_peers SET ativo = 0, revogado_em = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function registrarTrafego(int $peerId, int $rxBytes, int $txBytes, ?string $ultimoHandshake): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO vpn_wireguard_trafego_historico (peer_id, rx_bytes, tx_bytes, ultimo_handshake)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$peerId, $rxBytes, $txBytes, $ultimoHandshake]);
    }

    public function historicoTrafego(int $peerId, int $limite = 50): array
    {
        $limite = max(1, min($limite, 200));

        $stmt = $this->pdo->prepare("
            SELECT * FROM vpn_wireguard_trafego_historico
             WHERE peer_id = ?
             ORDER BY id DESC
             LIMIT {$limite}
        ");
        $stmt->execute([$peerId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function trafegoAgregadoHoje(): array
    {
        $stmt = $this->pdo->query('
            SELECT COALESCE(SUM(rx_bytes), 0) AS rx_total, COALESCE(SUM(tx_bytes), 0) AS tx_total
              FROM vpn_wireguard_trafego_historico
             WHERE DATE(coletado_em) = CURDATE()
        ');

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['rx_total' => 0, 'tx_total' => 0];
    }
}
