<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Tarefa = o cartão que roda no Kanban. Pertence sempre a um projeto,
 * opcionalmente a uma fase; carrega responsáveis internos (usuarios)
 * e/ou participantes externos em tabelas N:N próprias.
 */
class ProjetoTarefaService
{
    public const COLUNAS = ['a_fazer', 'em_andamento', 'aguardando_terceiro', 'concluido'];
    private const COLUNA_LABEL = [
        'a_fazer' => 'A fazer',
        'em_andamento' => 'Em andamento',
        'aguardando_terceiro' => 'Aguardando terceiro',
        'concluido' => 'Concluído',
    ];

    /**
     * Cor do cartão -- etiqueta puramente visual (tipo Trello), não interfere
     * em status/coluna/fase. Além destas, aceita qualquer cor "#rrggbb"
     * escolhida no seletor livre.
     */
    public const CORES = [
        'rosa' => '#f4a6c1',
        'vermelho' => '#f28b82',
        'laranja' => '#f0b06e',
        'amarelo' => '#f0d878',
        'verde' => '#8fd4ac',
        'turquesa' => '#7fd3c9',
        'azul' => '#8ec9f0',
        'anil' => '#9fb4f0',
        'roxo' => '#c3a6e8',
        'cinza' => '#c4c9d0',
    ];

    /** Pinta só a faixa da lateral (como sempre foi) ou o cartão inteiro. */
    public const ESTILOS_COR = ['lateral' => 'Só a lateral', 'inteiro' => 'Card inteiro'];

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public static function colunaLabel(string $coluna): string
    {
        return self::COLUNA_LABEL[$coluna] ?? $coluna;
    }

    public static function corHex(?string $cor): ?string
    {
        if ($cor !== null && preg_match('/^#[0-9a-f]{6}$/i', $cor)) {
            return strtolower($cor);
        }

        return self::CORES[$cor] ?? null;
    }

    /** Valor aceito pro campo cor: chave pronta, "#rrggbb" ou nada. */
    public static function normalizarCor(?string $cor): ?string
    {
        $cor = trim((string)$cor);
        if (isset(self::CORES[$cor])) {
            return $cor;
        }

        return preg_match('/^#[0-9a-f]{6}$/i', $cor) ? strtolower($cor) : null;
    }

    /** Texto legível sobre o fundo (card pintado inteiro): escuro em cor clara, branco em cor escura. */
    public static function corTexto(string $hex): string
    {
        [$r, $g, $b] = array_map('hexdec', str_split(ltrim($hex, '#'), 2));

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 150 ? '#1f2328' : '#ffffff';
    }

    /** @return string[] cores livres (#rrggbb) já usadas no projeto -- aparecem na paleta pra reaproveitar */
    public function coresPersonalizadas(int $projetoId): array
    {
        $stmt = $this->pdo->prepare("SELECT DISTINCT LOWER(cor) FROM projetos_tarefas WHERE projeto_id = ? AND cor LIKE '#%' ORDER BY id DESC LIMIT 12");
        $stmt->execute([$projetoId]);

        return array_values(array_unique($stmt->fetchAll(PDO::FETCH_COLUMN)));
    }

    /**
     * @return array<int, array> tarefas do projeto agrupadas pelo id da coluna
     * do quadro (ProjetoColunaService), na ordem das colunas -- tarefa sem
     * coluna válida cai na primeira coluna de mesma situação.
     */
    public function quadro(int $projetoId): array
    {
        $colunas = (new ProjetoColunaService())->listar($projetoId);
        $stmt = $this->pdo->prepare(
            'SELECT t.*,
                    f.nome AS fase_nome,
                    (SELECT COUNT(*) FROM projetos_tarefas_responsaveis r WHERE r.tarefa_id = t.id) AS total_responsaveis,
                    (SELECT COUNT(*) FROM projetos_tarefas_externos e WHERE e.tarefa_id = t.id) AS total_externos
             FROM projetos_tarefas t
             LEFT JOIN projetos_fases f ON f.id = t.fase_id
             WHERE t.projeto_id = ?
             ORDER BY t.posicao, t.id'
        );
        $stmt->execute([$projetoId]);
        $tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $quadro = array_fill_keys(array_column($colunas, 'id'), []);
        $primeiraPorSituacao = [];
        foreach ($colunas as $c) {
            $primeiraPorSituacao[$c['situacao']] ??= $c['id'];
        }
        foreach ($tarefas as $tarefa) {
            $colunaId = (int)($tarefa['coluna_id'] ?? 0);
            if (!isset($quadro[$colunaId])) {
                $colunaId = $primeiraPorSituacao[$tarefa['coluna']] ?? $colunas[0]['id'];
            }
            $quadro[$colunaId][] = $tarefa;
        }

        return $quadro;
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.*, p.titulo AS projeto_titulo, p.area_id
             FROM projetos_tarefas t
             JOIN projetos p ON p.id = t.projeto_id
             WHERE t.id = ?'
        );
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    /** @return array<int, array> responsáveis internos + externos, num formato único pra exibir junto (usado nos cartões/detalhe). */
    public function pessoas(int $tarefaId): array
    {
        $internos = $this->pdo->prepare(
            'SELECT u.id, u.nome, u.email, "interno" AS tipo
             FROM projetos_tarefas_responsaveis r
             JOIN usuarios u ON u.id = r.usuario_id
             WHERE r.tarefa_id = ?'
        );
        $internos->execute([$tarefaId]);

        $externos = $this->pdo->prepare(
            'SELECT pe.id, pe.nome, pe.email, "externo" AS tipo
             FROM projetos_tarefas_externos e
             JOIN projetos_participantes_externos pe ON pe.id = e.participante_externo_id
             WHERE e.tarefa_id = ?'
        );
        $externos->execute([$tarefaId]);

        return array_merge($internos->fetchAll(PDO::FETCH_ASSOC), $externos->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array{success: bool, message: string, id?: int} */
    public function criar(int $projetoId, array $dados, ?int $usuarioId): array
    {
        $titulo = trim($dados['titulo'] ?? '');
        if ($titulo === '') {
            return ['success' => false, 'message' => 'Informe o título da tarefa.'];
        }

        $faseId = !empty($dados['fase_id']) ? (int)$dados['fase_id'] : null;
        $cor = self::normalizarCor($dados['cor'] ?? null);
        $estilo = isset(self::ESTILOS_COR[$dados['cor_estilo'] ?? '']) ? $dados['cor_estilo'] : 'lateral';

        // Tarefa nova entra no fim da primeira coluna do quadro.
        $primeira = (new ProjetoColunaService())->listar($projetoId)[0];
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(posicao), -1) + 1 FROM projetos_tarefas WHERE projeto_id = ? AND coluna_id = ?');
        $stmt->execute([$projetoId, $primeira['id']]);
        $posicao = (int)$stmt->fetchColumn();

        $ins = $this->pdo->prepare(
            'INSERT INTO projetos_tarefas (projeto_id, fase_id, titulo, descricao, tag, cor, cor_estilo, coluna, coluna_id, posicao, data_inicio, prazo, criado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $projetoId,
            $faseId,
            $titulo,
            trim($dados['descricao'] ?? '') ?: null,
            trim($dados['tag'] ?? '') ?: null,
            $cor,
            $estilo,
            $primeira['situacao'],
            $primeira['id'],
            $posicao,
            trim($dados['data_inicio'] ?? '') ?: null,
            trim($dados['prazo'] ?? '') ?: null,
            $usuarioId,
        ]);

        $id = (int)$this->pdo->lastInsertId();

        (new ProjetoComentarioService())->registrarSistema($projetoId, $id, 'Tarefa "' . $titulo . '" criada.', $usuarioId, null);

        return ['success' => true, 'message' => 'Tarefa criada.', 'id' => $id];
    }

    /** @return array{success: bool, message: string} */
    public function atualizar(int $id, array $dados): array
    {
        $titulo = trim($dados['titulo'] ?? '');
        if ($titulo === '') {
            return ['success' => false, 'message' => 'Informe o título da tarefa.'];
        }

        $faseId = !empty($dados['fase_id']) ? (int)$dados['fase_id'] : null;
        $cor = self::normalizarCor($dados['cor'] ?? null);
        $estilo = isset(self::ESTILOS_COR[$dados['cor_estilo'] ?? '']) ? $dados['cor_estilo'] : 'lateral';

        $stmt = $this->pdo->prepare(
            'UPDATE projetos_tarefas SET titulo = ?, descricao = ?, tag = ?, cor = ?, cor_estilo = ?, fase_id = ?, data_inicio = ?, prazo = ? WHERE id = ?'
        );
        $stmt->execute([
            $titulo,
            trim($dados['descricao'] ?? '') ?: null,
            trim($dados['tag'] ?? '') ?: null,
            $cor,
            $estilo,
            $faseId,
            trim($dados['data_inicio'] ?? '') ?: null,
            trim($dados['prazo'] ?? '') ?: null,
            $id,
        ]);

        return ['success' => true, 'message' => 'Tarefa atualizada.'];
    }

    /**
     * Chamado pelo drag-and-drop do Kanban -- move a tarefa pra
     * coluna/posição informada. Sem lock/fila: se dois usuários
     * arrastarem ao mesmo tempo, o último UPDATE que chegar vence
     * (mesmo espírito do restante do sistema, sem transação pesada
     * pra uma reordenação visual).
     *
     * @return array{success: bool, message: string}
     */
    public function mover(int $id, int $colunaId, int $novaPosicao, ?int $usuarioId): array
    {
        $tarefa = $this->buscar($id);
        if (!$tarefa) {
            return ['success' => false, 'message' => 'Tarefa não encontrada.'];
        }

        $colunas = new ProjetoColunaService();
        $destino = $colunas->buscar($colunaId);
        if (!$destino || (int)$destino['projeto_id'] !== (int)$tarefa['projeto_id']) {
            return ['success' => false, 'message' => 'Coluna inválida.'];
        }

        // A situação (o que estatísticas/atrasadas leem) vem da coluna de destino.
        $novaSituacao = $destino['situacao'];
        $concluidaEm = $tarefa['concluida_em'];
        if ($novaSituacao === 'concluido' && $tarefa['coluna'] !== 'concluido') {
            $concluidaEm = date('Y-m-d H:i:s');
        } elseif ($novaSituacao !== 'concluido') {
            $concluidaEm = null;
        }

        $stmt = $this->pdo->prepare('UPDATE projetos_tarefas SET coluna = ?, coluna_id = ?, posicao = ?, concluida_em = ? WHERE id = ?');
        $stmt->execute([$novaSituacao, $colunaId, $novaPosicao, $concluidaEm, $id]);

        if ((int)($tarefa['coluna_id'] ?? 0) !== $colunaId) {
            $origem = $tarefa['coluna_id'] ? $colunas->buscar((int)$tarefa['coluna_id']) : null;
            (new ProjetoComentarioService())->registrarSistema(
                (int)$tarefa['projeto_id'],
                $id,
                sprintf('Tarefa "%s" movida de %s para %s.', $tarefa['titulo'], $origem['nome'] ?? self::colunaLabel($tarefa['coluna']), $destino['nome']),
                $usuarioId,
                null
            );
        }

        return ['success' => true, 'message' => 'Tarefa movida.'];
    }

    /** @return array{success: bool, message: string} */
    public function excluir(int $id): array
    {
        $this->pdo->prepare('DELETE FROM projetos_tarefas WHERE id = ?')->execute([$id]);

        return ['success' => true, 'message' => 'Tarefa removida.'];
    }

    /** @return array{success: bool, message: string} */
    public function adicionarResponsavel(int $tarefaId, int $usuarioId, ?int $quemAtribuiu): array
    {
        $this->pdo->prepare('INSERT IGNORE INTO projetos_tarefas_responsaveis (tarefa_id, usuario_id) VALUES (?, ?)')->execute([$tarefaId, $usuarioId]);

        $tarefa = $this->buscar($tarefaId);
        if ($tarefa) {
            (new ProjetoNotificacaoService())->notificarAtribuicaoInterna($tarefaId, $usuarioId);
        }

        return ['success' => true, 'message' => 'Responsável adicionado.'];
    }

    /** @return array{success: bool, message: string} */
    public function removerResponsavel(int $tarefaId, int $usuarioId): array
    {
        $this->pdo->prepare('DELETE FROM projetos_tarefas_responsaveis WHERE tarefa_id = ? AND usuario_id = ?')->execute([$tarefaId, $usuarioId]);

        return ['success' => true, 'message' => 'Responsável removido.'];
    }

    /** @return array{success: bool, message: string} */
    public function adicionarExterno(int $tarefaId, int $participanteExternoId): array
    {
        $this->pdo->prepare('INSERT IGNORE INTO projetos_tarefas_externos (tarefa_id, participante_externo_id) VALUES (?, ?)')->execute([$tarefaId, $participanteExternoId]);

        (new ProjetoNotificacaoService())->notificarAtribuicaoExterna($tarefaId, $participanteExternoId);

        return ['success' => true, 'message' => 'Participante externo adicionado.'];
    }

    /** @return array{success: bool, message: string} */
    public function removerExterno(int $tarefaId, int $participanteExternoId): array
    {
        $this->pdo->prepare('DELETE FROM projetos_tarefas_externos WHERE tarefa_id = ? AND participante_externo_id = ?')->execute([$tarefaId, $participanteExternoId]);

        return ['success' => true, 'message' => 'Participante externo removido.'];
    }

    /** Progresso da fase (0-100), calculado na hora -- sem coluna própria pra não ficar dessincronizada. */
    public function progressoPorFase(int $faseId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*), SUM(coluna = "concluido") FROM projetos_tarefas WHERE fase_id = ?');
        $stmt->execute([$faseId]);
        [$total, $feitas] = $stmt->fetch(PDO::FETCH_NUM);

        if ((int)$total === 0) {
            return 0;
        }

        return (int)round((int)$feitas / (int)$total * 100);
    }

    /** @return array{total:int, atrasadas:int, aguardando_terceiro:int} resumo rápido pro cabeçalho/Modo TV. */
    public function resumo(int $projetoId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                SUM(coluna != 'concluido') AS total,
                SUM(coluna != 'concluido' AND prazo IS NOT NULL AND prazo < CURDATE()) AS atrasadas,
                SUM(coluna = 'aguardando_terceiro') AS aguardando_terceiro
             FROM projetos_tarefas WHERE projeto_id = ?"
        );
        $stmt->execute([$projetoId]);
        $linha = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total' => (int)($linha['total'] ?? 0),
            'atrasadas' => (int)($linha['atrasadas'] ?? 0),
            'aguardando_terceiro' => (int)($linha['aguardando_terceiro'] ?? 0),
        ];
    }

    /**
     * Varredura periódica (cron "projetos:verificar-prazos") -- avisa
     * responsáveis de tarefa com prazo vencendo amanhã e ainda não
     * concluída. Mesmo formato de ChamadoService::sincronizarPausaSlaTodos():
     * um SELECT de candidatos, loop, ação, devolve a contagem.
     */
    public function verificarPrazosVencendo(): int
    {
        $stmt = $this->pdo->query(
            "SELECT id FROM projetos_tarefas
             WHERE coluna != 'concluido' AND prazo = DATE_ADD(CURDATE(), INTERVAL 1 DAY)"
        );
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $notificacao = new ProjetoNotificacaoService();
        foreach ($ids as $id) {
            $notificacao->notificarPrazoVencendo((int)$id);
        }

        return count($ids);
    }

    /**
     * Dados prontos pra desenhar o Gantt em PHP+CSS (`left`/`width` em
     * %, já calculados aqui) -- a view só desenha `<div style="...">`,
     * sem nenhuma matemática de data nela. Uma linha por Fase (barra
     * larga), com as tarefas daquela fase logo abaixo, indentadas;
     * tarefas sem fase entram num grupo "Sem fase" no fim.
     *
     * "Marco": quando só dá pra saber UM ponto no tempo (fase com
     * data_inicio == data_fim_prevista, ou tarefa só com `prazo` e sem
     * `data_inicio`), vira `tipo => 'marco'` -- a view desenha um
     * losango em vez de uma barra. Sem nenhuma data utilizável, a
     * linha nem entra no resultado (não dá pra posicionar).
     *
     * @return array{inicio: string, fim: string, hoje_pct: ?float, linhas: array<int, array>}
     */
    public function gantt(int $projetoId): array
    {
        $fases = (new ProjetoFaseService())->listar($projetoId);
        $quadro = $this->quadro($projetoId);
        $tarefas = array_merge(...array_values($quadro));

        $datas = [];
        foreach ($fases as $fase) {
            if ($fase['data_inicio']) $datas[] = $fase['data_inicio'];
            if ($fase['data_fim_prevista']) $datas[] = $fase['data_fim_prevista'];
        }
        foreach ($tarefas as $tarefa) {
            if ($tarefa['data_inicio']) $datas[] = $tarefa['data_inicio'];
            if ($tarefa['prazo']) $datas[] = $tarefa['prazo'];
        }

        if (empty($datas)) {
            $inicio = date('Y-m-d');
            $fim = date('Y-m-d', strtotime('+30 days'));
        } else {
            $inicio = min($datas);
            $fim = max($datas);
            if ($inicio === $fim) {
                $fim = date('Y-m-d', strtotime($fim . ' +1 day'));
            }
        }

        $totalDias = max(1, (int)((strtotime($fim) - strtotime($inicio)) / 86400));
        $hojePct = null;
        $hoje = date('Y-m-d');
        if ($hoje >= $inicio && $hoje <= $fim) {
            $hojePct = round((strtotime($hoje) - strtotime($inicio)) / 86400 / $totalDias * 100, 2);
        }

        $barra = function (?string $de, ?string $ate) use ($inicio, $totalDias): ?array {
            $de = $de ?: $ate;
            $ate = $ate ?: $de;
            if ($de === null) {
                return null;
            }
            if ($de === $ate) {
                $left = round((strtotime($de) - strtotime($inicio)) / 86400 / $totalDias * 100, 2);
                return ['tipo' => 'marco', 'left' => max(0, min(100, $left)), 'width' => 0];
            }
            $left = round((strtotime($de) - strtotime($inicio)) / 86400 / $totalDias * 100, 2);
            $width = round((strtotime($ate) - strtotime($de)) / 86400 / $totalDias * 100, 2);
            return ['tipo' => 'barra', 'left' => max(0, min(100, $left)), 'width' => max(1, $width)];
        };

        $linhas = [];
        $tarefasSemFase = [];
        foreach ($fases as $fase) {
            $pos = $barra($fase['data_inicio'], $fase['data_fim_prevista']);
            $filhas = [];
            foreach ($tarefas as $tarefa) {
                if ((int)($tarefa['fase_id'] ?? 0) !== (int)$fase['id']) {
                    continue;
                }
                $posTarefa = $barra($tarefa['data_inicio'], $tarefa['prazo']);
                if ($posTarefa !== null) {
                    $filhas[] = array_merge($tarefa, $posTarefa);
                }
            }
            if ($pos !== null || !empty($filhas)) {
                $linhas[] = array_merge($fase, $pos ?? ['tipo' => null, 'left' => 0, 'width' => 0], ['tarefas' => $filhas]);
            }
        }
        foreach ($tarefas as $tarefa) {
            if (!empty($tarefa['fase_id'])) {
                continue;
            }
            $posTarefa = $barra($tarefa['data_inicio'], $tarefa['prazo']);
            if ($posTarefa !== null) {
                $tarefasSemFase[] = array_merge($tarefa, $posTarefa);
            }
        }
        if (!empty($tarefasSemFase)) {
            $linhas[] = ['id' => null, 'nome' => 'Sem fase', 'tipo' => null, 'left' => 0, 'width' => 0, 'tarefas' => $tarefasSemFase];
        }

        return ['inicio' => $inicio, 'fim' => $fim, 'hoje_pct' => $hojePct, 'linhas' => $linhas];
    }
}
