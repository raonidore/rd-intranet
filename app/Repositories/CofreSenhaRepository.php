<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class CofreSenhaRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /**
     * Lista visível para o usuário: segredos compartilhados (privado = 0)
     * + os privados dos quais ele próprio é dono. Nunca traz
     * senha_cifrada -- decriptação só acontece em CofreSenhaService::revelar().
     */
    public function listarVisiveis(int $usuarioId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.id, c.nome, c.categoria, c.usuario_login, c.url_host, c.observacoes,
                   c.usuario_id_dono, c.privado, c.criado_em, c.atualizado_em, u.nome AS dono_nome
            FROM cofre_senhas c
            LEFT JOIN usuarios u ON u.id = c.usuario_id_dono
            WHERE c.privado = 0 OR c.usuario_id_dono = ?
            ORDER BY c.categoria, c.nome
        ");
        $stmt->execute([$usuarioId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM cofre_senhas WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function criar(array $dados): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO cofre_senhas
                (nome, categoria, usuario_login, senha_cifrada, url_host, observacoes, usuario_id_dono, privado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $dados['nome'],
            $dados['categoria'],
            $dados['usuario_login'] ?: null,
            $dados['senha_cifrada'],
            $dados['url_host'] ?: null,
            $dados['observacoes'] ?: null,
            $dados['usuario_id_dono'],
            $dados['privado'] ? 1 : 0,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /** Não toca senha_cifrada -- ver atualizarSenha() para isso. */
    public function atualizar(int $id, array $dados): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE cofre_senhas
               SET nome = ?, categoria = ?, usuario_login = ?, url_host = ?, observacoes = ?, privado = ?
             WHERE id = ?
        ");

        return $stmt->execute([
            $dados['nome'],
            $dados['categoria'],
            $dados['usuario_login'] ?: null,
            $dados['url_host'] ?: null,
            $dados['observacoes'] ?: null,
            $dados['privado'] ? 1 : 0,
            $id,
        ]);
    }

    public function atualizarSenha(int $id, string $senhaCifrada): bool
    {
        $stmt = $this->pdo->prepare("UPDATE cofre_senhas SET senha_cifrada = ? WHERE id = ?");

        return $stmt->execute([$senhaCifrada, $id]);
    }

    public function excluir(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM cofre_senhas WHERE id = ?");

        return $stmt->execute([$id]);
    }
}
