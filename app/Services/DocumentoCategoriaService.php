<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class DocumentoCategoriaService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listar(): array
    {
        return $this->pdo->query(
            "SELECT c.*, COUNT(d.id) AS total_documentos
             FROM documentos_categorias c
             LEFT JOIN documentos d ON d.categoria_id = c.id
             GROUP BY c.id
             ORDER BY c.nome"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarAtivas(): array
    {
        return $this->pdo->query(
            "SELECT c.*, COUNT(d.id) AS total_documentos
             FROM documentos_categorias c
             LEFT JOIN documentos d ON d.categoria_id = c.id
             WHERE c.ativo = 1
             GROUP BY c.id
             ORDER BY c.nome"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM documentos_categorias WHERE id = ?');
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    /** @return array{success: bool, message: string, id?: int} */
    public function criar(string $nome, string $descricao): array
    {
        $nome = trim($nome);
        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe o nome da categoria.'];
        }
        if ($this->nomeEmUso($nome)) {
            return ['success' => false, 'message' => 'Já existe uma categoria com esse nome.'];
        }

        $stmt = $this->pdo->prepare('INSERT INTO documentos_categorias (nome, descricao) VALUES (?, ?)');
        $stmt->execute([$nome, trim($descricao) ?: null]);

        return ['success' => true, 'message' => 'Categoria criada.', 'id' => (int)$this->pdo->lastInsertId()];
    }

    /** @return array{success: bool, message: string} */
    public function atualizar(int $id, string $nome, string $descricao, bool $ativo): array
    {
        $nome = trim($nome);
        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe o nome da categoria.'];
        }
        if ($this->nomeEmUso($nome, $id)) {
            return ['success' => false, 'message' => 'Já existe uma categoria com esse nome.'];
        }

        $stmt = $this->pdo->prepare('UPDATE documentos_categorias SET nome = ?, descricao = ?, ativo = ? WHERE id = ?');
        $stmt->execute([$nome, trim($descricao) ?: null, $ativo ? 1 : 0, $id]);

        return ['success' => true, 'message' => 'Categoria atualizada.'];
    }

    /** @return array{success: bool, message: string} */
    public function excluir(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM documentos WHERE categoria_id = ?');
        $stmt->execute([$id]);

        if ((int)$stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Essa categoria tem documentos cadastrados -- mova ou exclua os documentos antes.'];
        }

        $this->pdo->prepare('DELETE FROM documentos_categorias WHERE id = ?')->execute([$id]);

        return ['success' => true, 'message' => 'Categoria removida.'];
    }

    private function nomeEmUso(string $nome, ?int $ignorarId = null): bool
    {
        $sql = 'SELECT id FROM documentos_categorias WHERE nome = ?';
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
