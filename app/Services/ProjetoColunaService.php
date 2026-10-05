<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Colunas do quadro de um projeto -- nome, ordem e quantidade livres. Cada
 * coluna diz a "situação" das tarefas que estão nela (a fazer, em
 * andamento, aguardando terceiro, concluído); é essa situação que
 * estatísticas, atrasadas e progresso de fase leem em
 * projetos_tarefas.coluna, então o quadro pode ter quantas colunas quiser
 * sem quebrar nenhum número.
 */
class ProjetoColunaService
{
    public const SITUACOES = [
        'a_fazer' => 'A fazer (pendente)',
        'em_andamento' => 'Em andamento',
        'aguardando_terceiro' => 'Aguardando terceiro',
        'concluido' => 'Concluído',
    ];

    /** Quadro de um projeto novo (e de projeto que por algum motivo ficou sem colunas). */
    private const PADRAO = [
        ['A fazer', 'a_fazer'],
        ['Em andamento', 'em_andamento'],
        ['Aguardando terceiro', 'aguardando_terceiro'],
        ['Concluído', 'concluido'],
    ];

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /** @return array<int, array{id: int, nome: string, situacao: string, posicao: int}> em ordem; cria as padrão se o projeto não tiver nenhuma */
    public function listar(int $projetoId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, nome, situacao, posicao FROM projetos_colunas WHERE projeto_id = ? ORDER BY posicao, id');
        $stmt->execute([$projetoId]);
        $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$colunas) {
            $this->criarPadrao($projetoId);
            $stmt->execute([$projetoId]);
            $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return array_map(fn (array $c) => ['id' => (int)$c['id'], 'nome' => $c['nome'], 'situacao' => $c['situacao'], 'posicao' => (int)$c['posicao']], $colunas);
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM projetos_colunas WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** As 4 colunas de sempre; tarefas sem coluna vão pra equivalente à situação delas. */
    public function criarPadrao(int $projetoId): void
    {
        $ins = $this->pdo->prepare('INSERT INTO projetos_colunas (projeto_id, nome, situacao, posicao) VALUES (?, ?, ?, ?)');
        foreach (self::PADRAO as $posicao => [$nome, $situacao]) {
            $ins->execute([$projetoId, $nome, $situacao, $posicao]);
        }
        $this->pdo->prepare(
            'UPDATE projetos_tarefas t JOIN projetos_colunas c ON c.projeto_id = t.projeto_id AND c.situacao = t.coluna
             SET t.coluna_id = c.id WHERE t.projeto_id = ? AND t.coluna_id IS NULL'
        )->execute([$projetoId]);
    }

    /** Duplicar projeto: o novo nasce com as mesmas colunas (nome, situação, ordem). */
    public function copiar(int $deProjetoId, int $paraProjetoId): void
    {
        $ins = $this->pdo->prepare('INSERT INTO projetos_colunas (projeto_id, nome, situacao, posicao) VALUES (?, ?, ?, ?)');
        foreach ($this->listar($deProjetoId) as $coluna) {
            $ins->execute([$paraProjetoId, $coluna['nome'], $coluna['situacao'], $coluna['posicao']]);
        }
    }

    /** @return array{success: bool, message: string} */
    public function criar(int $projetoId, string $nome, string $situacao, ?int $usuarioId): array
    {
        $nome = mb_substr(trim($nome), 0, 60);
        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe o nome da coluna.'];
        }
        if (!isset(self::SITUACOES[$situacao])) {
            return ['success' => false, 'message' => 'Escolha a situação das tarefas dessa coluna.'];
        }

        $this->listar($projetoId); // garante as padrão antes de somar uma nova
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(posicao), -1) + 1 FROM projetos_colunas WHERE projeto_id = ?');
        $stmt->execute([$projetoId]);
        $this->pdo->prepare('INSERT INTO projetos_colunas (projeto_id, nome, situacao, posicao) VALUES (?, ?, ?, ?)')
            ->execute([$projetoId, $nome, $situacao, (int)$stmt->fetchColumn()]);

        (new ProjetoComentarioService())->registrarSistema($projetoId, null, 'Coluna "' . $nome . '" adicionada ao quadro.', $usuarioId, null);

        return ['success' => true, 'message' => 'Coluna "' . $nome . '" adicionada.'];
    }

    /**
     * Renomeia e/ou muda a situação. Mudou a situação: as tarefas da coluna
     * passam a ter a nova (inclusive data de conclusão).
     *
     * @return array{success: bool, message: string}
     */
    public function atualizar(int $id, string $nome, string $situacao, ?int $usuarioId): array
    {
        $coluna = $this->buscar($id);
        $nome = mb_substr(trim($nome), 0, 60);
        if (!$coluna) {
            return ['success' => false, 'message' => 'Coluna não encontrada.'];
        }
        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe o nome da coluna.'];
        }
        if (!isset(self::SITUACOES[$situacao])) {
            return ['success' => false, 'message' => 'Situação inválida.'];
        }

        $this->pdo->prepare('UPDATE projetos_colunas SET nome = ?, situacao = ? WHERE id = ?')->execute([$nome, $situacao, $id]);

        if ($situacao !== $coluna['situacao']) {
            $this->aplicarSituacao($id, $situacao);
        }
        if ($nome !== $coluna['nome']) {
            (new ProjetoComentarioService())->registrarSistema((int)$coluna['projeto_id'], null, 'Coluna "' . $coluna['nome'] . '" renomeada para "' . $nome . '".', $usuarioId, null);
        }

        return ['success' => true, 'message' => 'Coluna atualizada.'];
    }

    /** Uma casa pra esquerda (-1) ou pra direita (+1). */
    public function mover(int $id, int $direcao): array
    {
        $coluna = $this->buscar($id);
        if (!$coluna) {
            return ['success' => false, 'message' => 'Coluna não encontrada.'];
        }

        $colunas = $this->listar((int)$coluna['projeto_id']);
        $indice = array_search($id, array_column($colunas, 'id'), true);
        $vizinho = $indice + ($direcao < 0 ? -1 : 1);
        if ($indice === false || !isset($colunas[$vizinho])) {
            return ['success' => true, 'message' => 'A coluna já está na ponta.'];
        }

        [$colunas[$indice], $colunas[$vizinho]] = [$colunas[$vizinho], $colunas[$indice]];
        $upd = $this->pdo->prepare('UPDATE projetos_colunas SET posicao = ? WHERE id = ?');
        foreach ($colunas as $posicao => $c) {
            $upd->execute([$posicao, $c['id']]);
        }

        return ['success' => true, 'message' => 'Coluna movida.'];
    }

    /**
     * Exclui a coluna levando os cartões dela pra outra coluna do mesmo
     * projeto (com a situação da coluna de destino). Sempre sobra ao menos uma.
     *
     * @return array{success: bool, message: string}
     */
    public function excluir(int $id, int $destinoId, ?int $usuarioId): array
    {
        $coluna = $this->buscar($id);
        $destino = $this->buscar($destinoId);
        if (!$coluna) {
            return ['success' => false, 'message' => 'Coluna não encontrada.'];
        }
        if (count($this->listar((int)$coluna['projeto_id'])) <= 1) {
            return ['success' => false, 'message' => 'O quadro precisa de pelo menos uma coluna.'];
        }
        if (!$destino || $destinoId === $id || (int)$destino['projeto_id'] !== (int)$coluna['projeto_id']) {
            return ['success' => false, 'message' => 'Escolha para qual coluna os cartões vão.'];
        }

        $propria = !$this->pdo->inTransaction(); // dentro de outra transação, quem abriu decide
        $propria && $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('UPDATE projetos_tarefas SET coluna_id = ? WHERE coluna_id = ?')->execute([$destinoId, $id]);
            $this->aplicarSituacao($destinoId, $destino['situacao']);
            $this->pdo->prepare('DELETE FROM projetos_colunas WHERE id = ?')->execute([$id]);
            $propria && $this->pdo->commit();
        } catch (Throwable $e) {
            $propria && $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Falha ao excluir a coluna.'];
        }

        (new ProjetoComentarioService())->registrarSistema((int)$coluna['projeto_id'], null, 'Coluna "' . $coluna['nome'] . '" excluída; cartões foram para "' . $destino['nome'] . '".', $usuarioId, null);

        return ['success' => true, 'message' => 'Coluna "' . $coluna['nome'] . '" excluída.'];
    }

    /** Tarefas da coluna passam a ter a situação dela (concluída ganha/perde a data de conclusão). */
    private function aplicarSituacao(int $colunaId, string $situacao): void
    {
        $this->pdo->prepare(
            "UPDATE projetos_tarefas SET coluna = ?,
                concluida_em = CASE WHEN ? = 'concluido' THEN COALESCE(concluida_em, NOW()) ELSE NULL END
             WHERE coluna_id = ?"
        )->execute([$situacao, $situacao, $colunaId]);
    }
}
