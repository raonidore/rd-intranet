<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Categoria de chamado -- define o setor padrão de roteamento e, ao
 * ser criada, ganha automaticamente as 4 linhas de SLA (uma por
 * prioridade) com prazos padrão, editáveis depois em
 * ChamadoSlaService. Sem isso o admin teria que configurar SLA na mão
 * pra toda categoria nova antes de conseguir abrir chamado com ela.
 */
class ChamadoCategoriaService
{
    private PDO $pdo;

    /** [prioridade => [resposta_min, resolucao_min]] -- mesmos defaults semeados pela migration pra categoria "Geral". */
    private const SLA_PADRAO = [
        'baixa' => [480, 4320],
        'media' => [240, 1440],
        'alta' => [60, 480],
        'urgente' => [15, 240],
    ];

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listar(): array
    {
        return $this->pdo->query(
            "SELECT c.*, s.nome AS setor_padrao_nome
             FROM chamados_categorias c
             LEFT JOIN chamados_setores s ON s.id = c.setor_padrao_id
             ORDER BY c.nome"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarAtivas(): array
    {
        return $this->pdo->query("SELECT * FROM chamados_categorias WHERE ativo = 1 ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM chamados_categorias WHERE id = ?');
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    /** @return array{success: bool, message: string} */
    public function criar(string $nome, ?int $setorPadraoId): array
    {
        $nome = trim($nome);

        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe o nome da categoria.'];
        }

        if ($this->nomeEmUso($nome)) {
            return ['success' => false, 'message' => 'Já existe uma categoria com esse nome.'];
        }

        $stmt = $this->pdo->prepare('INSERT INTO chamados_categorias (nome, setor_padrao_id) VALUES (?, ?)');
        $stmt->execute([$nome, $setorPadraoId ?: null]);

        $categoriaId = (int)$this->pdo->lastInsertId();

        $slaStmt = $this->pdo->prepare(
            'INSERT INTO chamados_slas (categoria_id, prioridade, tempo_primeira_resposta_min, tempo_resolucao_min) VALUES (?, ?, ?, ?)'
        );
        foreach (self::SLA_PADRAO as $prioridade => [$resposta, $resolucao]) {
            $slaStmt->execute([$categoriaId, $prioridade, $resposta, $resolucao]);
        }

        AuditService::registrar('Chamados', 'Categorias', 'Categoria "' . $nome . '" criada, com SLA padrão pras 4 prioridades.');

        return ['success' => true, 'message' => 'Categoria criada com sucesso.'];
    }

    /** @return array{success: bool, message: string} */
    public function atualizar(int $id, string $nome, ?int $setorPadraoId, bool $ativo): array
    {
        $nome = trim($nome);

        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe o nome da categoria.'];
        }

        if ($this->nomeEmUso($nome, $id)) {
            return ['success' => false, 'message' => 'Já existe uma categoria com esse nome.'];
        }

        $stmt = $this->pdo->prepare('UPDATE chamados_categorias SET nome = ?, setor_padrao_id = ?, ativo = ? WHERE id = ?');
        $stmt->execute([$nome, $setorPadraoId ?: null, $ativo ? 1 : 0, $id]);

        return ['success' => true, 'message' => 'Categoria atualizada com sucesso.'];
    }

    /** @return array{success: bool, message: string} */
    public function excluir(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM chamados WHERE categoria_id = ?');
        $stmt->execute([$id]);

        if ((int)$stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Não é possível excluir: existem chamados nessa categoria.'];
        }

        $this->pdo->prepare('DELETE FROM chamados_categorias WHERE id = ?')->execute([$id]);

        return ['success' => true, 'message' => 'Categoria removida.'];
    }

    private function nomeEmUso(string $nome, ?int $ignorarId = null): bool
    {
        $sql = 'SELECT id FROM chamados_categorias WHERE nome = ?';
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
