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

    /** Itens pessoais do usuário (cofre_id IS NULL, ele é o dono). Nunca traz senha_cifrada. */
    public function listarPessoais(int $usuarioId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.id, c.nome, c.categoria, c.cofre_id, c.usuario_login, c.url_host, c.observacoes,
                   c.usuario_id_dono, c.criado_em, c.atualizado_em
            FROM cofre_senhas c
            WHERE c.cofre_id IS NULL AND c.usuario_id_dono = ?
            ORDER BY c.categoria, c.nome
        ");
        $stmt->execute([$usuarioId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Itens de um conjunto de cofres de equipe (ids já filtrados por CofrePermissaoService::cofresVisiveis()). */
    public function listarDeCofres(array $cofreIds): array
    {
        if (empty($cofreIds)) {
            return [];
        }

        $marcadores = implode(',', array_fill(0, count($cofreIds), '?'));
        $stmt = $this->pdo->prepare("
            SELECT c.id, c.nome, c.categoria, c.cofre_id, c.usuario_login, c.url_host, c.observacoes,
                   c.usuario_id_dono, c.criado_em, c.atualizado_em, u.nome AS dono_nome
            FROM cofre_senhas c
            LEFT JOIN usuarios u ON u.id = c.usuario_id_dono
            WHERE c.cofre_id IN ({$marcadores})
            ORDER BY c.cofre_id, c.categoria, c.nome
        ");
        $stmt->execute(array_values(array_map('intval', $cofreIds)));

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
                (nome, categoria, cofre_id, usuario_login, senha_cifrada, url_host, observacoes, usuario_id_dono)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $dados['nome'],
            $dados['categoria'],
            $dados['cofre_id'],
            $dados['usuario_login'] ?: null,
            $dados['senha_cifrada'],
            $dados['url_host'] ?: null,
            $dados['observacoes'] ?: null,
            $dados['usuario_id_dono'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /** Não toca senha_cifrada nem cofre_id (mover de cofre fica fora de escopo -- só na criação) -- ver atualizarSenha() pra senha. */
    public function atualizar(int $id, array $dados): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE cofre_senhas
               SET nome = ?, categoria = ?, usuario_login = ?, url_host = ?, observacoes = ?
             WHERE id = ?
        ");

        return $stmt->execute([
            $dados['nome'],
            $dados['categoria'],
            $dados['usuario_login'] ?: null,
            $dados['url_host'] ?: null,
            $dados['observacoes'] ?: null,
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
