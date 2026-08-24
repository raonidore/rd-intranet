<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/** Catálogo de "Tipo de serviço" -- lista fechada, cadastrada por quem administra Fornecedores, em vez de texto livre digitado toda vez. */
class FornecedorTipoServicoService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listar(): array
    {
        return $this->pdo->query('SELECT * FROM fornecedor_tipos_servico ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarAtivos(): array
    {
        return $this->pdo->query('SELECT * FROM fornecedor_tipos_servico WHERE ativo = 1 ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{success: bool, message: string, id?: int} */
    public function criar(string $nome): array
    {
        $nome = trim($nome);
        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe o nome do tipo de serviço.'];
        }
        if ($this->nomeEmUso($nome)) {
            return ['success' => false, 'message' => 'Esse tipo de serviço já existe.'];
        }

        $stmt = $this->pdo->prepare('INSERT INTO fornecedor_tipos_servico (nome) VALUES (?)');
        $stmt->execute([$nome]);

        return ['success' => true, 'message' => 'Tipo de serviço cadastrado.', 'id' => (int)$this->pdo->lastInsertId()];
    }

    /** @return array{success: bool, message: string} */
    public function atualizar(int $id, string $nome, bool $ativo): array
    {
        $nome = trim($nome);
        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe o nome do tipo de serviço.'];
        }
        if ($this->nomeEmUso($nome, $id)) {
            return ['success' => false, 'message' => 'Esse tipo de serviço já existe.'];
        }

        $stmt = $this->pdo->prepare('UPDATE fornecedor_tipos_servico SET nome = ?, ativo = ? WHERE id = ?');
        $stmt->execute([$nome, $ativo ? 1 : 0, $id]);

        return ['success' => true, 'message' => 'Tipo de serviço atualizado.'];
    }

    /** @return array{success: bool, message: string} */
    public function excluir(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM fornecedores WHERE tipo_servico_id = ?');
        $stmt->execute([$id]);

        if ((int)$stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Esse tipo de serviço está em uso por fornecedores -- desative em vez de excluir.'];
        }

        $this->pdo->prepare('DELETE FROM fornecedor_tipos_servico WHERE id = ?')->execute([$id]);

        return ['success' => true, 'message' => 'Tipo de serviço removido.'];
    }

    private function nomeEmUso(string $nome, ?int $ignorarId = null): bool
    {
        $sql = 'SELECT id FROM fornecedor_tipos_servico WHERE nome = ?';
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
