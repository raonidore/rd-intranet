<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Projeto = a iniciativa em si (ex: "Assessoria de TI -- Cliente X"),
 * sempre dentro de uma Área. Espelha ChamadoExternoService na forma
 * (CRUD simples + timeline + anexo), mas some com a barra de visão:
 * quem não é admin do módulo só enxerga projeto de área onde é
 * gestor, ou onde tem pelo menos uma tarefa atribuída -- ver
 * visivelPara(), chamado por todo controller antes de listar/exibir.
 */
class ProjetoService
{
    private const STATUS_VALIDOS = ['planejamento', 'em_andamento', 'pausado', 'concluido', 'cancelado'];
    private const STATUS_LABEL = [
        'planejamento' => 'Planejamento',
        'em_andamento' => 'Em andamento',
        'pausado' => 'Pausado',
        'concluido' => 'Concluído',
        'cancelado' => 'Cancelado',
    ];
    private const PRIORIDADES_VALIDAS = ['baixa', 'media', 'alta', 'urgente'];

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUS_LABEL[$status] ?? $status;
    }

    /** @return array<string,string> */
    public static function statusLabelTodos(): array
    {
        return self::STATUS_LABEL;
    }

    /**
     * Regra central de visibilidade (3 camadas aprovadas): admin do
     * módulo vê tudo; gestor de área vê tudo da(s) área(s) onde
     * gerencia; quem só participa vê os projetos onde tem ao menos
     * uma tarefa atribuída. Sem nenhum dos três, lista vem vazia.
     *
     * @param array{status?:string, area_id?:int} $filtros
     */
    public function visivelPara(int $usuarioId, bool $ehAdmin, array $filtros = []): array
    {
        $sql = 'SELECT p.*, a.nome AS area_nome FROM projetos p JOIN projetos_areas a ON a.id = p.area_id WHERE p.excluido_em IS NULL';
        $params = [];

        if (!$ehAdmin) {
            $areasGeridas = (new ProjetoAreaService())->areasGeridasPor($usuarioId);
            $condicoes = [];

            if (!empty($areasGeridas)) {
                $placeholders = implode(',', array_fill(0, count($areasGeridas), '?'));
                $condicoes[] = "p.area_id IN ({$placeholders})";
                foreach ($areasGeridas as $areaId) {
                    $params[] = $areaId;
                }
            }

            $condicoes[] = 'p.id IN (SELECT pt.projeto_id FROM projetos_tarefas pt JOIN projetos_tarefas_responsaveis r ON r.tarefa_id = pt.id WHERE r.usuario_id = ?)';
            $params[] = $usuarioId;

            $sql .= ' AND (' . implode(' OR ', $condicoes) . ')';
        }

        if (!empty($filtros['status'])) {
            $sql .= ' AND p.status = ?';
            $params[] = $filtros['status'];
        }
        if (!empty($filtros['area_id'])) {
            $sql .= ' AND p.area_id = ?';
            $params[] = (int)$filtros['area_id'];
        }

        $sql .= ' ORDER BY FIELD(p.status, "em_andamento","planejamento","pausado","concluido","cancelado"), p.atualizado_em DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Projetos ativos de uma área -- usado pelo Modo TV (não passa por visivelPara(), o token já é o controle de acesso). */
    public function ativosPorArea(int $areaId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, a.nome AS area_nome FROM projetos p
             JOIN projetos_areas a ON a.id = p.area_id
             WHERE p.area_id = ? AND p.excluido_em IS NULL AND p.status IN ('planejamento','em_andamento')
             ORDER BY p.atualizado_em DESC"
        );
        $stmt->execute([$areaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, a.nome AS area_nome
             FROM projetos p
             JOIN projetos_areas a ON a.id = p.area_id
             WHERE p.id = ?'
        );
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    /** Admin do módulo OU gestor da área do projeto -- gate de editar/excluir/status/gerenciar fase. */
    public function podeGerenciar(array $projeto, int $usuarioId, bool $ehAdmin): bool
    {
        if ($ehAdmin) {
            return true;
        }

        return (new ProjetoAreaService())->ehGestor((int)$projeto['area_id'], $usuarioId);
    }

    /**
     * Gate de VISUALIZAÇÃO (mais permissivo que podeGerenciar) -- admin,
     * gestor da área OU quem tem ao menos uma tarefa atribuída nesse
     * projeto específico. Todo controller que recebe um id de projeto
     * vindo de fora (GET/POST) precisa passar por aqui antes de mostrar
     * ou aceitar ação -- senão a regra de 3 camadas vira só decoração
     * no menu, sem valer nada contra um id digitado na mão.
     */
    public function ehVisivelPara(array $projeto, int $usuarioId, bool $ehAdmin): bool
    {
        if ($this->podeGerenciar($projeto, $usuarioId, $ehAdmin)) {
            return true;
        }

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM projetos_tarefas pt
             JOIN projetos_tarefas_responsaveis r ON r.tarefa_id = pt.id
             WHERE pt.projeto_id = ? AND r.usuario_id = ?'
        );
        $stmt->execute([$projeto['id'], $usuarioId]);

        return (int)$stmt->fetchColumn() > 0;
    }

    /** @return array{success: bool, message: string, id?: int} */
    public function criar(array $dados, ?int $usuarioId): array
    {
        $titulo = trim($dados['titulo'] ?? '');
        $areaId = (int)($dados['area_id'] ?? 0);

        if ($titulo === '') {
            return ['success' => false, 'message' => 'Informe o título do projeto.'];
        }
        if (!$areaId) {
            return ['success' => false, 'message' => 'Selecione a área.'];
        }

        $prioridade = in_array($dados['prioridade'] ?? '', self::PRIORIDADES_VALIDAS, true) ? $dados['prioridade'] : 'media';
        $usaFases = !empty($dados['usa_fases']) ? 1 : 0;

        $stmt = $this->pdo->prepare(
            'INSERT INTO projetos (area_id, titulo, descricao, cliente, prioridade, usa_fases, data_inicio, data_fim_prevista, criado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $areaId,
            $titulo,
            trim($dados['descricao'] ?? '') ?: null,
            trim($dados['cliente'] ?? '') ?: null,
            $prioridade,
            $usaFases,
            trim($dados['data_inicio'] ?? '') ?: null,
            trim($dados['data_fim_prevista'] ?? '') ?: null,
            $usuarioId,
        ]);

        $id = (int)$this->pdo->lastInsertId();

        (new ProjetoComentarioService())->registrarSistema($id, null, 'Projeto criado.', $usuarioId, null);

        return ['success' => true, 'message' => 'Projeto criado.', 'id' => $id];
    }

    /** @return array{success: bool, message: string} */
    public function atualizar(int $id, array $dados): array
    {
        $titulo = trim($dados['titulo'] ?? '');
        if ($titulo === '') {
            return ['success' => false, 'message' => 'Informe o título do projeto.'];
        }

        $prioridade = in_array($dados['prioridade'] ?? '', self::PRIORIDADES_VALIDAS, true) ? $dados['prioridade'] : 'media';
        $usaFases = !empty($dados['usa_fases']) ? 1 : 0;

        $stmt = $this->pdo->prepare(
            'UPDATE projetos SET titulo = ?, descricao = ?, cliente = ?, prioridade = ?, usa_fases = ?, data_inicio = ?, data_fim_prevista = ? WHERE id = ?'
        );
        $stmt->execute([
            $titulo,
            trim($dados['descricao'] ?? '') ?: null,
            trim($dados['cliente'] ?? '') ?: null,
            $prioridade,
            $usaFases,
            trim($dados['data_inicio'] ?? '') ?: null,
            trim($dados['data_fim_prevista'] ?? '') ?: null,
            $id,
        ]);

        return ['success' => true, 'message' => 'Projeto atualizado.'];
    }

    /** @return array{success: bool, message: string} */
    public function mudarStatus(int $id, string $novoStatus, ?int $usuarioId): array
    {
        if (!in_array($novoStatus, self::STATUS_VALIDOS, true)) {
            return ['success' => false, 'message' => 'Status inválido.'];
        }

        $projeto = $this->buscar($id);
        if (!$projeto) {
            return ['success' => false, 'message' => 'Projeto não encontrado.'];
        }
        if ($projeto['status'] === $novoStatus) {
            return ['success' => true, 'message' => 'Status já era esse.'];
        }

        $this->pdo->prepare('UPDATE projetos SET status = ? WHERE id = ?')->execute([$novoStatus, $id]);

        (new ProjetoComentarioService())->registrarSistema(
            $id,
            null,
            sprintf('Status alterado de "%s" para "%s".', self::statusLabel($projeto['status']), self::statusLabel($novoStatus)),
            $usuarioId,
            null
        );

        return ['success' => true, 'message' => 'Status atualizado.'];
    }

    /**
     * Não apaga de verdade -- manda pra lixeira (30 dias pra restaurar
     * antes da purga automática de `purgarExpirados()`). O hard-delete
     * de verdade só acontece em excluirDefinitivo().
     *
     * @return array{success: bool, message: string}
     */
    public function excluir(int $id, ?int $usuarioId): array
    {
        $this->pdo->prepare('UPDATE projetos SET excluido_em = NOW(), excluido_por = ? WHERE id = ?')->execute([$usuarioId, $id]);

        return ['success' => true, 'message' => 'Projeto movido para a lixeira. Fica lá por 30 dias antes de ser removido de vez.'];
    }

    /** @return array{success: bool, message: string} */
    public function restaurar(int $id): array
    {
        $this->pdo->prepare('UPDATE projetos SET excluido_em = NULL, excluido_por = NULL WHERE id = ?')->execute([$id]);

        return ['success' => true, 'message' => 'Projeto restaurado.'];
    }

    /** Hard-delete de verdade -- usado pelo botão "Excluir definitivo" da lixeira e pela purga automática. */
    public function excluirDefinitivo(int $id): array
    {
        foreach ((new ProjetoAnexoService())->porProjeto($id) as $anexo) {
            if ($anexo['anexo_origem'] === 'upload') {
                @unlink(ProjetoAnexoService::caminhoCompletoUpload($anexo['anexo_caminho']));
            }
        }

        $this->pdo->prepare('DELETE FROM projetos WHERE id = ?')->execute([$id]);

        return ['success' => true, 'message' => 'Projeto removido em definitivo.'];
    }

    /** @return array<int, array> projetos na lixeira, com quantos dias faltam antes da purga automática. */
    public function listarLixeira(): array
    {
        $stmt = $this->pdo->query(
            "SELECT p.*, a.nome AS area_nome, u.nome AS excluido_por_nome,
                    GREATEST(0, DATEDIFF(DATE_ADD(p.excluido_em, INTERVAL 30 DAY), NOW())) AS dias_restantes
             FROM projetos p
             JOIN projetos_areas a ON a.id = p.area_id
             LEFT JOIN usuarios u ON u.id = p.excluido_por
             WHERE p.excluido_em IS NOT NULL
             ORDER BY p.excluido_em DESC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Varredura periódica (cron "projetos:purgar-lixeira") -- remove
     * em definitivo quem está na lixeira há mais de 30 dias. Mesmo
     * formato de ProjetoTarefaService::verificarPrazosVencendo().
     */
    public function purgarExpirados(): int
    {
        $stmt = $this->pdo->query(
            "SELECT id FROM projetos WHERE excluido_em IS NOT NULL AND excluido_em < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($ids as $id) {
            $this->excluirDefinitivo((int)$id);
        }

        return count($ids);
    }

    /**
     * "Modelo de projeto reutilizável" -- clona o projeto e a
     * estrutura de fases (mesma ordem/nome/datas em branco). Tarefas
     * não são clonadas de propósito -- só a estrutura de fases é o
     * que dá trabalho de recriar manualmente.
     *
     * @return array{success: bool, message: string, id?: int}
     */
    public function duplicar(int $id, string $novoTitulo, ?int $usuarioId): array
    {
        $original = $this->buscar($id);
        if (!$original) {
            return ['success' => false, 'message' => 'Projeto não encontrado.'];
        }

        $novoTitulo = trim($novoTitulo) ?: ($original['titulo'] . ' (cópia)');

        $stmt = $this->pdo->prepare(
            'INSERT INTO projetos (area_id, titulo, descricao, cliente, prioridade, usa_fases, criado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $original['area_id'],
            $novoTitulo,
            $original['descricao'],
            $original['cliente'],
            $original['prioridade'],
            $original['usa_fases'],
            $usuarioId,
        ]);

        $novoId = (int)$this->pdo->lastInsertId();

        $faseService = new ProjetoFaseService();
        foreach ($faseService->listar($id) as $fase) {
            $faseService->criar($novoId, $fase['nome'], (int)$fase['ordem']);
        }

        (new ProjetoComentarioService())->registrarSistema($novoId, null, 'Projeto duplicado a partir de "' . $original['titulo'] . '".', $usuarioId, null);

        return ['success' => true, 'message' => 'Projeto "' . $novoTitulo . '" criado a partir do modelo.', 'id' => $novoId];
    }
}
