<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Setores DA EMPRESA de onde os chamados vêm (Financeiro, RH, Vendas...) --
 * "setor do solicitante". Não confundir com ChamadoSetorService, que são
 * as equipes que ATENDEM (Suporte Técnico, TI...). Opcional: sem nenhum
 * ativo, o campo some da abertura de chamado.
 */
class ChamadoSetorSolicitanteService
{
    private const CHAVE_OBRIGATORIO = 'chamados_setor_solicitante_obrigatorio';

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listar(): array
    {
        return $this->pdo->query(
            "SELECT ss.*, u.nome AS unidade_nome,
                    (SELECT COUNT(*) FROM chamados c WHERE c.setor_solicitante_id = ss.id) AS total_chamados
             FROM chamados_setores_solicitantes ss
             LEFT JOIN unidades u ON u.id = ss.unidade_id
             ORDER BY ss.nome, u.nome"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarAtivos(): array
    {
        return $this->pdo->query(
            'SELECT id, nome, unidade_id FROM chamados_setores_solicitantes WHERE ativo = 1 ORDER BY nome'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM chamados_setores_solicitantes WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** "Exigir na abertura pelo painel" -- só vale quando a unidade tem algum setor cadastrado. */
    public function obrigatorio(): bool
    {
        return ConfigService::get(self::CHAVE_OBRIGATORIO, '') === '1';
    }

    public function salvarObrigatorio(bool $obrigatorio): void
    {
        ConfigService::set(self::CHAVE_OBRIGATORIO, $obrigatorio ? '1' : '0');
    }

    public function existeAtivoParaUnidade(int $unidadeId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM chamados_setores_solicitantes WHERE ativo = 1 AND (unidade_id IS NULL OR unidade_id = ?) LIMIT 1');
        $stmt->execute([$unidadeId]);

        return (bool)$stmt->fetchColumn();
    }

    /** Setor válido para o chamado: ativo e da mesma unidade (ou de todas). */
    public function validoParaUnidade(int $id, int $unidadeId): bool
    {
        $setor = $this->buscar($id);

        return $setor && $setor['ativo'] && ($setor['unidade_id'] === null || (int)$setor['unidade_id'] === $unidadeId);
    }

    /** @return array{success: bool, message: string} */
    public function criar(string $nome, ?int $unidadeId): array
    {
        $nome = trim($nome);
        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe o nome do setor.'];
        }
        if ($this->nomeEmUso($nome, $unidadeId)) {
            return ['success' => false, 'message' => 'Já existe um setor com esse nome nessa unidade.'];
        }

        $this->pdo->prepare('INSERT INTO chamados_setores_solicitantes (nome, unidade_id) VALUES (?, ?)')->execute([$nome, $unidadeId]);

        return ['success' => true, 'message' => 'Setor "' . $nome . '" cadastrado.'];
    }

    /** @return array{success: bool, message: string} */
    public function atualizar(int $id, string $nome, ?int $unidadeId, bool $ativo): array
    {
        $nome = trim($nome);
        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe o nome do setor.'];
        }
        if ($this->nomeEmUso($nome, $unidadeId, $id)) {
            return ['success' => false, 'message' => 'Já existe um setor com esse nome nessa unidade.'];
        }

        $this->pdo->prepare('UPDATE chamados_setores_solicitantes SET nome = ?, unidade_id = ?, ativo = ? WHERE id = ?')
            ->execute([$nome, $unidadeId, $ativo ? 1 : 0, $id]);

        return ['success' => true, 'message' => 'Setor atualizado.'];
    }

    /** @return array{success: bool, message: string} */
    public function excluir(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM chamados WHERE setor_solicitante_id = ?');
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Há chamados com esse setor -- desative em vez de excluir, para não perder a informação.'];
        }

        $this->pdo->prepare('DELETE FROM chamados_setores_solicitantes WHERE id = ?')->execute([$id]);

        return ['success' => true, 'message' => 'Setor removido.'];
    }

    /**
     * Corrige quem cadastrou os setores da empresa como equipes de
     * atendimento: cada equipe escolhida vira um setor do solicitante com o
     * mesmo nome, os chamados dela passam a ter esse setor do solicitante e
     * vão para a equipe de destino, categorias/subcategorias que apontavam
     * para ela passam a apontar para o destino e a equipe é excluída.
     * $moverTodos: também coloca no destino os chamados das equipes que
     * ficaram (ex.: TI) e os sem equipe.
     *
     * @param int[] $setorIds
     * @return array{success: bool, message: string}
     */
    public function converterEquipes(array $setorIds, int $destinoId, bool $moverTodos): array
    {
        $setorIds = array_values(array_unique(array_filter(array_map('intval', $setorIds))));
        $setores = new ChamadoSetorService();

        $destino = $setores->buscar($destinoId);
        if (!$destino) {
            return ['success' => false, 'message' => 'Escolha a equipe de atendimento que vai receber os chamados.'];
        }
        if (in_array($destinoId, $setorIds, true)) {
            return ['success' => false, 'message' => 'A equipe de destino ("' . $destino['nome'] . '") não pode estar entre as convertidas.'];
        }
        if (!$setorIds && !$moverTodos) {
            return ['success' => false, 'message' => 'Marque pelo menos um setor para converter.'];
        }

        $convertidos = [];
        $chamadosConvertidos = 0;

        $this->pdo->beginTransaction();
        try {
            foreach ($setorIds as $setorId) {
                $setor = $setores->buscar($setorId);
                if (!$setor) {
                    continue;
                }

                $solicitanteId = $this->idPorNome($setor['nome']);
                if ($solicitanteId === null) {
                    $this->pdo->prepare('INSERT INTO chamados_setores_solicitantes (nome, ativo) VALUES (?, ?)')
                        ->execute([$setor['nome'], $setor['ativo'] ? 1 : 0]);
                    $solicitanteId = (int)$this->pdo->lastInsertId();
                }

                $stmt = $this->pdo->prepare(
                    'UPDATE chamados SET setor_solicitante_id = COALESCE(setor_solicitante_id, ?), setor_id = ? WHERE setor_id = ?'
                );
                $stmt->execute([$solicitanteId, $destinoId, $setorId]);
                $chamadosConvertidos += $stmt->rowCount();

                $this->pdo->prepare('UPDATE chamados_categorias SET setor_padrao_id = ? WHERE setor_padrao_id = ?')->execute([$destinoId, $setorId]);
                $this->pdo->prepare('UPDATE chamados_subcategorias SET setor_padrao_id = ? WHERE setor_padrao_id = ?')->execute([$destinoId, $setorId]);
                $this->pdo->prepare('DELETE FROM chamados_setores WHERE id = ?')->execute([$setorId]);

                $convertidos[] = $setor['nome'];
            }

            $outrosMovidos = 0;
            if ($moverTodos) {
                $stmt = $this->pdo->prepare('UPDATE chamados SET setor_id = ? WHERE setor_id IS NULL OR setor_id != ?');
                $stmt->execute([$destinoId, $destinoId]);
                $outrosMovidos = $stmt->rowCount();
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Falha ao converter os setores: ' . $e->getMessage()];
        }

        $mensagem = $convertidos
            ? count($convertidos) . ' setor(es) agora são setores do solicitante (' . implode(', ', $convertidos) . "); {$chamadosConvertidos} chamado(s) ganharam o setor do solicitante"
            : 'Nenhum setor convertido';
        $mensagem .= $moverTodos ? "; {$outrosMovidos} outro(s) chamado(s) movidos" : '';
        $mensagem .= ' -- todos foram para "' . $destino['nome'] . '".';

        AuditService::registrar('Chamados', 'Converter setores', $mensagem);

        return ['success' => true, 'message' => $mensagem];
    }

    private function idPorNome(string $nome): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM chamados_setores_solicitantes WHERE nome = ? AND unidade_id IS NULL LIMIT 1');
        $stmt->execute([$nome]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int)$id;
    }

    private function nomeEmUso(string $nome, ?int $unidadeId, ?int $ignorarId = null): bool
    {
        $sql = 'SELECT id FROM chamados_setores_solicitantes WHERE nome = ? AND unidade_id <=> ?';
        $params = [$nome, $unidadeId];
        if ($ignorarId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $ignorarId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (bool)$stmt->fetch();
    }
}
