<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class IptablesRegraRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listar(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM iptables_regras ORDER BY tabela, cadeia, ordem, id");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarAtivas(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM iptables_regras WHERE ativo = 1 ORDER BY tabela, cadeia, ordem, id");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM iptables_regras WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function criar(array $d): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO iptables_regras
                (nome, descricao, tabela, cadeia, acao, protocolo, porta_destino, porta_origem,
                 ip_origem, ip_destino, interface_entrada, interface_saida, nat_destino, extra,
                 registrar_log, ordem, origem_template, ativo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute($this->valores($d));

        return (int)$this->pdo->lastInsertId();
    }

    public function atualizar(int $id, array $d): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE iptables_regras SET
                nome = ?, descricao = ?, tabela = ?, cadeia = ?, acao = ?, protocolo = ?,
                porta_destino = ?, porta_origem = ?, ip_origem = ?, ip_destino = ?,
                interface_entrada = ?, interface_saida = ?, nat_destino = ?, extra = ?,
                registrar_log = ?, ordem = ?, origem_template = ?, ativo = ?
             WHERE id = ?
        ");

        return $stmt->execute([...$this->valores($d), $id]);
    }

    public function definirAtivo(int $id, bool $ativo): bool
    {
        $stmt = $this->pdo->prepare("UPDATE iptables_regras SET ativo = ? WHERE id = ?");

        return $stmt->execute([$ativo ? 1 : 0, $id]);
    }

    public function reordenar(int $id, int $ordem): bool
    {
        $stmt = $this->pdo->prepare("UPDATE iptables_regras SET ordem = ? WHERE id = ?");

        return $stmt->execute([$ordem, $id]);
    }

    public function excluir(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM iptables_regras WHERE id = ?");

        return $stmt->execute([$id]);
    }

    private function valores(array $d): array
    {
        return [
            $d['nome'],
            $d['descricao'] ?: null,
            $d['tabela'],
            $d['cadeia'],
            $d['acao'],
            $d['protocolo'],
            $d['porta_destino'] ?: null,
            $d['porta_origem'] ?: null,
            $d['ip_origem'] ?: null,
            $d['ip_destino'] ?: null,
            $d['interface_entrada'] ?: null,
            $d['interface_saida'] ?: null,
            $d['nat_destino'] ?: null,
            $d['extra'] ?: null,
            !empty($d['registrar_log']) ? 1 : 0,
            (int)($d['ordem'] ?? 100),
            $d['origem_template'] ?? null,
            !empty($d['ativo']) ? 1 : 0,
        ];
    }
}
