<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class DdnsRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listar(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM ddns_contas ORDER BY apelido');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarAtivas(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM ddns_contas WHERE ativo = 1 ORDER BY apelido');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ddns_contas WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function criar(string $provedor, string $apelido, string $hostname, string $credenciaisCriptografadas): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO ddns_contas (provedor, apelido, hostname, credenciais)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$provedor, $apelido, $hostname, $credenciaisCriptografadas]);

        return (int)$this->pdo->lastInsertId();
    }

    public function atualizar(int $id, string $provedor, string $apelido, string $hostname, ?string $credenciaisCriptografadas): void
    {
        if ($credenciaisCriptografadas !== null) {
            $stmt = $this->pdo->prepare('
                UPDATE ddns_contas
                   SET provedor = ?, apelido = ?, hostname = ?, credenciais = ?
                 WHERE id = ?
            ');
            $stmt->execute([$provedor, $apelido, $hostname, $credenciaisCriptografadas, $id]);
            return;
        }

        $stmt = $this->pdo->prepare('
            UPDATE ddns_contas
               SET provedor = ?, apelido = ?, hostname = ?
             WHERE id = ?
        ');
        $stmt->execute([$provedor, $apelido, $hostname, $id]);
    }

    public function excluir(int $id): void
    {
        // sem FK entre as tabelas (padrao do projeto) -- apaga o
        // historico na mao, senao fica orfao pra sempre.
        $stmt = $this->pdo->prepare('DELETE FROM ddns_historico WHERE conta_id = ?');
        $stmt->execute([$id]);

        $stmt = $this->pdo->prepare('DELETE FROM ddns_contas WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function ativar(int $id): void
    {
        // zera ultimo_ip pra forcar uma checagem de verdade na proxima
        // atualizacao -- senao, se o IP publico nao mudou desde que a
        // conta foi desativada, o loop pula justamente quando mais
        // precisa forcar (registro pode ter expirado no provedor).
        $stmt = $this->pdo->prepare('UPDATE ddns_contas SET ativo = 1, ultimo_ip = NULL WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function desativar(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE ddns_contas SET ativo = 0 WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function registrarVerificacao(int $id, string $ip): void
    {
        $stmt = $this->pdo->prepare('UPDATE ddns_contas SET ultimo_ip = ?, ultima_verificacao_em = NOW() WHERE id = ?');
        $stmt->execute([$ip, $id]);
    }

    public function registrarAtualizacao(int $id, string $ip, bool $sucesso, string $mensagem): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE ddns_contas
               SET ultimo_ip = ?, ultima_verificacao_em = NOW(), ultima_atualizacao_em = NOW(),
                   ultimo_sucesso = ?, ultima_mensagem = ?
             WHERE id = ?
        ');
        $stmt->execute([$ip, $sucesso ? 1 : 0, $mensagem, $id]);
    }

    public function registrarHistorico(int $contaId, string $ip, bool $sucesso, string $mensagem): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO ddns_historico (conta_id, ip, sucesso, mensagem)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$contaId, $ip, $sucesso ? 1 : 0, $mensagem]);
    }

    public function listarHistorico(int $contaId, int $limite = 20): array
    {
        $limite = max(1, min($limite, 100));

        $stmt = $this->pdo->prepare("SELECT * FROM ddns_historico WHERE conta_id = ? ORDER BY id DESC LIMIT {$limite}");
        $stmt->execute([$contaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
