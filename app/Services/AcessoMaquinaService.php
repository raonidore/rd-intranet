<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Trilha de acesso às máquinas (Segurança > Trilha de acesso): quem entrou em
 * qual computador e quando, como evidência para auditoria/acreditação.
 *
 * Duas origens, uma confirmando a outra:
 *  - "windows": log de segurança do Windows lido pelo agente (1.0.71+) --
 *    login, logoff, bloqueio/desbloqueio de tela, senha errada e acesso negado
 *    pela restrição de Acesso às Máquinas. Pega também login sem internet.
 *  - "entra": registro de entradas do Entra ID (app "Windows Sign In") --
 *    prova que a autenticação passou pela Microsoft. Importado pelo cron
 *    (rd acessos:manutencao), já que o Entra só guarda 30 dias.
 *
 * Os horários chegam em UTC e são gravados no fuso do servidor.
 */
class AcessoMaquinaService
{
    public const EVENTOS = [
        'login' => 'Login',
        'desbloqueio' => 'Desbloqueio de tela',
        'bloqueio' => 'Bloqueio de tela',
        'logoff' => 'Logoff',
        'falha' => 'Falha de login',
        'negado' => 'Acesso negado',
    ];

    private const FORMAS_LOGON = [
        2 => 'No teclado da máquina',
        7 => 'Desbloqueio',
        10 => 'Remoto (Área de Trabalho Remota)',
        // Tipo 11 (credencial em cache) é o normal de máquina no Entra, mesmo com internet -- o registro
        // do Entra no mesmo segundo confirma a autenticação online (EN-TI-01-D, 17/10/2026).
        11 => 'No teclado (conta do Entra)',
    ];

    /** Motivos de falha do 4625 (Status/SubStatus). */
    private const MOTIVOS_FALHA = [
        'C000015B' => 'Conta sem permissão para entrar nesta máquina',
        'C000006A' => 'Senha incorreta',
        'C000006D' => 'Usuário ou senha incorretos',
        'C0000064' => 'Usuário não existe',
        'C0000072' => 'Conta desativada',
        'C0000234' => 'Conta bloqueada',
        'C0000193' => 'Conta expirada',
        'C0000071' => 'Senha expirada',
        'C0000224' => 'Senha precisa ser trocada',
        'C000006F' => 'Fora do horário permitido',
        'C0000070' => 'Máquina não permitida para a conta',
        'C0000133' => 'Relógio da máquina fora de sincronia',
    ];

    private const RETENCAO_PADRAO_ANOS = 5;

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /* ------------------------------------------------------------------
     | Recebimento do agente
     * ------------------------------------------------------------------ */

    /** Último RecordId do log de segurança já recebido desta máquina -- o agente continua dali. */
    public function ultimoRecordId(int $ativoId): int
    {
        $stmt = $this->db->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(ref, ':', -1) AS UNSIGNED)) FROM acessos_maquinas WHERE origem = 'windows' AND ativo_id = ?");
        $stmt->execute([$ativoId]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Eventos do log de segurança enviados pelo agente:
     * [{record_id, event_id, quando (ISO UTC), usuario, dominio, sid, logon_id, tipo_logon, ip, status, substatus}].
     *
     * @return int quantos eram novos
     */
    public function registrarDoWindows(int $ativoId, array $eventos): int
    {
        $maquina = $this->nomeAtivo($ativoId);
        $novos = 0;

        foreach ($eventos as $e) {
            if (!is_array($e) || empty($e['record_id']) || empty($e['quando'])) {
                continue;
            }

            $linha = $this->interpretarWindows($e);
            if ($linha === null) {
                continue;
            }

            $linha += [
                'origem' => 'windows',
                'ref' => $ativoId . ':' . (int)$e['record_id'],
                'ativo_id' => $ativoId,
                'maquina' => $maquina,
                'ocorrido_em' => $this->paraHoraLocal((string)$e['quando']),
            ];

            if ($this->duplicado($linha)) {
                continue;
            }

            $novos += $this->inserir($linha);
        }

        $this->purgarSeVenceu();

        return $novos;
    }

    private function interpretarWindows(array $e): ?array
    {
        $id = (int)($e['event_id'] ?? 0);
        $tipo = isset($e['tipo_logon']) && $e['tipo_logon'] !== '' ? (int)$e['tipo_logon'] : null;
        $dominio = trim((string)($e['dominio'] ?? ''));
        $usuario = trim((string)($e['usuario'] ?? ''));
        $sid = trim((string)($e['sid'] ?? ''));

        $linha = [
            'usuario' => $usuario === '' ? null : ($dominio !== '' && strcasecmp($dominio, 'AzureAD') !== 0 ? "{$dominio}\\{$usuario}" : $usuario),
            'sid' => $sid ?: null,
            'upn' => str_starts_with($sid, 'S-1-12-1-') ? $this->upnDoSid($sid) : null,
            'logon_id' => ($e['logon_id'] ?? '') ?: null,
            'ip' => in_array($e['ip'] ?? '', ['', '-', '::1', '127.0.0.1'], true) ? null : (string)$e['ip'],
            'codigo_evento' => $id,
            'forma' => $tipo !== null ? (self::FORMAS_LOGON[$tipo] ?? "Tipo {$tipo}") : null,
            'detalhe' => null,
        ];

        switch ($id) {
            case 4624:
                $linha['evento'] = $tipo === 7 ? 'desbloqueio' : 'login';
                break;
            case 4625:
                $codigo = strtoupper(preg_replace('/^0x/i', '', (string)($e['substatus'] ?? '')));
                if (!isset(self::MOTIVOS_FALHA[$codigo])) {
                    $codigo = strtoupper(preg_replace('/^0x/i', '', (string)($e['status'] ?? '')));
                }
                $linha['evento'] = $codigo === 'C000015B' ? 'negado' : 'falha';
                $linha['detalhe'] = self::MOTIVOS_FALHA[$codigo] ?? ($codigo !== '' ? "Código 0x{$codigo}" : null);
                break;
            case 4634:
            case 4647:
                $linha['evento'] = 'logoff';
                $linha['forma'] = null;
                break;
            case 4800:
                $linha['evento'] = 'bloqueio';
                break;
            case 4801:
                $linha['evento'] = 'desbloqueio';
                $linha['forma'] = 'Desbloqueio';
                break;
            default:
                return null;
        }

        return $linha;
    }

    /**
     * O Windows registra o mesmo fato duas vezes: logoff (4647 do usuário + 4634 da
     * sessão) e desbloqueio (4801 + 4624 tipo 7). Guarda só o primeiro que chegar.
     */
    private function duplicado(array $l): bool
    {
        // Logoff: o 4647 e o 4634 da mesma sessão, e o 4634 da sessão "elevada" gêmea
        // que o Windows cria pra administrador (logon_id diferente, mesmo usuário e segundo).
        if ($l['evento'] === 'logoff') {
            $stmt = $this->db->prepare("SELECT 1 FROM acessos_maquinas WHERE ativo_id = ? AND evento = 'logoff'
                AND (logon_id = ? OR (sid = ? AND ocorrido_em BETWEEN DATE_SUB(?, INTERVAL 15 SECOND) AND DATE_ADD(?, INTERVAL 15 SECOND))) LIMIT 1");
            $stmt->execute([$l['ativo_id'], $l['logon_id'] ?? '', $l['sid'] ?? '', $l['ocorrido_em'], $l['ocorrido_em']]);
            return (bool)$stmt->fetchColumn();
        }

        if ($l['evento'] === 'desbloqueio' && $l['sid']) {
            $stmt = $this->db->prepare("SELECT 1 FROM acessos_maquinas WHERE ativo_id = ? AND sid = ? AND evento = 'desbloqueio'
                AND ocorrido_em BETWEEN DATE_SUB(?, INTERVAL 15 SECOND) AND DATE_ADD(?, INTERVAL 15 SECOND) LIMIT 1");
            $stmt->execute([$l['ativo_id'], $l['sid'], $l['ocorrido_em'], $l['ocorrido_em']]);
            return (bool)$stmt->fetchColumn();
        }

        return false;
    }

    /* ------------------------------------------------------------------
     | Importação do Entra (cron)
     * ------------------------------------------------------------------ */

    /** @return array{importados:int, mensagem:string} */
    public function importarDoEntra(): array
    {
        $entra = new EntraService();
        if (!$entra->configurado()) {
            return ['importados' => 0, 'mensagem' => 'Entra não configurado.'];
        }

        // Volta 30 min sobre a última leitura: o Entra publica entradas com alguns minutos de atraso.
        $ultima = ConfigService::get('acessos_entra_ultima_leitura', '') ?: gmdate('Y-m-d\TH:i:s\Z', strtotime('-30 days'));
        $desde = gmdate('Y-m-d\TH:i:s\Z', strtotime($ultima) - 1800);
        $inicio = gmdate('Y-m-d\TH:i:s\Z');

        $r = $entra->entradasNoWindows($desde);
        if (!$r['sucesso']) {
            return ['importados' => 0, 'mensagem' => $r['mensagem']];
        }

        $ativosPorNome = [];
        foreach ($this->db->query("SELECT id, nome FROM ativos")->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $ativosPorNome[mb_strtolower($a['nome'])] = (int)$a['id'];
        }

        $novos = 0;
        foreach ($r['itens'] as $s) {
            $maquina = (string)($s['deviceDetail']['displayName'] ?? '');
            $codigo = (int)($s['status']['errorCode'] ?? 0);
            $local = trim(implode(' / ', array_filter([$s['location']['city'] ?? '', $s['location']['state'] ?? ''])));

            $novos += $this->inserir([
                'origem' => 'entra',
                'ref' => (string)$s['id'],
                'ativo_id' => $ativosPorNome[mb_strtolower($maquina)] ?? null,
                'maquina' => $maquina ?: null,
                'evento' => $codigo === 0 ? 'login' : 'falha',
                'forma' => 'Autenticado no Entra ID',
                'usuario' => $s['userDisplayName'] ?? null,
                'upn' => $s['userPrincipalName'] ?? null,
                'sid' => !empty($s['userId']) ? EntraService::sidDoObjeto($s['userId']) : null,
                'logon_id' => null,
                'ip' => $s['ipAddress'] ?? null,
                'detalhe' => $codigo === 0
                    ? ($local !== '' ? "Local aproximado: {$local}" : null)
                    : mb_substr((string)($s['status']['failureReason'] ?? "Erro {$codigo}"), 0, 300),
                'codigo_evento' => $codigo ?: null,
                'ocorrido_em' => $this->paraHoraLocal((string)$s['createdDateTime']),
            ]);
        }

        ConfigService::set('acessos_entra_ultima_leitura', $inicio);

        return ['importados' => $novos, 'mensagem' => count($r['itens']) . ' entrada(s) lida(s) do Entra.'];
    }

    /* ------------------------------------------------------------------
     | Consulta / relatório
     * ------------------------------------------------------------------ */

    /** Filtros: de, ate (Y-m-d), ativo_id, usuario (texto), evento, origem. */
    public function listar(array $filtros, int $limite = 2000): array
    {
        [$where, $params] = $this->montarFiltro($filtros);

        $stmt = $this->db->prepare("SELECT am.*, COALESCE(a.nome, am.maquina) AS maquina_nome
            FROM acessos_maquinas am LEFT JOIN ativos a ON a.id = am.ativo_id
            WHERE {$where} ORDER BY am.ocorrido_em DESC, am.id DESC LIMIT " . (int)$limite);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contar(array $filtros): array
    {
        [$where, $params] = $this->montarFiltro($filtros);

        $stmt = $this->db->prepare("SELECT evento, COUNT(*) FROM acessos_maquinas am WHERE {$where} GROUP BY evento");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    private function montarFiltro(array $f): array
    {
        $where = ['am.ocorrido_em >= ?', 'am.ocorrido_em < DATE_ADD(?, INTERVAL 1 DAY)'];
        $params = [$f['de'], $f['ate']];

        if (!empty($f['ativo_id'])) {
            $where[] = 'am.ativo_id = ?';
            $params[] = (int)$f['ativo_id'];
        }
        if (!empty($f['usuario'])) {
            $where[] = '(am.usuario LIKE ? OR am.upn LIKE ?)';
            $params[] = '%' . $f['usuario'] . '%';
            $params[] = '%' . $f['usuario'] . '%';
        }
        if (!empty($f['evento']) && isset(self::EVENTOS[$f['evento']])) {
            $where[] = 'am.evento = ?';
            $params[] = $f['evento'];
        }
        if (in_array($f['origem'] ?? '', ['windows', 'entra'], true)) {
            $where[] = 'am.origem = ?';
            $params[] = $f['origem'];
        }

        return [implode(' AND ', $where), $params];
    }

    /** Máquinas que já mandaram algum evento (pro filtro) + quando foi o último. */
    public function maquinasComTrilha(): array
    {
        return $this->db->query("SELECT a.id, a.nome, MAX(am.recebido_em) AS ultimo
            FROM acessos_maquinas am JOIN ativos a ON a.id = am.ativo_id
            GROUP BY a.id, a.nome ORDER BY a.nome")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Linhas prontas pra PDF/Excel. */
    public function tabela(array $linhas): array
    {
        return [
            'cabecalho' => ['Data/hora', 'Máquina', 'Usuário', 'Evento', 'Forma', 'Detalhe', 'IP', 'Origem'],
            'linhas' => array_map(fn($l) => [
                date('d/m/Y H:i:s', strtotime($l['ocorrido_em'])),
                (string)$l['maquina_nome'],
                self::rotuloUsuario($l),
                self::EVENTOS[$l['evento']] ?? $l['evento'],
                (string)$l['forma'],
                (string)$l['detalhe'],
                (string)$l['ip'],
                $l['origem'] === 'entra' ? 'Entra ID' : 'Windows',
            ], $linhas),
        ];
    }

    public static function rotuloUsuario(array $l): string
    {
        $usuario = (string)($l['usuario'] ?? '');
        $upn = (string)($l['upn'] ?? '');

        if ($upn !== '' && $usuario !== '' && strcasecmp($usuario, $upn) !== 0) {
            return "{$upn} ({$usuario})";
        }

        return $upn ?: $usuario;
    }

    public function retencaoAnos(): int
    {
        return max(1, (int)(ConfigService::get('acessos_retencao_anos', (string)self::RETENCAO_PADRAO_ANOS) ?: self::RETENCAO_PADRAO_ANOS));
    }

    /** Apaga o que passou da retenção -- no máximo uma vez por dia. */
    public function purgarSeVenceu(): int
    {
        if (ConfigService::get('acessos_purga_em', '') === date('Y-m-d')) {
            return 0;
        }
        ConfigService::set('acessos_purga_em', date('Y-m-d'));

        $stmt = $this->db->prepare('DELETE FROM acessos_maquinas WHERE ocorrido_em < DATE_SUB(NOW(), INTERVAL ? YEAR)');
        $stmt->execute([$this->retencaoAnos()]);

        return $stmt->rowCount();
    }

    /* ------------------------------------------------------------------ */

    private function inserir(array $l): int
    {
        $stmt = $this->db->prepare('INSERT IGNORE INTO acessos_maquinas
            (origem, ref, ativo_id, maquina, evento, forma, usuario, upn, sid, logon_id, ip, detalhe, codigo_evento, ocorrido_em)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $l['origem'], $l['ref'], $l['ativo_id'], $l['maquina'], $l['evento'], $l['forma'],
            $l['usuario'] ? mb_substr($l['usuario'], 0, 200) : null, $l['upn'], $l['sid'], $l['logon_id'],
            $l['ip'] ? mb_substr($l['ip'], 0, 60) : null, $l['detalhe'], $l['codigo_evento'], $l['ocorrido_em'],
        ]);

        return $stmt->rowCount();
    }

    private function paraHoraLocal(string $iso): string
    {
        try {
            return (new \DateTimeImmutable($iso))->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return date('Y-m-d H:i:s');
        }
    }

    private function nomeAtivo(int $ativoId): ?string
    {
        $stmt = $this->db->prepare('SELECT nome FROM ativos WHERE id = ?');
        $stmt->execute([$ativoId]);

        return $stmt->fetchColumn() ?: null;
    }

    /** @var array<string,string>|null */
    private ?array $mapaSid = null;

    /** UPN de uma conta do Entra pelo SID -- mapa guardado por 1h (uma chamada ao Graph, não uma por evento). */
    private function upnDoSid(string $sid): ?string
    {
        if ($this->mapaSid === null) {
            $guardado = json_decode(ConfigService::get('acessos_mapa_sid', '') ?: '', true);
            $idade = time() - (int)($guardado['em'] ?? 0);
            $this->mapaSid = is_array($guardado) && $idade < 3600 ? $guardado['mapa'] : null;

            // Conta nova (não está no mapa): renova antes da hora, mas no máximo a cada 10 min.
            if ($this->mapaSid === null || (!isset($this->mapaSid[$sid]) && $idade > 600)) {
                $entra = new EntraService();
                $this->mapaSid = $entra->configurado() ? $entra->mapaSidUpn() : [];
                ConfigService::set('acessos_mapa_sid', json_encode(['em' => time(), 'mapa' => $this->mapaSid]));
            }
        }

        return $this->mapaSid[$sid] ?? null;
    }
}
