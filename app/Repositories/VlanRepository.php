<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class VlanRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listar(): array
    {
        return $this->pdo->query('SELECT * FROM vlans ORDER BY interface_pai, vlan_id')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarAtivas(): array
    {
        return $this->pdo->query('SELECT * FROM vlans WHERE ativo = 1 ORDER BY interface_pai, vlan_id')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM vlans WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function buscarPorInterfaceVlanId(string $interfacePai, int $vlanId, ?int $ignorarId = null): ?array
    {
        $sql = 'SELECT * FROM vlans WHERE interface_pai = ? AND vlan_id = ?';
        $params = [$interfacePai, $vlanId];

        if ($ignorarId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $ignorarId;
        }

        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function buscarPorIp(string $ip, ?int $ignorarId = null): ?array
    {
        $sql = 'SELECT * FROM vlans WHERE ip_endereco = ?';
        $params = [$ip];

        if ($ignorarId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $ignorarId;
        }

        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function idsUsados(string $interfacePai): array
    {
        $stmt = $this->pdo->prepare('SELECT vlan_id FROM vlans WHERE interface_pai = ?');
        $stmt->execute([$interfacePai]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function criar(array $dados): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO vlans (nome, interface_pai, vlan_id, ip_endereco, prefixo, descricao, ativo)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $dados['nome'], $dados['interface_pai'], $dados['vlan_id'],
            $dados['ip_endereco'], $dados['prefixo'], $dados['descricao'], $dados['ativo'] ?? 1,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function atualizar(int $id, array $dados): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE vlans
               SET nome = ?, interface_pai = ?, vlan_id = ?, ip_endereco = ?, prefixo = ?, descricao = ?, ativo = ?
             WHERE id = ?
        ');
        $stmt->execute([
            $dados['nome'], $dados['interface_pai'], $dados['vlan_id'],
            $dados['ip_endereco'], $dados['prefixo'], $dados['descricao'], $dados['ativo'] ?? 1, $id,
        ]);
    }

    public function excluir(int $id): void
    {
        $this->pdo->prepare('DELETE FROM vlans WHERE id = ?')->execute([$id]);
    }
}
