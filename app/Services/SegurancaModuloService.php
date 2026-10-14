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
        // Agente 1.0.56+: varredura de rede, programa suspeito conversando com a
        // internet, processo crítico e inicialização -- só detecta e alerta,
        // nunca isola sozinho (ver SegurancaEventoService::TIPOS_SEM_ISOLAMENTO).
        'rede' => 'Conexões, processos e inicialização',
    ];

    /** Intervalo do monitor de conexões (segundos): padrão e faixa aceita. */
    public const REDE_INTERVALO_PADRAO = 30;
    public const REDE_INTERVALO_MIN = 5;
    public const REDE_INTERVALO_MAX = 3600;

    /** Coleta contínua de desempenho (agente 1.0.61+): padrão DESLIGADO, intervalo em segundos. */
    public const DESEMPENHO_INTERVALO_PADRAO = 5;
    public const DESEMPENHO_INTERVALO_MIN = 2;
    public const DESEMPENHO_INTERVALO_MAX = 60;

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

    /**
     * Detectores anti-ransomware nascem desligados (podem isolar a máquina);
     * o monitor de conexões nasce LIGADO -- só observa e alerta.
     */
    public function padrao(): array
    {
        $padrao = [];
        foreach (array_keys(self::MODULOS) as $modulo) {
            $padrao[$modulo] = ConfigService::get(self::PREFIXO . $modulo, $modulo === 'rede' ? '1' : '0') === '1';
        }
        $padrao['rede_intervalo'] = self::intervaloValido(ConfigService::get(self::PREFIXO . 'rede_intervalo', (string)self::REDE_INTERVALO_PADRAO));
        $padrao['desempenho'] = ConfigService::get(self::PREFIXO . 'desempenho', '0') === '1';
        $padrao['desempenho_intervalo'] = self::intervaloDesempenho(ConfigService::get(self::PREFIXO . 'desempenho_intervalo', (string)self::DESEMPENHO_INTERVALO_PADRAO));

        $modo = ConfigService::get(self::PREFIXO . 'isolamento_modo', 'alerta');
        $padrao['isolamento_modo'] = isset(self::MODOS_ISOLAMENTO[$modo]) ? $modo : 'alerta';
        $padrao['confirmacao_minutos'] = max(1, min(60, (int)ConfigService::get(self::PREFIXO . 'confirmacao_minutos', '5')));
        $padrao['fim_limiar_critico'] = max(5, (int)ConfigService::get(self::PREFIXO . 'fim_limiar_critico', '50'));
        $padrao['fim_limiar_aviso'] = max(1, (int)ConfigService::get(self::PREFIXO . 'fim_limiar_aviso', '20'));
        $padrao['fim_janela_segundos'] = max(5, min(300, (int)ConfigService::get(self::PREFIXO . 'fim_janela_segundos', '30')));
        $padrao['alerta_emails'] = ConfigService::get(self::PREFIXO . 'alerta_emails', '') ?? '';
        $padrao['alerta_whatsapp'] = ConfigService::get(self::PREFIXO . 'alerta_whatsapp', '') ?? '';
        $padrao['isolamento_liberados'] = ConfigService::get(self::PREFIXO . 'isolamento_liberados', '') ?? '';
        $padrao['alerta_url_portal'] = ConfigService::get(self::PREFIXO . 'alerta_url_portal', '') ?? '';
        $padrao['alerta_modelo'] = SegurancaAlertaService::modeloAtual();
        $padrao['fim_pastas_ignoradas'] = ConfigService::get(self::PREFIXO . 'fim_pastas_ignoradas', '') ?? '';
        $padrao['fim_extensoes_ignoradas'] = ConfigService::get(self::PREFIXO . 'fim_extensoes_ignoradas', '') ?? '';
        $padrao['janela_inicio'] = ConfigService::get(self::PREFIXO . 'janela_inicio', '') ?? '';
        $padrao['janela_fim'] = ConfigService::get(self::PREFIXO . 'janela_fim', '') ?? '';

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
        ConfigService::set(self::PREFIXO . 'rede_intervalo', (string)self::intervaloValido($dados['rede_intervalo'] ?? self::REDE_INTERVALO_PADRAO));
        ConfigService::set(self::PREFIXO . 'desempenho', !empty($dados['desempenho']) ? '1' : '0');
        ConfigService::set(self::PREFIXO . 'desempenho_intervalo', (string)self::intervaloDesempenho($dados['desempenho_intervalo'] ?? self::DESEMPENHO_INTERVALO_PADRAO));

        ConfigService::set(self::PREFIXO . 'alerta_emails', trim((string)($dados['alerta_emails'] ?? '')));
        ConfigService::set(self::PREFIXO . 'alerta_whatsapp', trim((string)($dados['alerta_whatsapp'] ?? '')));
        $modeloAlerta = (string)($dados['alerta_modelo'] ?? 'sistema');
        ConfigService::set(self::PREFIXO . 'alerta_modelo', isset(SegurancaAlertaService::MODELOS[$modeloAlerta]) ? $modeloAlerta : 'sistema');
        $urlPortal = rtrim(trim((string)($dados['alerta_url_portal'] ?? '')), '/');
        ConfigService::set(self::PREFIXO . 'alerta_url_portal', preg_match('#^https?://[^\s<>"\']+$#i', $urlPortal) ? $urlPortal : '');
        ConfigService::set(self::PREFIXO . 'isolamento_liberados', implode("\n", self::normalizarLiberados((string)($dados['isolamento_liberados'] ?? ''))));
    }

    public static function intervaloDesempenho(mixed $valor): int
    {
        return max(self::DESEMPENHO_INTERVALO_MIN, min(self::DESEMPENHO_INTERVALO_MAX, (int)$valor ?: self::DESEMPENHO_INTERVALO_PADRAO));
    }

    public static function intervaloValido(mixed $valor): int
    {
        return max(self::REDE_INTERVALO_MIN, min(self::REDE_INTERVALO_MAX, (int)$valor ?: self::REDE_INTERVALO_PADRAO));
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
|
|
/', $texto) as $linha) {
            $linha = trim($linha, " 	\"'");
            if ($linha !== '' && mb_strlen($linha) <= 260 && !preg_match('/[`$;|&<>]/', $linha)) {
                $itens[] = $linha;
            }
        }

        return array_values(array_unique(array_slice($itens, 0, 20)));
    }

    /**
     * Exceções da aba "Exceções e falsos positivos" -- salvas à parte do
     * padrão global pra que salvar um formulário não apague o outro.
     */
    public function salvarExcecoes(array $dados): void
    {
        $pastas = self::linhas((string)($dados['fim_pastas_ignoradas'] ?? ''), 260, 50);
        $extensoes = array_map(
            fn (string $e) => '.' . ltrim(mb_strtolower($e), '.*'),
            self::linhas((string)($dados['fim_extensoes_ignoradas'] ?? ''), 20, 100)
        );

        ConfigService::set(self::PREFIXO . 'fim_pastas_ignoradas', implode("\n", $pastas));
        ConfigService::set(self::PREFIXO . 'fim_extensoes_ignoradas', implode("\n", array_values(array_unique($extensoes))));

        $hora = static fn ($v) => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)$v) ? (string)$v : '';
        ConfigService::set(self::PREFIXO . 'janela_inicio', $hora($dados['janela_inicio'] ?? ''));
        ConfigService::set(self::PREFIXO . 'janela_fim', $hora($dados['janela_fim'] ?? ''));
    }

    /** @return list<string> */
    public static function linhas(string $texto, int $tamanhoMaximo, int $limite): array
    {
        $itens = [];
        foreach (preg_split('/

|
|
/', $texto) as $linha) {
            $linha = trim($linha, " 	\"'");
            if ($linha !== '' && mb_strlen($linha) <= $tamanhoMaximo) {
                $itens[] = $linha;
            }
        }

        return array_values(array_unique(array_slice($itens, 0, $limite)));
    }

    /**
     * Janela de manutenção (ex.: backup noturno): dentro dela, eventos de
     * shadow copy e mudança em massa só alertam, não isolam. Arquivo-isca
     * continua isolando -- backup e antivírus não alteram o conteúdo dela.
     * Aceita janela que vira a meia-noite (22:00 → 05:00).
     */
    public function dentroDaJanelaManutencao(?\DateTimeInterface $agora = null): bool
    {
        $padrao = $this->padrao();
        if ($padrao['janela_inicio'] === '' || $padrao['janela_fim'] === '' || $padrao['janela_inicio'] === $padrao['janela_fim']) {
            return false;
        }

        // Relógio do MySQL, o mesmo dos prazos de isolamento -- o fuso do PHP
        // pode estar diferente nesses servidores.
        $hora = $agora !== null
            ? $agora->format('H:i')
            : (string)$this->pdo->query("SELECT DATE_FORMAT(NOW(), '%H:%i')")->fetchColumn();

        return $padrao['janela_inicio'] < $padrao['janela_fim']
            ? $hora >= $padrao['janela_inicio'] && $hora < $padrao['janela_fim']
            : $hora >= $padrao['janela_inicio'] || $hora < $padrao['janela_fim'];
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
            'rede' => isset($linha['rede_habilitado']) ? (bool)$linha['rede_habilitado'] : null,
            'rede_intervalo' => isset($linha['rede_intervalo_segundos']) ? (int)$linha['rede_intervalo_segundos'] : null,
            'desempenho' => isset($linha['desempenho_habilitado']) ? (bool)$linha['desempenho_habilitado'] : null,
            'desempenho_intervalo' => isset($linha['desempenho_intervalo_segundos']) ? (int)$linha['desempenho_intervalo_segundos'] : null,
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

        $intervalo = trim((string)($dados['rede_intervalo'] ?? ''));
        $intervalo = $intervalo === '' ? null : self::intervaloValido($intervalo);

        $stmt = $this->pdo->prepare(
            'INSERT INTO ativos_seguranca_modulos (ativo_id, canary_habilitado, shadow_copy_habilitado, fim_habilitado, rede_habilitado, rede_intervalo_segundos, isolamento_modo)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE canary_habilitado = VALUES(canary_habilitado),
                 shadow_copy_habilitado = VALUES(shadow_copy_habilitado),
                 fim_habilitado = VALUES(fim_habilitado),
                 rede_habilitado = VALUES(rede_habilitado),
                 rede_intervalo_segundos = VALUES(rede_intervalo_segundos),
                 isolamento_modo = VALUES(isolamento_modo)'
        );
        $stmt->execute([
            $ativoId,
            $valor($dados['canary'] ?? 'padrao'),
            $valor($dados['shadow_copy'] ?? 'padrao'),
            $valor($dados['fim'] ?? 'padrao'),
            $valor($dados['rede'] ?? 'padrao'),
            $intervalo,
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
        if ($overrides['rede_intervalo'] !== null) {
            $efetivo['rede_intervalo'] = self::intervaloValido($overrides['rede_intervalo']);
        }
        if ($overrides['desempenho'] !== null) {
            $efetivo['desempenho'] = $overrides['desempenho'];
        }
        if ($overrides['desempenho_intervalo'] !== null) {
            $efetivo['desempenho_intervalo'] = self::intervaloDesempenho($overrides['desempenho_intervalo']);
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
            // Agente 1.0.35+: exceções configuradas na Central de Segurança.
            'fim_pastas_ignoradas' => self::linhas((string)$efetivo['fim_pastas_ignoradas'], 260, 50),
            'fim_extensoes_ignoradas' => self::linhas((string)$efetivo['fim_extensoes_ignoradas'], 20, 100),
            // Lista pública de ransomware (opcional). null = desligada: o agente descarta a que tiver.
            'assinaturas_versao' => (new SegurancaAssinaturaService())->versaoParaAgente(),
            // Agente 1.0.51+: regras de comportamento (limiares) -- baixa quando a versão muda.
            'regras_versao' => (new SegurancaRegrasService())->versao(),
        ];
    }

    /**
     * Bloco "monitor_rede" do heartbeat (a cada poucos segundos): mudar o
     * intervalo no portal vale na máquina em segundos, sem esperar check-in.
     */
    public function monitorRedeParaAgente(int $ativoId): array
    {
        $efetivo = $this->efetivo($ativoId);

        return ['ativo' => (bool)$efetivo['rede'], 'intervalo' => (int)$efetivo['rede_intervalo']];
    }

    /** Bloco "monitor_desempenho" do heartbeat. */
    public function monitorDesempenhoParaAgente(int $ativoId): array
    {
        $efetivo = $this->efetivo($ativoId);

        return ['ativo' => (bool)$efetivo['desempenho'], 'intervalo' => (int)$efetivo['desempenho_intervalo']];
    }

    /** Override da coleta contínua na máquina ('padrao' | '1' | '0'; intervalo vazio = padrão). */
    public function salvarDesempenho(int $ativoId, string $ligado, string $intervalo): void
    {
        $valor = $ligado === '1' ? 1 : ($ligado === '0' ? 0 : null);
        $segundos = trim($intervalo) === '' ? null : self::intervaloDesempenho($intervalo);
        $stmt = $this->pdo->prepare(
            'INSERT INTO ativos_seguranca_modulos (ativo_id, desempenho_habilitado, desempenho_intervalo_segundos) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE desempenho_habilitado = VALUES(desempenho_habilitado), desempenho_intervalo_segundos = VALUES(desempenho_intervalo_segundos)'
        );
        $stmt->execute([$ativoId, $valor, $segundos]);
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
