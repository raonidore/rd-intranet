<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Liga/desliga do módulo anti-ransomware. Padrão global em
 * configuracoes (seguranca_modulo_padrao_*) + override opcional por ativo
 * em ativos_seguranca_modulos (coluna NULL = segue o padrão). O agente
 * recebe o resultado já resolvido em todo checkin (paraAgente()) e liga
 * ou desliga os watchers sozinho -- mudar aqui não exige reinstalar nada.
 */
class SegurancaModuloService
{
    public const MODULOS = [
        'canary' => 'Arquivos-isca (canary files)',
        'shadow_copy' => 'Exclusão de shadow copy / backup',
        'fim' => 'Mudança em massa de arquivos (FIM)',
    ];

    public const MODOS_ISOLAMENTO = [
        'desligado' => 'Desligado -- nunca isola',
        'alerta' => 'Só alerta -- avisa e espera alguém decidir',
        'confirmacao' => 'Confirmação -- avisa e isola sozinho se ninguém cancelar no prazo',
        'automatico' => 'Automático -- isola na hora',
    ];

    private const PREFIXO = 'seguranca_modulo_padrao_';

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /** Tudo desligado por padrão: nada acontece nas máquinas até alguém escolher ligar. */
    public function padrao(): array
    {
        $padrao = [];
        foreach (array_keys(self::MODULOS) as $modulo) {
            $padrao[$modulo] = ConfigService::get(self::PREFIXO . $modulo, '0') === '1';
        }

        $modo = ConfigService::get(self::PREFIXO . 'isolamento_modo', 'alerta');
        $padrao['isolamento_modo'] = isset(self::MODOS_ISOLAMENTO[$modo]) ? $modo : 'alerta';
        $padrao['confirmacao_minutos'] = max(1, min(60, (int)ConfigService::get(self::PREFIXO . 'confirmacao_minutos', '5')));
        $padrao['fim_limiar_critico'] = max(5, (int)ConfigService::get(self::PREFIXO . 'fim_limiar_critico', '50'));
        $padrao['fim_limiar_aviso'] = max(1, (int)ConfigService::get(self::PREFIXO . 'fim_limiar_aviso', '20'));
        $padrao['fim_janela_segundos'] = max(5, min(300, (int)ConfigService::get(self::PREFIXO . 'fim_janela_segundos', '30')));
        $padrao['alerta_emails'] = ConfigService::get(self::PREFIXO . 'alerta_emails', '') ?? '';
        $padrao['alerta_whatsapp'] = ConfigService::get(self::PREFIXO . 'alerta_whatsapp', '') ?? '';
        $padrao['isolamento_liberados'] = ConfigService::get(self::PREFIXO . 'isolamento_liberados', '') ?? '';

        return $padrao;
    }

    public function salvarPadrao(array $dados): void
    {
        foreach (array_keys(self::MODULOS) as $modulo) {
            ConfigService::set(self::PREFIXO . $modulo, !empty($dados[$modulo]) ? '1' : '0');
        }

        $modo = $dados['isolamento_modo'] ?? 'alerta';
        ConfigService::set(self::PREFIXO . 'isolamento_modo', isset(self::MODOS_ISOLAMENTO[$modo]) ? $modo : 'alerta');
        ConfigService::set(self::PREFIXO . 'confirmacao_minutos', (string)max(1, min(60, (int)($dados['confirmacao_minutos'] ?? 5))));

        $critico = max(5, (int)($dados['fim_limiar_critico'] ?? 50));
        $aviso = max(1, min($critico - 1, (int)($dados['fim_limiar_aviso'] ?? 20)));
        ConfigService::set(self::PREFIXO . 'fim_limiar_critico', (string)$critico);
        ConfigService::set(self::PREFIXO . 'fim_limiar_aviso', (string)$aviso);
        ConfigService::set(self::PREFIXO . 'fim_janela_segundos', (string)max(5, min(300, (int)($dados['fim_janela_segundos'] ?? 30))));

        ConfigService::set(self::PREFIXO . 'alerta_emails', trim((string)($dados['alerta_emails'] ?? '')));
        ConfigService::set(self::PREFIXO . 'alerta_whatsapp', trim((string)($dados['alerta_whatsapp'] ?? '')));
        ConfigService::set(self::PREFIXO . 'isolamento_liberados', implode("
", self::normalizarLiberados((string)($dados['isolamento_liberados'] ?? ''))));
    }

    /**
     * Programas extras que continuam com rede durante o isolamento, além do
     * agente e do MeshAgent (sempre liberados). Uma linha por item: caminho
     * de um .exe ou nome de um serviço do Windows. Cada item liberado é uma
     * porta aberta numa máquina possivelmente comprometida -- ferramentas de
     * acesso remoto de terceiros (AnyDesk, TeamViewer) são usadas por
     * atacantes, por isso não entram por padrão.
     *
     * @return list<string>
     */
    public static function normalizarLiberados(string $texto): array
    {
        $itens = [];
        foreach (preg_split('/
||
/', $texto) as $linha) {
            $linha = trim($linha, " 	\"'");
            if ($linha !== '' && mb_strlen($linha) <= 260 && !preg_match('/[`$;|&<>]/', $linha)) {
                $itens[] = $linha;
            }
        }

        return array_values(array_unique(array_slice($itens, 0, 20)));
    }

    /** Máquinas com isolamento de rede ativo agora -- Central de Segurança e menu lateral. */
    public function listarIsoladas(): array
    {
        return $this->pdo->query(
            "SELECT m.ativo_id, m.isolado_em, a.codigo_patrimonio, a.nome, a.ultimo_heartbeat
             FROM ativos_seguranca_modulos m
             JOIN ativos a ON a.id = m.ativo_id
             WHERE m.isolado_em IS NOT NULL
             ORDER BY m.isolado_em DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Barato o suficiente pra rodar em toda página (badge do menu). Tabela ausente (servidor sem migração) = 0. */
    public function contarIsoladas(): int
    {
        try {
            return (int)$this->pdo->query('SELECT COUNT(*) FROM ativos_seguranca_modulos WHERE isolado_em IS NOT NULL')->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Linha crua da máquina (NULL = segue o padrão), ou tudo NULL se nunca foi sobrescrita. */
    public function overrides(int $ativoId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ativos_seguranca_modulos WHERE ativo_id = ?');
        $stmt->execute([$ativoId]);
        $linha = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'canary' => isset($linha['canary_habilitado']) ? (bool)$linha['canary_habilitado'] : null,
            'shadow_copy' => isset($linha['shadow_copy_habilitado']) ? (bool)$linha['shadow_copy_habilitado'] : null,
            'fim' => isset($linha['fim_habilitado']) ? (bool)$linha['fim_habilitado'] : null,
            'isolamento_modo' => $linha['isolamento_modo'] ?? null,
            'isolado_em' => $linha['isolado_em'] ?? null,
        ];
    }

    /**
     * $dados vem do formulário da ficha: cada módulo é 'padrao' | '1' | '0',
     * isolamento_modo é 'padrao' ou uma chave de MODOS_ISOLAMENTO.
     */
    public function salvarOverrides(int $ativoId, array $dados): void
    {
        $valor = static function ($v): ?int {
            return match ((string)$v) {
                '1' => 1,
                '0' => 0,
                default => null,
            };
        };

        $modo = (string)($dados['isolamento_modo'] ?? 'padrao');
        $modo = isset(self::MODOS_ISOLAMENTO[$modo]) ? $modo : null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO ativos_seguranca_modulos (ativo_id, canary_habilitado, shadow_copy_habilitado, fim_habilitado, isolamento_modo)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE canary_habilitado = VALUES(canary_habilitado),
                 shadow_copy_habilitado = VALUES(shadow_copy_habilitado),
                 fim_habilitado = VALUES(fim_habilitado),
                 isolamento_modo = VALUES(isolamento_modo)'
        );
        $stmt->execute([
            $ativoId,
            $valor($dados['canary'] ?? 'padrao'),
            $valor($dados['shadow_copy'] ?? 'padrao'),
            $valor($dados['fim'] ?? 'padrao'),
            $modo,
        ]);
    }

    /** Estado resolvido (override da máquina > padrão global). */
    public function efetivo(int $ativoId): array
    {
        $padrao = $this->padrao();
        $overrides = $this->overrides($ativoId);

        $efetivo = $padrao;
        foreach (array_keys(self::MODULOS) as $modulo) {
            if ($overrides[$modulo] !== null) {
                $efetivo[$modulo] = $overrides[$modulo];
            }
        }
        if ($overrides['isolamento_modo'] !== null) {
            $efetivo['isolamento_modo'] = $overrides['isolamento_modo'];
        }
        $efetivo['isolado_em'] = $overrides['isolado_em'];

        return $efetivo;
    }

    /** Bloco enviado na resposta do checkin -- só o que o agente precisa saber. */
    public function paraAgente(int $ativoId): array
    {
        $efetivo = $this->efetivo($ativoId);

        return [
            'canary' => $efetivo['canary'],
            'shadow_copy' => $efetivo['shadow_copy'],
            'fim' => $efetivo['fim'],
            'isolamento_modo' => $efetivo['isolamento_modo'],
            'fim_limiar_critico' => $efetivo['fim_limiar_critico'],
            'fim_limiar_aviso' => $efetivo['fim_limiar_aviso'],
            'fim_janela_segundos' => $efetivo['fim_janela_segundos'],
            'isolado' => !empty($efetivo['isolado_em']),
        ];
    }

    public function marcarIsolado(int $ativoId, bool $isolado): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO ativos_seguranca_modulos (ativo_id, isolado_em) VALUES (?, IF(?, NOW(), NULL))
             ON DUPLICATE KEY UPDATE isolado_em = VALUES(isolado_em)'
        );
        $stmt->execute([$ativoId, $isolado ? 1 : 0]);
    }
}
