<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Subcategoria de chamado (ex.: Software > Excel) -- segundo nível,
 * sempre preso a uma categoria. Pode ter setor próprio (senão vale o
 * da categoria) e escolher entre herdar o SLA da categoria ou usar
 * prazos próprios. As 4 linhas de prazo próprio são criadas junto com
 * a subcategoria, copiadas da categoria, pra que ligar "prazos
 * próprios" já comece de um ponto conhecido em vez de vazio.
 */
class ChamadoSubcategoriaService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /** @return array<int, array[]> [categoria_id => subcategorias] -- tela de Categorias, inclui inativas. */
    public function listarAgrupadas(): array
    {
        $linhas = $this->pdo->query(
            "SELECT sc.*, s.nome AS setor_padrao_nome, COUNT(c.id) AS total_chamados
             FROM chamados_subcategorias sc
             LEFT JOIN chamados_setores s ON s.id = sc.setor_padrao_id
             LEFT JOIN chamados c ON c.subcategoria_id = sc.id
             GROUP BY sc.id
             ORDER BY sc.nome"
        )->fetchAll(PDO::FETCH_ASSOC);

        $agrupadas = [];
        foreach ($linhas as $linha) {
            $agrupadas[(int)$linha['categoria_id']][] = $linha;
        }

        return $agrupadas;
    }

    /** @return array<int, array{id: int, nome: string}[]> Só ativas -- alimenta o select dependente da abertura. */
    public function listarAtivasAgrupadas(): array
    {
        $linhas = $this->pdo->query(
            'SELECT id, categoria_id, nome FROM chamados_subcategorias WHERE ativo = 1 ORDER BY nome'
        )->fetchAll(PDO::FETCH_ASSOC);

        $agrupadas = [];
        foreach ($linhas as $linha) {
            $agrupadas[(int)$linha['categoria_id']][] = ['id' => (int)$linha['id'], 'nome' => $linha['nome']];
        }

        return $agrupadas;
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM chamados_subcategorias WHERE id = ?');
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function contarAtivasDaCategoria(int $categoriaId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM chamados_subcategorias WHERE categoria_id = ? AND ativo = 1');
        $stmt->execute([$categoriaId]);

        return (int)$stmt->fetchColumn();
    }

    /** @return array{success: bool, message: string} */
    public function criar(int $categoriaId, string $nome, ?int $setorPadraoId): array
    {
        $nome = trim($nome);

        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe o nome da subcategoria.'];
        }

        $categoria = (new ChamadoCategoriaService())->buscar($categoriaId);
        if (!$categoria) {
            return ['success' => false, 'message' => 'Categoria inválida.'];
        }

        if ($this->nomeEmUso($categoriaId, $nome)) {
            return ['success' => false, 'message' => 'Já existe uma subcategoria com esse nome em "' . $categoria['nome'] . '".'];
        }

        $stmt = $this->pdo->prepare('INSERT INTO chamados_subcategorias (categoria_id, nome, setor_padrao_id) VALUES (?, ?, ?)');
        $stmt->execute([$categoriaId, $nome, $setorPadraoId ?: null]);

        $subcategoriaId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare(
            'INSERT INTO chamados_subcategoria_slas (subcategoria_id, prioridade, tempo_primeira_resposta_min, tempo_resolucao_min)
             SELECT ?, prioridade, tempo_primeira_resposta_min, tempo_resolucao_min FROM chamados_slas WHERE categoria_id = ?'
        )->execute([$subcategoriaId, $categoriaId]);

        return ['success' => true, 'message' => 'Subcategoria "' . $nome . '" criada em "' . $categoria['nome'] . '".'];
    }

    /** @return array{success: bool, message: string} */
    public function atualizar(int $id, string $nome, ?int $setorPadraoId, bool $slaProprio, bool $ativo): array
    {
        $nome = trim($nome);

        $subcategoria = $this->buscar($id);
        if (!$subcategoria) {
            return ['success' => false, 'message' => 'Subcategoria não encontrada.'];
        }

        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe o nome da subcategoria.'];
        }

        if ($this->nomeEmUso((int)$subcategoria['categoria_id'], $nome, $id)) {
            return ['success' => false, 'message' => 'Já existe uma subcategoria com esse nome nessa categoria.'];
        }

        $stmt = $this->pdo->prepare('UPDATE chamados_subcategorias SET nome = ?, setor_padrao_id = ?, sla_proprio = ?, ativo = ? WHERE id = ?');
        $stmt->execute([$nome, $setorPadraoId ?: null, $slaProprio ? 1 : 0, $ativo ? 1 : 0, $id]);

        return ['success' => true, 'message' => 'Subcategoria atualizada com sucesso.'];
    }

    /** @return array{success: bool, message: string} */
    public function excluir(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM chamados WHERE subcategoria_id = ?');
        $stmt->execute([$id]);

        if ((int)$stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Não é possível excluir: existem chamados nessa subcategoria. Desative-a para tirá-la da abertura.'];
        }

        $this->pdo->prepare('DELETE FROM chamados_subcategorias WHERE id = ?')->execute([$id]);

        return ['success' => true, 'message' => 'Subcategoria removida.'];
    }

    /** As 4 linhas de prazo próprio de uma subcategoria. */
    public function listarSlas(int $subcategoriaId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM chamados_subcategoria_slas WHERE subcategoria_id = ? ORDER BY FIELD(prioridade, 'urgente','alta','media','baixa')");
        $stmt->execute([$subcategoriaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, array[]> [subcategoria_id => linhas de SLA] */
    public function listarSlasAgrupados(): array
    {
        $linhas = $this->pdo->query(
            "SELECT * FROM chamados_subcategoria_slas ORDER BY subcategoria_id, FIELD(prioridade, 'urgente','alta','media','baixa')"
        )->fetchAll(PDO::FETCH_ASSOC);

        $agrupados = [];
        foreach ($linhas as $linha) {
            $agrupados[(int)$linha['subcategoria_id']][] = $linha;
        }

        return $agrupados;
    }

    /** Prazo próprio da subcategoria pra uma prioridade -- null quando ela herda da categoria. */
    public function buscarSlaProprio(int $subcategoriaId, string $prioridade): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ss.* FROM chamados_subcategoria_slas ss
             JOIN chamados_subcategorias sc ON sc.id = ss.subcategoria_id
             WHERE ss.subcategoria_id = ? AND ss.prioridade = ? AND sc.sla_proprio = 1'
        );
        $stmt->execute([$subcategoriaId, $prioridade]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    /** @return array{success: bool, message: string} */
    public function atualizarSla(int $id, int $tempoPrimeiraRespostaMin, int $tempoResolucaoMin): array
    {
        if ($tempoPrimeiraRespostaMin < 1 || $tempoResolucaoMin < 1) {
            return ['success' => false, 'message' => 'Os prazos precisam ser maiores que zero.'];
        }

        if ($tempoPrimeiraRespostaMin > $tempoResolucaoMin) {
            return ['success' => false, 'message' => 'O prazo de primeira resposta não pode ser maior que o de resolução.'];
        }

        $stmt = $this->pdo->prepare('UPDATE chamados_subcategoria_slas SET tempo_primeira_resposta_min = ?, tempo_resolucao_min = ? WHERE id = ?');
        $stmt->execute([$tempoPrimeiraRespostaMin, $tempoResolucaoMin, $id]);

        return ['success' => true, 'message' => 'SLA da subcategoria atualizado.'];
    }

    private function nomeEmUso(int $categoriaId, string $nome, ?int $ignorarId = null): bool
    {
        $sql = 'SELECT id FROM chamados_subcategorias WHERE categoria_id = ? AND nome = ?';
        $params = [$categoriaId, $nome];

        if ($ignorarId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $ignorarId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (bool)$stmt->fetch();
    }
}
