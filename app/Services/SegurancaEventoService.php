<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Eventos do módulo anti-ransomware -- gravados na hora em que o agente
 * detecta (endpoint próprio, fora do ciclo de checkin) e notificados por
 * e-mail/WhatsApp conforme o padrão global. A decisão de isolar a rede
 * fica em SegurancaIsolamentoService, chamado daqui.
 */
class SegurancaEventoService
{
    public const TIPOS_AGENTE = [
        'CANARY_TRIGGERED' => 'Arquivo-isca modificado',
        'SHADOW_COPY_DELETE_ATTEMPT' => 'Shadow copies apagadas',
        'BACKUP_DELETE_ATTEMPT' => 'Tentativa de apagar backups/recuperação',
        'MASS_FILE_CHANGE' => 'Mudança em massa de arquivos',
        // Agente 1.0.47+: surto quase só de exclusões, sem nenhum sinal de
        // criptografia (mover pra outra unidade, apagar pasta) -- sempre INFO.
        'MASS_FILE_DELETE' => 'Exclusão/movimentação em massa',
        'RANSOM_NOTE_CREATED' => 'Nota de resgate criada',
    ];

    public const TIPOS_SERVIDOR = [
        'NETWORK_ISOLATION_APPLIED' => 'Rede isolada',
        'NETWORK_ISOLATION_REMOVED' => 'Isolamento removido',
        'NETWORK_ISOLATION_CANCELLED' => 'Isolamento cancelado',
    ];

    public const ACOES_AGENTE = ['nenhuma', 'processo_encerrado'];

    // Proteção da VPS do cliente contra enxurrada (ex: FIM disparando em
    // loop numa máquina com sync de nuvem mal configurado).
    private const MAX_EVENTOS_JANELA = 30;
    private const JANELA_MINUTOS = 10;
    private const INTERVALO_NOTIFICACAO_MINUTOS = 10;

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public static function rotuloTipo(string $tipo): string
    {
        return self::TIPOS_AGENTE[$tipo] ?? self::TIPOS_SERVIDOR[$tipo] ?? $tipo;
    }

    /** @return array{success: bool, message?: string, id?: int} */
    public function registrarDoAgente(int $ativoId, array $payload): array
    {
        $tipo = (string)($payload['tipo'] ?? '');
        if (!isset(self::TIPOS_AGENTE[$tipo])) {
            return ['success' => false, 'message' => 'Tipo de evento inválido.'];
        }

        $severidade = strtoupper((string)($payload['severidade'] ?? 'WARNING'));
        if (!in_array($severidade, ['CRITICAL', 'WARNING', 'INFO'], true)) {
            $severidade = 'WARNING';
        }

        $detalhes = is_array($payload['detalhes'] ?? null) ? $payload['detalhes'] : [];
        $severidade = $this->rebaixarRodizioShadowDeAgenteAntigo($tipo, $severidade, $detalhes);

        if ($this->contarRecentes($ativoId) >= self::MAX_EVENTOS_JANELA) {
            return ['success' => true, 'message' => 'Limite de eventos da janela atingido -- descartado.'];
        }

        $acao = (string)($payload['acao_automatica'] ?? 'nenhuma');
        if (!in_array($acao, self::ACOES_AGENTE, true)) {
            $acao = 'nenhuma';
        }
        $ocorridoEm = strtotime((string)($payload['ocorrido_em'] ?? '')) ?: time();

        $id = $this->inserir($ativoId, $tipo, $severidade, $this->montarResumo($tipo, $detalhes), $detalhes, $acao, $ocorridoEm);

        $ativo = (new AtivoService())->buscar($ativoId);
        AuditService::registrar('Segurança', 'Evento anti-ransomware', sprintf(
            '%s (%s) em %s: %s',
            self::rotuloTipo($tipo),
            $severidade,
            $ativo['codigo_patrimonio'] ?? "ativo #{$ativoId}",
            $this->montarResumo($tipo, $detalhes)
        ));

        if ($severidade === 'CRITICAL') {
            $decisao = (new SegurancaIsolamentoService())->reagirAEvento($ativoId, $id);
            $this->notificar($ativoId, $tipo, $severidade, $this->montarResumo($tipo, $detalhes), $decisao, $detalhes, $id);
        }

        return ['success' => true, 'id' => $id];
    }

    /**
     * Agente anterior ao 1.0.50 marca como CRITICAL (e isola) o rodízio normal de
     * shadow copies: a rotina cria a cópia do dia e o Windows descarta as mais
     * antigas no mesmo minuto (MDA-PC-0012, Maurílio, 30/09: 6 -> 5 com 1 nova e
     * 2 descartadas). Os números do próprio evento mostram isso: saíram mais
     * cópias do que a contagem caiu = alguma foi criada junto. Se ainda sobrou
     * pelo menos metade, vira WARNING (alerta sem isolar). Apagar tudo continua
     * CRITICAL. Agente 1.0.50+ já faz essa análise (com as datas) e manda "novas".
     */
    private function rebaixarRodizioShadowDeAgenteAntigo(string $tipo, string $severidade, array &$detalhes): string
    {
        if ($tipo !== 'SHADOW_COPY_DELETE_ATTEMPT' || $severidade !== 'CRITICAL' || array_key_exists('novas', $detalhes)
            || !isset($detalhes['contagem_anterior'], $detalhes['contagem_atual'], $detalhes['sem_explicacao'])) {
            return $severidade;
        }

        $anterior = (int)$detalhes['contagem_anterior'];
        $atual = (int)$detalhes['contagem_atual'];
        $saiuNoMinimo = (int)($detalhes['temporarias'] ?? 0) + (int)($detalhes['expiradas'] ?? 0) + (int)$detalhes['sem_explicacao'];
        $criadasJunto = $saiuNoMinimo - ($anterior - $atual);

        $fracao = (float)(new SegurancaRegrasService())->dados()['shadow']['fracao_minima_restante'];
        if ($criadasJunto >= 1 && $atual >= 1 && $atual >= $anterior * $fracao) {
            $detalhes['rebaixado_pelo_servidor'] = 'rodízio provável: ' . $criadasJunto . ' cópia(s) criada(s) no mesmo intervalo e ainda restam ' . $atual . ' de ' . $anterior;
            return 'WARNING';
        }

        return $severidade;
    }

    public function registrarDoServidor(int $ativoId, string $tipo, string $resumo, array $detalhes = []): int
    {
        return $this->inserir($ativoId, $tipo, 'INFO', $resumo, $detalhes, 'nenhuma', time());
    }

    public function listarDoAtivo(int $ativoId, int $limite = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM ativos_eventos_seguranca WHERE ativo_id = ? ORDER BY recebido_em DESC, id DESC LIMIT ' . max(1, $limite)
        );
        $stmt->execute([$ativoId]);

        return $this->anexarSaidas(array_map(function (array $e) {
            $e['detalhes'] = json_decode((string)$e['detalhes'], true) ?: [];
            return $e;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC)));
    }

    /**
     * Eventos de todas as máquinas -- Central de Segurança.
     * Filtros: dias (1-365), severidade, tipo, abertos (só críticos/avisos sem resolução).
     */
    public function listarTodos(array $filtros, int $limite = 300): array
    {
        $where = ['e.recebido_em >= (NOW() - INTERVAL ? DAY)'];
        $params = [max(1, min(365, (int)($filtros['dias'] ?? 7)))];

        if (in_array($filtros['severidade'] ?? '', ['CRITICAL', 'WARNING', 'INFO'], true)) {
            $where[] = 'e.severidade = ?';
            $params[] = $filtros['severidade'];
        }
        if (isset(self::TIPOS_AGENTE[$filtros['tipo'] ?? '']) || isset(self::TIPOS_SERVIDOR[$filtros['tipo'] ?? ''])) {
            $where[] = 'e.tipo = ?';
            $params[] = $filtros['tipo'];
        }
        if (!empty($filtros['abertos'])) {
            $where[] = "e.resolvido_em IS NULL AND e.severidade <> 'INFO'";
        }
        if (isset(self::RESOLUCOES[$filtros['resolucao'] ?? ''])) {
            $where[] = 'e.resolucao = ?';
            $params[] = $filtros['resolucao'];
        }
        if (!empty($filtros['ativo_id'])) {
            $where[] = 'e.ativo_id = ?';
            $params[] = (int)$filtros['ativo_id'];
        }

        $stmt = $this->pdo->prepare(
            'SELECT e.*, a.codigo_patrimonio, a.nome AS ativo_nome
             FROM ativos_eventos_seguranca e
             JOIN ativos a ON a.id = e.ativo_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY e.recebido_em DESC, e.id DESC LIMIT ' . max(1, $limite)
        );
        $stmt->execute($params);

        return $this->anexarSaidas(array_map(function (array $e) {
            $e['detalhes'] = json_decode((string)$e['detalhes'], true) ?: [];
            return $e;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC)));
    }

    /** Números do topo da Central de Segurança. */
    public function resumoGeral(): array
    {
        return $this->pdo->query(
            "SELECT
                SUM(severidade = 'CRITICAL' AND resolvido_em IS NULL) AS criticos_abertos,
                SUM(severidade = 'WARNING' AND resolvido_em IS NULL) AS avisos_abertos,
                SUM(isolamento_pendente_ate IS NOT NULL AND resolvido_em IS NULL) AS isolamentos_pendentes,
                SUM(severidade <> 'INFO' AND recebido_em >= (NOW() - INTERVAL 7 DAY)) AS deteccoes_7d,
                COUNT(DISTINCT CASE WHEN severidade <> 'INFO' AND recebido_em >= (NOW() - INTERVAL 7 DAY) THEN ativo_id END) AS maquinas_7d
             FROM ativos_eventos_seguranca"
        )->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Eventos de isolamento guardam o id da solicitação de PowerShell que
     * aplicou/removeu o firewall -- anexa a saída dela, pra ver no histórico
     * o que de fato rodou na máquina (e se deu erro).
     */
    public function anexarSaidas(array $eventos): array
    {
        $ids = array_values(array_unique(array_filter(array_map(fn ($e) => (int)($e['detalhes']['solicitacao_id'] ?? 0), $eventos))));
        if (!$ids) {
            return $eventos;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, status, respondido_em, resultado FROM ativos_solicitacoes WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'
        );
        $stmt->execute($ids);

        $saidas = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            $resultado = json_decode((string)$linha['resultado'], true);
            $erro = is_array($resultado) ? trim((string)($resultado['erro'] ?? '')) : '';
            // PowerShell manda barra de progresso como CLIXML no stderr -- não é erro de verdade.
            if (str_starts_with($erro, '#< CLIXML')) {
                $erro = '';
            }
            $saidas[(int)$linha['id']] = [
                'status' => $linha['status'],
                'respondido_em' => $linha['respondido_em'],
                'saida' => is_array($resultado) ? trim((string)($resultado['saida'] ?? '')) : '',
                'erro' => $erro,
            ];
        }

        foreach ($eventos as &$evento) {
            $sid = (int)($evento['detalhes']['solicitacao_id'] ?? 0);
            $evento['execucao'] = $saidas[$sid] ?? null;
        }

        return $eventos;
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ativos_eventos_seguranca WHERE id = ?');
        $stmt->execute([$id]);
        $evento = $stmt->fetch(PDO::FETCH_ASSOC);

        return $evento ?: null;
    }

    public const RESOLUCOES = [
        'resolvido' => 'Resolvido',
        'falso_positivo' => 'Falso positivo',
        'ataque_confirmado' => 'Ataque confirmado',
    ];

    public function marcarResolvido(int $id, string $usuario, string $resolucao = 'resolvido', ?string $nota = null): void
    {
        $resolucao = isset(self::RESOLUCOES[$resolucao]) ? $resolucao : 'resolvido';
        $nota = $nota !== null ? mb_substr(trim($nota), 0, 255) : null;

        $stmt = $this->pdo->prepare(
            'UPDATE ativos_eventos_seguranca
             SET resolvido_em = NOW(), resolvido_por = ?, resolucao = ?, resolucao_nota = ?, isolamento_pendente_ate = NULL
             WHERE id = ? AND resolvido_em IS NULL'
        );
        $stmt->execute([$usuario, $resolucao, $nota ?: null, $id]);
    }

    /**
     * Falsos positivos agrupados (tipo e máquina) nos últimos $dias -- aba
     * "Exceções e falsos positivos" da Central, pra ver onde ajustar.
     */
    public function resumoFalsosPositivos(int $dias = 90): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.tipo, e.ativo_id, a.codigo_patrimonio, a.nome AS ativo_nome,
                    COUNT(*) AS total, MAX(e.recebido_em) AS ultimo
             FROM ativos_eventos_seguranca e
             JOIN ativos a ON a.id = e.ativo_id
             WHERE e.resolucao = 'falso_positivo' AND e.recebido_em >= (NOW() - INTERVAL ? DAY)
             GROUP BY e.tipo, e.ativo_id, a.codigo_patrimonio, a.nome
             ORDER BY total DESC, ultimo DESC"
        );
        $stmt->execute([max(1, $dias)]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Totais por resolução nos últimos $dias (inclui abertos). */
    public function totaisPorResolucao(int $dias = 90): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(resolucao, IF(resolvido_em IS NULL, 'aberto', 'resolvido')) AS resolucao, COUNT(*) AS total
             FROM ativos_eventos_seguranca
             WHERE severidade <> 'INFO' AND recebido_em >= (NOW() - INTERVAL ? DAY)
             GROUP BY 1"
        );
        $stmt->execute([max(1, $dias)]);

        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'total', 'resolucao');
    }

    /** Prazo calculado pelo MySQL (NOW()), o mesmo relógio usado em processarPendentes() -- PHP e MySQL podem estar em fusos diferentes. */
    public function atualizarAcao(int $id, string $acao, ?int $pendenteEmMinutos = null): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE ativos_eventos_seguranca
             SET acao_automatica = ?, isolamento_pendente_ate = IF(? IS NULL, NULL, NOW() + INTERVAL ? MINUTE)
             WHERE id = ?'
        );
        $stmt->execute([$acao, $pendenteEmMinutos, (int)$pendenteEmMinutos, $id]);
    }

    /** Quantos eventos críticos ainda não resolvidos a máquina tem -- badge da aba Segurança. */
    public function contarAbertosCriticos(int $ativoId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM ativos_eventos_seguranca
             WHERE ativo_id = ? AND severidade = 'CRITICAL' AND resolvido_em IS NULL"
        );
        $stmt->execute([$ativoId]);

        return (int)$stmt->fetchColumn();
    }

    private function inserir(int $ativoId, string $tipo, string $severidade, string $resumo, array $detalhes, string $acao, int $ocorridoEm): int
    {
        $json = json_encode($detalhes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        if (strlen($json) > 20000) {
            $json = json_encode(['truncado' => true, 'resumo' => mb_substr($json, 0, 19000)], JSON_UNESCAPED_UNICODE) ?: '{}';
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO ativos_eventos_seguranca (ativo_id, tipo, severidade, resumo, detalhes, acao_automatica, ocorrido_em)
             VALUES (?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?))'
        );
        $stmt->execute([$ativoId, $tipo, $severidade, mb_substr($resumo, 0, 255), $json, $acao, $ocorridoEm]);

        return (int)$this->pdo->lastInsertId();
    }

    private function contarRecentes(int $ativoId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM ativos_eventos_seguranca WHERE ativo_id = ? AND recebido_em >= (NOW() - INTERVAL ' . self::JANELA_MINUTOS . ' MINUTE)'
        );
        $stmt->execute([$ativoId]);

        return (int)$stmt->fetchColumn();
    }

    private function montarResumo(string $tipo, array $d): string
    {
        $processo = !empty($d['processo']) ? " -- processo: {$d['processo']}" . (!empty($d['pid']) ? " (PID {$d['pid']})" : '') : '';

        return match ($tipo) {
            'CANARY_TRIGGERED' => 'Arquivo-isca ' . ($d['mudanca'] ?? 'alterado') . ': ' . ($d['caminho'] ?? '?') . $processo,
            // Agente 1.0.30+ não vê comando nenhum -- só conta as shadow
            // copies a cada minuto. Dizer "comando detectado" aqui levou a
            // procurar um comando que nunca existiu (Maurílio, 2026-09-26).
            'SHADOW_COPY_DELETE_ATTEMPT' => isset($d['contagem_anterior'])
                ? sprintf('Shadow copies caíram de %d para %d em menos de 1 minuto (detectado pela contagem -- o agente não identifica quem apagou)', (int)$d['contagem_anterior'], (int)($d['contagem_atual'] ?? 0))
                : 'Comando detectado: ' . mb_substr((string)($d['linha_comando'] ?? '?'), 0, 150) . $processo,
            'BACKUP_DELETE_ATTEMPT' => 'Comando detectado: ' . mb_substr((string)($d['linha_comando'] ?? '?'), 0, 150) . $processo,
            'MASS_FILE_CHANGE' => (int)($d['eventos'] ?? 0) . ' arquivos alterados em ' . (int)($d['janela_segundos'] ?? 0) . 's em ' . ($d['pasta'] ?? '?')
                . (!empty($d['motivo']) ? ' -- ' . $d['motivo'] : '')
                . (!empty($d['extensoes_novas']) ? ' -- extensões novas: ' . implode(', ', array_slice((array)$d['extensoes_novas'], 0, 5)) : ''),
            'MASS_FILE_DELETE' => (int)($d['excluidos'] ?? 0) . ' arquivos excluídos/movidos em ' . (int)($d['janela_segundos'] ?? 0) . 's em ' . ($d['pasta'] ?? '?')
                . (!empty($d['motivo']) ? ' -- ' . $d['motivo'] : ''),
            'RANSOM_NOTE_CREATED' => 'Nota de resgate conhecida "' . ($d['nota_resgate'] ?? '?') . '" criada em várias pastas (' . ($d['pasta'] ?? '?') . ')',
            default => self::rotuloTipo($tipo),
        };
    }

    /** Não repete aviso da mesma máquina+tipo dentro de INTERVALO_NOTIFICACAO_MINUTOS -- o portal continua registrando tudo. */
    private function notificar(int $ativoId, string $tipo, string $severidade, string $resumo, string $decisao, array $detalhes = [], ?int $eventoId = null): void
    {
        $chave = "seguranca_ultima_notificacao_{$ativoId}_{$tipo}";
        $ultima = (int)(ConfigService::get($chave, '0') ?? 0);
        if (time() - $ultima < self::INTERVALO_NOTIFICACAO_MINUTOS * 60) {
            return;
        }
        ConfigService::set($chave, (string)time());

        $ativo = (new AtivoService())->buscar($ativoId) ?? ['id' => $ativoId];
        $padrao = (new SegurancaModuloService())->padrao();

        // Cliente, servidor, máquina e links -- a TI recebe alertas de
        // vários clientes, cada um com o próprio RD Intranet.
        $alerta = SegurancaAlertaService::montar($ativo, $tipo, $severidade, $resumo, $decisao, $detalhes, $eventoId);
        $texto = $alerta['texto'];

        foreach (EmailService::normalizarLista($padrao['alerta_emails']) as $email) {
            try {
                (new EmailService())->enviar($email, $alerta['assunto'], $alerta['html'], $alerta['imagens']);
            } catch (\Throwable) {
                // falha de e-mail não pode impedir o registro/isolamento
            }
        }

        foreach (preg_split('/[\s,;]+/', $padrao['alerta_whatsapp'], -1, PREG_SPLIT_NO_EMPTY) as $numero) {
            try {
                (new WhatsAppMensagemService())->enviar($numero, $texto);
            } catch (\Throwable) {
                // idem
            }
        }
    }
}
