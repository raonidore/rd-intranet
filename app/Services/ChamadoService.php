<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Núcleo do módulo Chamados -- abrir, atribuir (fila -> em_atendimento,
 * mesma corrida evitada do WhatsApp), responder (nota interna vs.
 * resposta pública) e mudar status. Prazo de SLA é calculado na
 * abertura como "agora + minutos configurados" -- não implementa pausa
 * fina por fora-de-expediente (isso exigiria um motor de "minutos
 * úteis" pulando janelas fechadas, escopo maior que o pedido original;
 * fica como possível evolução futura).
 */
class ChamadoService
{
    private PDO $pdo;

    public const PRIORIDADES = ['baixa' => 'Baixa', 'media' => 'Média', 'alta' => 'Alta', 'urgente' => 'Urgente'];

    public const STATUS = [
        'fila' => 'Na fila',
        'em_atendimento' => 'Em atendimento',
        'aguardando_cliente' => 'Aguardando cliente',
        'resolvido' => 'Resolvido',
        'fechado' => 'Fechado',
    ];

    private const SELECT_ENRIQUECIDO = "
        SELECT c.*, cat.nome AS categoria_nome, s.nome AS setor_nome,
               u.nome AS unidade_nome, u.sigla AS unidade_sigla,
               sol.nome AS solicitante_nome, sol.email AS solicitante_email, sol.telefone AS solicitante_telefone,
               us.nome AS usuario_nome,
               a.codigo_patrimonio AS ativo_codigo, a.nome AS ativo_nome
        FROM chamados c
        JOIN chamados_categorias cat ON cat.id = c.categoria_id
        LEFT JOIN chamados_setores s ON s.id = c.setor_id
        JOIN unidades u ON u.id = c.unidade_id
        JOIN chamados_solicitantes sol ON sol.id = c.solicitante_id
        LEFT JOIN usuarios us ON us.id = c.usuario_id
        LEFT JOIN ativos a ON a.id = c.ativo_id
    ";

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /** @return array{success: bool, message: string, id?: int} */
    public function abrir(array $post, string $canal = 'painel'): array
    {
        if (!in_array($canal, ['painel', 'email', 'whatsapp', 'portal'], true)) {
            $canal = 'painel';
        }

        $titulo = trim($post['titulo'] ?? '');
        $descricao = trim($post['descricao'] ?? '');
        $categoriaId = (int)($post['categoria_id'] ?? 0);
        $unidadeId = (int)($post['unidade_id'] ?? 0);
        $prioridade = isset(self::PRIORIDADES[$post['prioridade'] ?? '']) ? $post['prioridade'] : 'media';
        $nomeSolicitante = trim($post['solicitante_nome'] ?? '');

        if ($titulo === '') {
            return ['success' => false, 'message' => 'Informe um título para o chamado.'];
        }
        if ($descricao === '') {
            return ['success' => false, 'message' => 'Descreva o problema.'];
        }
        if ($nomeSolicitante === '') {
            return ['success' => false, 'message' => 'Informe o nome do solicitante.'];
        }

        $categoria = (new ChamadoCategoriaService())->buscar($categoriaId);
        if (!$categoria) {
            return ['success' => false, 'message' => 'Categoria inválida.'];
        }

        if (!(new UnidadeService())->buscar($unidadeId)) {
            return ['success' => false, 'message' => 'Unidade inválida.'];
        }

        $email = trim($post['solicitante_email'] ?? '') ?: null;
        $telefone = trim($post['solicitante_telefone'] ?? '') ?: null;
        if ($email === null && $telefone === null) {
            return ['success' => false, 'message' => 'Informe pelo menos um contato do solicitante (e-mail ou telefone).'];
        }

        $solicitante = (new ChamadoSolicitanteService())->buscarOuCriar($nomeSolicitante, $email, $telefone, $unidadeId);

        $setorId = !empty($post['setor_id']) ? (int)$post['setor_id'] : ($categoria['setor_padrao_id'] ?: null);

        $ativoId = !empty($post['ativo_id']) ? (int)$post['ativo_id'] : null;

        [$slaResposta, $slaResolucao] = $this->calcularPrazos($categoriaId, $prioridade);

        $stmt = $this->pdo->prepare(
            "INSERT INTO chamados
             (titulo, descricao, categoria_id, setor_id, unidade_id, ativo_id, solicitante_id, prioridade, canal_abertura, aguardando_resposta, sla_resposta_prazo, sla_resolucao_prazo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)"
        );
        $stmt->execute([
            $titulo, $descricao, $categoriaId, $setorId, $unidadeId, $ativoId, $solicitante['id'], $prioridade, $canal, $slaResposta, $slaResolucao,
        ]);

        $id = (int)$this->pdo->lastInsertId();

        $this->registrarHistorico($id, 'status', null, 'fila', (int)($_SESSION['usuario']['id'] ?? 0) ?: null);

        AuditService::registrar('Chamados', 'Abertura', "Chamado #{$id} \"{$titulo}\" aberto para {$nomeSolicitante}.");

        return ['success' => true, 'message' => 'Chamado #' . $id . ' aberto com sucesso.', 'id' => $id];
    }

    private function calcularPrazos(int $categoriaId, string $prioridade): array
    {
        $sla = (new ChamadoSlaService())->buscar($categoriaId, $prioridade);

        if (!$sla) {
            return [null, null];
        }

        $resposta = date('Y-m-d H:i:s', strtotime('+' . (int)$sla['tempo_primeira_resposta_min'] . ' minutes'));
        $resolucao = date('Y-m-d H:i:s', strtotime('+' . (int)$sla['tempo_resolucao_min'] . ' minutes'));

        return [$resposta, $resolucao];
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT_ENRIQUECIDO . ' WHERE c.id = ?');
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    /** @param int[]|null $setorIds null = vê tudo (admin); [] = só a fila geral (sem setor); senão, os setores do usuário + fila geral. */
    public function listarFila(?array $setorIds): array
    {
        $sql = self::SELECT_ENRIQUECIDO . " WHERE c.status = 'fila'";
        $params = [];

        if ($setorIds !== null) {
            if (empty($setorIds)) {
                $sql .= ' AND c.setor_id IS NULL';
            } else {
                $marcadores = implode(',', array_fill(0, count($setorIds), '?'));
                $sql .= " AND (c.setor_id IN ({$marcadores}) OR c.setor_id IS NULL)";
                $params = $setorIds;
            }
        }

        $sql .= ' ORDER BY c.aberto_em ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarDoUsuario(int $usuarioId): array
    {
        $stmt = $this->pdo->prepare(
            self::SELECT_ENRIQUECIDO . " WHERE c.usuario_id = ? AND c.status NOT IN ('resolvido','fechado') ORDER BY c.ultima_mensagem_em DESC"
        );
        $stmt->execute([$usuarioId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarEncerradosDoUsuario(int $usuarioId, int $limite = 100): array
    {
        $stmt = $this->pdo->prepare(
            self::SELECT_ENRIQUECIDO . " WHERE c.usuario_id = ? AND c.status IN ('resolvido','fechado') ORDER BY c.fechado_em DESC, c.resolvido_em DESC LIMIT ?"
        );
        $stmt->bindValue(1, $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{success: bool, message: string} */
    public function assumir(int $chamadoId, int $usuarioId): array
    {
        // Mesma condição "AND status = 'fila'" no UPDATE que já evita
        // corrida na Fila do WhatsApp: dois cliques simultâneos, só o
        // primeiro casa a linha.
        $stmt = $this->pdo->prepare(
            "UPDATE chamados SET status = 'em_atendimento', usuario_id = ?, atribuido_em = NOW() WHERE id = ? AND status = 'fila'"
        );
        $stmt->execute([$usuarioId, $chamadoId]);

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Esse chamado não está mais na fila (outra pessoa já deve ter assumido).'];
        }

        $this->registrarHistorico($chamadoId, 'usuario_id', null, (string)$usuarioId, $usuarioId);

        return ['success' => true, 'message' => 'Chamado assumido.'];
    }

    /** @return array{success: bool, message: string} */
    public function mudarStatus(int $chamadoId, string $novoStatus, int $usuarioId): array
    {
        if (!isset(self::STATUS[$novoStatus])) {
            return ['success' => false, 'message' => 'Status inválido.'];
        }

        $chamado = $this->buscar($chamadoId);
        if (!$chamado) {
            return ['success' => false, 'message' => 'Chamado não encontrado.'];
        }

        $campos = ['status = ?'];
        $params = [$novoStatus];

        if ($novoStatus === 'resolvido' && $chamado['status'] !== 'resolvido') {
            $campos[] = 'resolvido_em = NOW()';
        }
        if ($novoStatus === 'fechado') {
            $campos[] = 'fechado_em = NOW()';
        }
        if ($novoStatus === 'fila') {
            $campos[] = 'usuario_id = NULL, atribuido_em = NULL';
        }
        // Reabertura (de resolvido/fechado pra em_atendimento) limpa as marcas de encerramento.
        if (in_array($chamado['status'], ['resolvido', 'fechado'], true) && !in_array($novoStatus, ['resolvido', 'fechado'], true)) {
            $campos[] = 'resolvido_em = NULL, fechado_em = NULL';
        }

        $sql = 'UPDATE chamados SET ' . implode(', ', $campos) . ' WHERE id = ?';
        $params[] = $chamadoId;

        $this->pdo->prepare($sql)->execute($params);

        $this->registrarHistorico($chamadoId, 'status', $chamado['status'], $novoStatus, $usuarioId);

        if ($novoStatus === 'resolvido' && $chamado['status'] !== 'resolvido') {
            (new ChamadoAvaliacaoService())->perguntar($chamado);
        }

        return ['success' => true, 'message' => 'Status atualizado para "' . self::STATUS[$novoStatus] . '".'];
    }

    /** @return array{success: bool, message: string} */
    public function responder(int $chamadoId, string $conteudo, string $tipo, ?int $usuarioId): array
    {
        $conteudo = trim($conteudo);
        if ($conteudo === '') {
            return ['success' => false, 'message' => 'Escreva alguma coisa antes de enviar.'];
        }
        if (!in_array($tipo, ['interna', 'publica'], true)) {
            return ['success' => false, 'message' => 'Tipo de comentário inválido.'];
        }

        $chamado = $this->buscar($chamadoId);
        if (!$chamado) {
            return ['success' => false, 'message' => 'Chamado não encontrado.'];
        }

        $stmt = $this->pdo->prepare('INSERT INTO chamados_comentarios (chamado_id, usuario_id, tipo, conteudo) VALUES (?, ?, ?, ?)');
        $stmt->execute([$chamadoId, $usuarioId, $tipo, $conteudo]);

        $primeiraResposta = $tipo === 'publica' && $chamado['primeira_resposta_em'] === null;

        $campos = ['ultima_mensagem_em = NOW()'];
        if ($tipo === 'publica') {
            $campos[] = 'aguardando_resposta = 0';
        }
        if ($primeiraResposta) {
            $campos[] = 'primeira_resposta_em = NOW()';
        }

        $this->pdo->prepare('UPDATE chamados SET ' . implode(', ', $campos) . ' WHERE id = ?')->execute([$chamadoId]);

        if ($tipo === 'publica' && !empty($chamado['solicitante_email'])) {
            $this->notificarSolicitantePorEmail($chamado, $conteudo);
        }

        return ['success' => true, 'message' => 'Enviado.'];
    }

    /**
     * Resposta vinda do Portal do Solicitante -- sempre pública (quem
     * não é da equipe não tem como mandar nota interna) e sempre marca
     * aguardando_resposta=1 de novo (é a equipe que fica devendo
     * resposta agora, o oposto de quando um atendente responde). Sai
     * de "aguardando cliente" pra "em atendimento" sozinho, já que
     * quem estava faltando responder acabou de fazer isso.
     *
     * @return array{success: bool, message: string}
     */
    public function responderComoSolicitante(int $chamadoId, string $conteudo): array
    {
        $conteudo = trim($conteudo);
        if ($conteudo === '') {
            return ['success' => false, 'message' => 'Escreva alguma coisa antes de enviar.'];
        }

        $chamado = $this->buscar($chamadoId);
        if (!$chamado) {
            return ['success' => false, 'message' => 'Chamado não encontrado.'];
        }
        if (in_array($chamado['status'], ['resolvido', 'fechado'], true)) {
            return ['success' => false, 'message' => 'Esse chamado já foi encerrado -- abra um novo chamado se precisar.'];
        }

        $stmt = $this->pdo->prepare("INSERT INTO chamados_comentarios (chamado_id, usuario_id, tipo, conteudo) VALUES (?, NULL, 'publica', ?)");
        $stmt->execute([$chamadoId, $conteudo]);

        $campos = ['ultima_mensagem_em = NOW()', 'aguardando_resposta = 1'];
        if ($chamado['status'] === 'aguardando_cliente') {
            $campos[] = "status = 'em_atendimento'";
        }

        $this->pdo->prepare('UPDATE chamados SET ' . implode(', ', $campos) . ' WHERE id = ?')->execute([$chamadoId]);

        return ['success' => true, 'message' => 'Resposta enviada.'];
    }

    public function listarDoSolicitante(int $solicitanteId): array
    {
        $stmt = $this->pdo->prepare(self::SELECT_ENRIQUECIDO . ' WHERE c.solicitante_id = ? ORDER BY c.aberto_em DESC');
        $stmt->execute([$solicitanteId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function notificarSolicitantePorEmail(array $chamado, string $conteudo): void
    {
        $assunto = 'Chamado #' . $chamado['id'] . ' -- ' . $chamado['titulo'];
        $corpo = '<p>Olá, ' . htmlspecialchars($chamado['solicitante_nome']) . '!</p>'
            . '<p>Uma nova resposta foi adicionada ao seu chamado <strong>#' . (int)$chamado['id'] . ' -- ' . htmlspecialchars($chamado['titulo']) . '</strong>:</p>'
            . '<blockquote>' . nl2br(htmlspecialchars($conteudo)) . '</blockquote>';

        (new EmailService())->enviar($chamado['solicitante_email'], $assunto, $corpo);
    }

    public function comentarios(int $chamadoId, bool $incluirInternas = true): array
    {
        $sql = "SELECT co.*, u.nome AS usuario_nome
                FROM chamados_comentarios co
                LEFT JOIN usuarios u ON u.id = co.usuario_id
                WHERE co.chamado_id = ?";
        if (!$incluirInternas) {
            $sql .= " AND co.tipo = 'publica'";
        }
        $sql .= ' ORDER BY co.id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$chamadoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function historico(int $chamadoId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT h.*, u.nome AS usuario_nome FROM chamados_historico h LEFT JOIN usuarios u ON u.id = h.usuario_id WHERE h.chamado_id = ? ORDER BY h.id ASC"
        );
        $stmt->execute([$chamadoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function registrarHistorico(int $chamadoId, string $campo, ?string $valorAnterior, ?string $valorNovo, ?int $usuarioId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO chamados_historico (chamado_id, campo, valor_anterior, valor_novo, usuario_id) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$chamadoId, $campo, $valorAnterior, $valorNovo, $usuarioId]);
    }

    /** Quantos chamados desse usuário estão em atendimento e ainda sem resposta pública -- badge do menu e alerta sonoro. */
    public function contarAguardandoResposta(int $usuarioId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM chamados WHERE status = 'em_atendimento' AND usuario_id = ? AND aguardando_resposta = 1"
        );
        $stmt->execute([$usuarioId]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Maior id de chamado que entrou no estado "aguardando resposta"
     * pra esse usuário -- gatilho do alerta sonoro (mesmo raciocínio
     * do WhatsApp: contagem sozinha não detecta um chamado novo se
     * outro já estava pendente; usar o id crescente detecta sempre).
     */
    public function ultimoIdAguardandoResposta(int $usuarioId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(MAX(id), 0) FROM chamados WHERE status = 'em_atendimento' AND usuario_id = ? AND aguardando_resposta = 1"
        );
        $stmt->execute([$usuarioId]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Round-robin por carga: chamado parado na fila há mais tempo que
     * o configurado vai pro atendente do setor com MENOS chamados em
     * atendimento agora -- chamado("rd chamados:distribuir", cron
     * a cada 5min). Não mexe em setor sem nenhum atendente vinculado
     * (nada pra distribuir) nem quando a distribuição automática está
     * desligada em Configurações.
     *
     * @return array{total: int, atribuidos: int}
     */
    public function distribuirAutomaticamente(): array
    {
        $config = new ChamadoConfigService();

        if (!$config->distribuicaoAutomaticaAtiva()) {
            return ['total' => 0, 'atribuidos' => 0];
        }

        $minutos = $config->distribuicaoAutomaticaMinutos();

        $stmt = $this->pdo->prepare(
            "SELECT id, setor_id FROM chamados WHERE status = 'fila' AND setor_id IS NOT NULL AND aberto_em <= DATE_SUB(NOW(), INTERVAL ? MINUTE)"
        );
        $stmt->execute([$minutos]);
        $pendentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $setorService = new ChamadoSetorService();
        $atribuidos = 0;

        foreach ($pendentes as $item) {
            $usuarioIds = $setorService->idsUsuariosDoSetor((int)$item['setor_id']);
            if (empty($usuarioIds)) {
                continue;
            }

            $usuarioEscolhido = $this->usuarioComMenosCarga($usuarioIds);
            $resultado = $this->assumir((int)$item['id'], $usuarioEscolhido);

            if ($resultado['success']) {
                $atribuidos++;
                $this->registrarHistorico((int)$item['id'], 'distribuicao_automatica', null, (string)$usuarioEscolhido, null);
            }
        }

        return ['total' => count($pendentes), 'atribuidos' => $atribuidos];
    }

    /** @param int[] $usuarioIds */
    private function usuarioComMenosCarga(array $usuarioIds): int
    {
        $marcadores = implode(',', array_fill(0, count($usuarioIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT u.id, COUNT(c.id) AS carga
             FROM usuarios u
             LEFT JOIN chamados c ON c.usuario_id = u.id AND c.status = 'em_atendimento'
             WHERE u.id IN ({$marcadores})
             GROUP BY u.id
             ORDER BY carga ASC, u.id ASC
             LIMIT 1"
        );
        $stmt->execute($usuarioIds);

        return (int)$stmt->fetchColumn();
    }
}
