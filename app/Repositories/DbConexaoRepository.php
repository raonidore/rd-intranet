<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class DbConexaoRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listar(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, nome, host, porta, usuario, banco_padrao, ativo, criado_em
            FROM db_conexoes
            ORDER BY nome
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, nome, host, porta, usuario, senha_cifrada, banco_padrao, ativo, criado_em
            FROM db_conexoes
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function criar(array $dados): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO db_conexoes (nome, host, porta, usuario, senha_cifrada, banco_padrao)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $dados['nome'],
            $dados['host'],
            $dados['porta'],
            $dados['usuario'],
            $dados['senha_cifrada'],
            $dados['banco_padrao'] ?: null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function atualizar(int $id, array $dados): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE db_conexoes
               SET nome = ?, host = ?, porta = ?, usuario = ?, banco_padrao = ?
             WHERE id = ?
        ");

        return $stmt->execute([
            $dados['nome'],
            $dados['host'],
            $dados['porta'],
            $dados['usuario'],
            $dados['banco_padrao'] ?: null,
            $id,
        ]);
    }

    public function atualizarSenha(int $id, string $senhaCifrada): bool
    {
        $stmt = $this->pdo->prepare("UPDATE db_conexoes SET senha_cifrada = ? WHERE id = ?");

        return $stmt->execute([$senhaCifrada, $id]);
    }

    public function definirAtivo(int $id, bool $ativo): bool
    {
        $stmt = $this->pdo->prepare("UPDATE db_conexoes SET ativo = ? WHERE id = ?");

        return $stmt->execute([$ativo ? 1 : 0, $id]);
    }

    public function excluir(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM db_conexoes WHERE id = ?");

        return $stmt->execute([$id]);
    }
}
