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
        'SHADOW_COPY_DELETE_ATTEMPT' => 'Tentativa de apagar shadow copies',
        'BACKUP_DELETE_ATTEMPT' => 'Tentativa de apagar backups/recuperação',
        'MASS_FILE_CHANGE' => 'Mudança em massa de arquivos',
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

        if ($this->contarRecentes($ativoId) >= self::MAX_EVENTOS_JANELA) {
            return ['success' => true, 'message' => 'Limite de eventos da janela atingido -- descartado.'];
        }

        $acao = (string)($payload['acao_automatica'] ?? 'nenhuma');
        if (!in_array($acao, self::ACOES_AGENTE, true)) {
            $acao = 'nenhuma';
        }
        $detalhes = is_array($payload['detalhes'] ?? null) ? $payload['detalhes'] : [];
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
            $this->notificar($ativoId, $tipo, $severidade, $this->montarResumo($tipo, $detalhes), $decisao);
        }

        return ['success' => true, 'id' => $id];
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

        return array_map(function (array $e) {
            $e['detalhes'] = json_decode((string)$e['detalhes'], true) ?: [];
            return $e;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ativos_eventos_seguranca WHERE id = ?');
        $stmt->execute([$id]);
        $evento = $stmt->fetch(PDO::FETCH_ASSOC);

        return $evento ?: null;
    }

    public function marcarResolvido(int $id, string $usuario): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE ativos_eventos_seguranca SET resolvido_em = NOW(), resolvido_por = ?, isolamento_pendente_ate = NULL
             WHERE id = ? AND resolvido_em IS NULL'
        );
        $stmt->execute([$usuario, $id]);
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
            'SHADOW_COPY_DELETE_ATTEMPT', 'BACKUP_DELETE_ATTEMPT' => 'Comando detectado: ' . mb_substr((string)($d['linha_comando'] ?? '?'), 0, 150) . $processo,
            'MASS_FILE_CHANGE' => (int)($d['eventos'] ?? 0) . ' arquivos alterados em ' . (int)($d['janela_segundos'] ?? 0) . 's em ' . ($d['pasta'] ?? '?')
                . (!empty($d['extensoes_novas']) ? ' -- extensões novas: ' . implode(', ', array_slice((array)$d['extensoes_novas'], 0, 5)) : ''),
            default => self::rotuloTipo($tipo),
        };
    }

    /** Não repete aviso da mesma máquina+tipo dentro de INTERVALO_NOTIFICACAO_MINUTOS -- o portal continua registrando tudo. */
    private function notificar(int $ativoId, string $tipo, string $severidade, string $resumo, string $decisao): void
    {
        $chave = "seguranca_ultima_notificacao_{$ativoId}_{$tipo}";
        $ultima = (int)(ConfigService::get($chave, '0') ?? 0);
        if (time() - $ultima < self::INTERVALO_NOTIFICACAO_MINUTOS * 60) {
            return;
        }
        ConfigService::set($chave, (string)time());

        $ativo = (new AtivoService())->buscar($ativoId);
        $maquina = ($ativo['codigo_patrimonio'] ?? "#{$ativoId}") . ' (' . ($ativo['nome'] ?? '?') . ')';
        $padrao = (new SegurancaModuloService())->padrao();

        $texto = "ALERTA DE SEGURANÇA -- {$maquina}\n"
            . self::rotuloTipo($tipo) . " ({$severidade})\n"
            . "{$resumo}\n"
            . "Resposta: {$decisao}";

        foreach (EmailService::normalizarLista($padrao['alerta_emails']) as $email) {
            try {
                (new EmailService())->enviar(
                    $email,
                    '[RD Intranet] Alerta de segurança: ' . self::rotuloTipo($tipo) . ' em ' . ($ativo['codigo_patrimonio'] ?? "#{$ativoId}"),
                    nl2br(htmlspecialchars($texto))
                );
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
