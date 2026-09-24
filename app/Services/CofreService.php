<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/** CRUD de cofres de equipe -- peça leve, sem Repository, mesmo estilo de GrupoService. */
class CofreService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /** Lista todos os cofres com contagem de itens -- tela de gestão. */
    public function listar(): array
    {
        return $this->pdo->query(
            "SELECT c.*, u.nome AS criado_por_nome, COUNT(cs.id) AS total_itens
             FROM cofres c
             LEFT JOIN usuarios u ON u.id = c.criado_por
             LEFT JOIN cofre_senhas cs ON cs.cofre_id = c.id
             GROUP BY c.id
             ORDER BY c.nome"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array[] cofres cujo id está em $ids (usado pra montar a listagem de uso do usuário). */
    public function buscarVarios(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT * FROM cofres WHERE id IN ({$marcadores}) ORDER BY nome");
        $stmt->execute(array_values(array_map('intval', $ids)));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cofres WHERE id = ?');
        $stmt->execute([$id]);

        $cofre = $stmt->fetch(PDO::FETCH_ASSOC);

        return $cofre ?: null;
    }

    /**
     * Já concede as 3 permissões ao criador -- senão o cofre nasce sem
     * ninguém com permissão de uso (quem gerencia a estrutura de cofres
     * pode nem ter o módulo seguranca_cofre_senhas, já que gestão e uso
     * são módulos separados).
     * @return array{success: bool, message: string}
     */
    public function criar(string $nome, string $descricao, int $criadoPor): array
    {
        $nome = trim($nome);
        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe o nome do cofre.'];
        }
        if ($this->nomeEmUso($nome)) {
            return ['success' => false, 'message' => 'Já existe um cofre com esse nome.'];
        }

        $stmt = $this->pdo->prepare('INSERT INTO cofres (nome, descricao, criado_por) VALUES (?, ?, ?)');
        $stmt->execute([$nome, trim($descricao) ?: null, $criadoPor]);

        $cofreId = (int)$this->pdo->lastInsertId();

        (new CofrePermissaoService())->salvarDoCofre($cofreId, [[
            'sujeito_tipo' => 'usuario',
            'sujeito_id' => $criadoPor,
            'pode_visualizar' => true,
            'pode_editar' => true,
            'pode_excluir' => true,
        ]]);

        return ['success' => true, 'message' => 'Cofre criado com sucesso.'];
    }

    /** @return array{success: bool, message: string} */
    public function atualizar(int $id, string $nome, string $descricao): array
    {
        $nome = trim($nome);
        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe o nome do cofre.'];
        }
        if ($this->nomeEmUso($nome, $id)) {
            return ['success' => false, 'message' => 'Já existe um cofre com esse nome.'];
        }

        $stmt = $this->pdo->prepare('UPDATE cofres SET nome = ?, descricao = ? WHERE id = ?');
        $stmt->execute([$nome, trim($descricao) ?: null, $id]);

        return ['success' => true, 'message' => 'Cofre atualizado com sucesso.'];
    }

    /** Bloqueia com mensagem amigável se o cofre ainda tiver senhas dentro (o FK também bloqueia, isso só dá a mensagem certa). */
    public function excluir(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM cofre_senhas WHERE cofre_id = ?');
        $stmt->execute([$id]);

        if ((int)$stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Esse cofre tem senhas cadastradas -- mova ou exclua os itens antes de excluir o cofre.'];
        }

        $this->pdo->prepare('DELETE FROM cofres WHERE id = ?')->execute([$id]);

        return ['success' => true, 'message' => 'Cofre removido.'];
    }

    private function nomeEmUso(string $nome, ?int $ignorarId = null): bool
    {
        $sql = 'SELECT id FROM cofres WHERE nome = ?';
        $params = [$nome];

        if ($ignorarId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $ignorarId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (bool)$stmt->fetch();
    }
}
