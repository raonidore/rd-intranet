<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Fase = etapa opcional de um projeto (Levantamento, Implantação...),
 * dá a visão de linha do tempo. Progresso de cada fase é calculado na
 * hora (tarefas concluídas / total), sem coluna própria -- ver
 * progressoPorFase() em ProjetoTarefaService.
 */
class ProjetoFaseService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listar(int $projetoId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM projetos_fases WHERE projeto_id = ? ORDER BY ordem, id');
        $stmt->execute([$projetoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM projetos_fases WHERE id = ?');
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    /** @return array{success: bool, message: string, id?: int} */
    public function criar(int $projetoId, string $nome, ?int $ordem = null, ?string $dataInicio = null, ?string $dataFim = null): array
    {
        $nome = trim($nome);
        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe o nome da fase.'];
        }

        if ($ordem === null) {
            $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(ordem), 0) + 1 FROM projetos_fases WHERE projeto_id = ?');
            $stmt->execute([$projetoId]);
            $ordem = (int)$stmt->fetchColumn();
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO projetos_fases (projeto_id, nome, ordem, data_inicio, data_fim_prevista) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$projetoId, $nome, $ordem, $dataInicio ?: null, $dataFim ?: null]);

        return ['success' => true, 'message' => 'Fase adicionada.', 'id' => (int)$this->pdo->lastInsertId()];
    }

    /** @return array{success: bool, message: string} */
    public function atualizar(int $id, string $nome, int $ordem, ?string $dataInicio, ?string $dataFim): array
    {
        $nome = trim($nome);
        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe o nome da fase.'];
        }

        $stmt = $this->pdo->prepare('UPDATE projetos_fases SET nome = ?, ordem = ?, data_inicio = ?, data_fim_prevista = ? WHERE id = ?');
        $stmt->execute([$nome, $ordem, $dataInicio ?: null, $dataFim ?: null, $id]);

        return ['success' => true, 'message' => 'Fase atualizada.'];
    }

    /** @return array{success: bool, message: string} */
    public function excluir(int $id): array
    {
        $this->pdo->prepare('DELETE FROM projetos_fases WHERE id = ?')->execute([$id]);

        return ['success' => true, 'message' => 'Fase removida -- as tarefas dela continuam no projeto, soltas.'];
    }
}
