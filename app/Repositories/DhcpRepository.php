<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class DhcpRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function config(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM dhcp_config WHERE id = 1');

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function salvarConfig(array $dados): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE dhcp_config
               SET interface = ?, dominio = ?, dns_primario = ?, dns_secundario = ?,
                   lease_padrao_segundos = ?, lease_maximo_segundos = ?
             WHERE id = 1
        ');
        $stmt->execute([
            $dados['interface'],
            $dados['dominio'],
            $dados['dns_primario'],
            $dados['dns_secundario'],
            $dados['lease_padrao_segundos'],
            $dados['lease_maximo_segundos'],
        ]);
    }

    public function marcarInstalado(): void
    {
        $this->pdo->exec('UPDATE dhcp_config SET instalado = 1 WHERE id = 1');
    }

    public function marcarServicoAtivo(bool $ativo): void
    {
        $stmt = $this->pdo->prepare('UPDATE dhcp_config SET servico_ativo = ? WHERE id = 1');
        $stmt->execute([$ativo ? 1 : 0]);
    }

    // ── Subnets ──────────────────────────────────────────────────────────

    public function listarSubnets(): array
    {
        return $this->pdo->query('SELECT * FROM dhcp_subnets ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarSubnet(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM dhcp_subnets WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function criarSubnet(array $dados): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO dhcp_subnets (nome, rede, mascara, faixa_inicio, faixa_fim, gateway)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $dados['nome'], $dados['rede'], $dados['mascara'],
            $dados['faixa_inicio'], $dados['faixa_fim'], $dados['gateway'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function atualizarSubnet(int $id, array $dados): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE dhcp_subnets
               SET nome = ?, rede = ?, mascara = ?, faixa_inicio = ?, faixa_fim = ?, gateway = ?
             WHERE id = ?
        ');
        $stmt->execute([
            $dados['nome'], $dados['rede'], $dados['mascara'],
            $dados['faixa_inicio'], $dados['faixa_fim'], $dados['gateway'], $id,
        ]);
    }

    public function excluirSubnet(int $id): void
    {
        $this->pdo->prepare('DELETE FROM dhcp_subnets WHERE id = ?')->execute([$id]);
    }

    // ── Reservas ─────────────────────────────────────────────────────────

    public function listarReservas(): array
    {
        $sql = "
            SELECT r.*, s.nome AS subnet_nome
            FROM dhcp_reservas r
            JOIN dhcp_subnets s ON s.id = r.subnet_id
            ORDER BY r.ip
        ";

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarReservaPorMacOuIp(string $mac, string $ip, ?int $ignorarId = null): ?array
    {
        $sql = 'SELECT * FROM dhcp_reservas WHERE (mac_address = ? OR ip = ?)';
        $params = [$mac, $ip];

        if ($ignorarId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $ignorarId;
        }

        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function criarReserva(array $dados): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO dhcp_reservas (subnet_id, mac_address, ip, descricao)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$dados['subnet_id'], $dados['mac_address'], $dados['ip'], $dados['descricao']]);

        return (int)$this->pdo->lastInsertId();
    }

    public function excluirReserva(int $id): void
    {
        $this->pdo->prepare('DELETE FROM dhcp_reservas WHERE id = ?')->execute([$id]);
    }
}
