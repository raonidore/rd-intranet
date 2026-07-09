<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class CronJobRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listar(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM cron_jobs ORDER BY nome");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarAtivos(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM cron_jobs WHERE ativo = 1 ORDER BY id");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM cron_jobs WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function criar(array $dados): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO cron_jobs (nome, descricao, expressao, usuario_execucao, comando, ativo)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $dados['nome'],
            $dados['descricao'] ?: null,
            $dados['expressao'],
            $dados['usuario_execucao'],
            $dados['comando'],
            $dados['ativo'] ? 1 : 0,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function atualizar(int $id, array $dados): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE cron_jobs
               SET nome = ?, descricao = ?, expressao = ?, usuario_execucao = ?, comando = ?, ativo = ?
             WHERE id = ?
        ");

        return $stmt->execute([
            $dados['nome'],
            $dados['descricao'] ?: null,
            $dados['expressao'],
            $dados['usuario_execucao'],
            $dados['comando'],
            $dados['ativo'] ? 1 : 0,
            $id,
        ]);
    }

    public function definirAtivo(int $id, bool $ativo): bool
    {
        $stmt = $this->pdo->prepare("UPDATE cron_jobs SET ativo = ? WHERE id = ?");

        return $stmt->execute([$ativo ? 1 : 0, $id]);
    }

    public function excluir(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM cron_jobs WHERE id = ?");

        return $stmt->execute([$id]);
    }

    public function registrarExecucao(int $id, bool $sucesso, string $saida): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE cron_jobs
               SET ultima_execucao_em = NOW(), ultima_execucao_sucesso = ?, ultima_execucao_saida = ?
             WHERE id = ?
        ");

        $stmt->execute([$sucesso ? 1 : 0, mb_substr($saida, 0, 20000), $id]);
    }
}
