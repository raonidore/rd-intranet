<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class SshConexaoRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listar(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, nome, host, porta, usuario, tipo_autenticacao, observacoes, ativo, criado_em
            FROM ssh_conexoes
            ORDER BY nome
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ssh_conexoes WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function criar(array $dados): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO ssh_conexoes
                (nome, host, porta, usuario, tipo_autenticacao, senha_cifrada, chave_privada_cifrada, chave_privada_senha_cifrada, observacoes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $dados['nome'],
            $dados['host'],
            $dados['porta'],
            $dados['usuario'],
            $dados['tipo_autenticacao'],
            $dados['senha_cifrada'],
            $dados['chave_privada_cifrada'],
            $dados['chave_privada_senha_cifrada'],
            $dados['observacoes'] ?: null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function atualizar(int $id, array $dados): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE ssh_conexoes
               SET nome = ?, host = ?, porta = ?, usuario = ?, observacoes = ?
             WHERE id = ?
        ");

        return $stmt->execute([
            $dados['nome'],
            $dados['host'],
            $dados['porta'],
            $dados['usuario'],
            $dados['observacoes'] ?: null,
            $id,
        ]);
    }

    /** Redefine a credencial -- sempre troca o tipo de autenticação inteiro (senha OU chave, nunca mistura sobra do outro tipo). */
    public function atualizarCredencial(int $id, string $tipoAutenticacao, ?string $senhaCifrada, ?string $chavePrivadaCifrada, ?string $chavePrivadaSenhaCifrada): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE ssh_conexoes
               SET tipo_autenticacao = ?, senha_cifrada = ?, chave_privada_cifrada = ?, chave_privada_senha_cifrada = ?
             WHERE id = ?
        ");

        return $stmt->execute([$tipoAutenticacao, $senhaCifrada, $chavePrivadaCifrada, $chavePrivadaSenhaCifrada, $id]);
    }

    public function definirAtivo(int $id, bool $ativo): bool
    {
        $stmt = $this->pdo->prepare("UPDATE ssh_conexoes SET ativo = ? WHERE id = ?");

        return $stmt->execute([$ativo ? 1 : 0, $id]);
    }

    public function excluir(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM ssh_conexoes WHERE id = ?");

        return $stmt->execute([$id]);
    }
}
