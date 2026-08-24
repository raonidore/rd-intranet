<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class ChamadoExternoCategoriaService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listar(): array
    {
        return $this->pdo->query('SELECT * FROM chamados_externos_categorias ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarAtivas(): array
    {
        return $this->pdo->query('SELECT * FROM chamados_externos_categorias WHERE ativo = 1 ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{success: bool, message: string, id?: int} */
    public function criar(string $nome): array
    {
        $nome = trim($nome);
        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe o nome da categoria.'];
        }
        if ($this->nomeEmUso($nome)) {
            return ['success' => false, 'message' => 'Essa categoria já existe.'];
        }

        $stmt = $this->pdo->prepare('INSERT INTO chamados_externos_categorias (nome) VALUES (?)');
        $stmt->execute([$nome]);

        return ['success' => true, 'message' => 'Categoria cadastrada.', 'id' => (int)$this->pdo->lastInsertId()];
    }

    /** @return array{success: bool, message: string} */
    public function atualizar(int $id, string $nome, bool $ativo): array
    {
        $nome = trim($nome);
        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe o nome da categoria.'];
        }
        if ($this->nomeEmUso($nome, $id)) {
            return ['success' => false, 'message' => 'Essa categoria já existe.'];
        }

        $stmt = $this->pdo->prepare('UPDATE chamados_externos_categorias SET nome = ?, ativo = ? WHERE id = ?');
        $stmt->execute([$nome, $ativo ? 1 : 0, $id]);

        return ['success' => true, 'message' => 'Categoria atualizada.'];
    }

    /** @return array{success: bool, message: string} */
    public function excluir(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM chamados_externos WHERE categoria_id = ?');
        $stmt->execute([$id]);

        if ((int)$stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Essa categoria está em uso por chamados -- desative em vez de excluir.'];
        }

        $this->pdo->prepare('DELETE FROM chamados_externos_categorias WHERE id = ?')->execute([$id]);

        return ['success' => true, 'message' => 'Categoria removida.'];
    }

    private function nomeEmUso(string $nome, ?int $ignorarId = null): bool
    {
        $sql = 'SELECT id FROM chamados_externos_categorias WHERE nome = ?';
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
