<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Central de Segurança > Recomendações. Verificações automáticas sobre o que
 * o portal já sabe das máquinas (inventário do agente, retratos de segurança,
 * eventos) e, quando dá, uma ação de um clique: um comando PRONTO, definido
 * aqui no servidor (nunca texto livre), enviado ao agente pelo mesmo canal
 * das outras ações. Origem: área de cópias de sombra de 1% no AV-RECEPCAO
 * (Enzilab Prime, 13/10/2026), que só guardava 1-2 dias de cópias.
 */
class SegurancaRecomendacaoService
{
    public const SEVERIDADES = ['alta' => 'Alta', 'media' => 'Média', 'baixa' => 'Baixa'];

    /** Área de cópias de sombra que a ação "aumentar" aplica (% do disco). */
    private const SOMBRA_ALVO_PCT = 8;

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /**
     * @return list<array{chave: string, titulo: string, porque: string, severidade: string, acao: ?array,
     *   link: ?array, maquinas: list<array>, ignoradas: int}>
     */
    public function gerar(): array
    {
        $ativos = array_values(array_filter(
            (new AtivoService())->listar([]),
            fn (array $a) => $a['origem'] === 'agente' && !in_array($a['status'], ['baixado', 'estoque'], true)
        ));
        $retratos = $this->retratosInicializacao();
        $outroAntivirus = $this->outroAntivirusPorAtivo();
        foreach ($ativos as &$a) {
            $a['_outro_av'] = $outroAntivirus[(int)$a['id']] ?? null;
        }
        unset($a);
        $ignoradas = $this->ignoradas();
        $acoes = $this->ultimasAcoes();

        $regras = [];
        foreach ($this->definicoes() as $chave => $def) {
            if (isset($ignoradas[$chave][0])) {
                continue; // regra inteira ignorada
            }
            $maquinas = [];
            $ocultas = 0;
            if (isset($def['por_maquina'])) {
                foreach ($ativos as $a) {
                    $detalhes = json_decode($a['detalhes'] ?? '', true) ?: [];
                    $achado = $def['por_maquina']($a, $detalhes, $retratos[(int)$a['id']] ?? []);
                    if ($achado === null) {
                        continue;
                    }
                    if (isset($ignoradas[$chave][(int)$a['id']])) {
                        $ocultas++;
                        continue;
                    }
                    $maquinas[] = [
                        'ativo_id' => (int)$a['id'],
                        'codigo' => $a['codigo_patrimonio'],
                        'nome' => $a['apelido'] ?: $a['nome'],
                        'detalhe' => $achado,
                        'ligada' => AtivoService::estaLigada($a),
                        'acao' => $acoes[$chave][(int)$a['id']] ?? null,
                    ];
                }
                if (!$maquinas) {
                    continue;
                }
            } elseif (!($def['geral'])()) {
                continue;
            }

            $regras[] = [
                'chave' => $chave,
                'titulo' => $def['titulo'],
                'porque' => $def['porque'],
                'severidade' => $def['severidade'],
                'acao' => $def['acao'] ?? null,
                'link' => $def['link'] ?? null,
                'maquinas' => $maquinas,
                'ignoradas' => $ocultas,
            ];
        }

        usort($regras, fn ($x, $y) => array_search($x['severidade'], array_keys(self::SEVERIDADES)) <=> array_search($y['severidade'], array_keys(self::SEVERIDADES))
            ?: count($y['maquinas']) <=> count($x['maquinas']));

        return $regras;
    }

    /** Quantas recomendações em aberto (badge da aba). */
    public function contar(): int
    {
        try {
            return count($this->gerar());
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Envia a ação da recomendação às máquinas escolhidas.
     * @param int[] $ativoIds
     * @return array{success: bool, message: string}
     */
    public function aplicar(string $chave, array $ativoIds, string $usuario): array
    {
        $def = $this->definicoes()[$chave] ?? null;
        if (!$def || empty($def['acao'])) {
            return ['success' => false, 'message' => 'Essa recomendação não tem ação automática.'];
        }

        // Só máquinas que de fato estão na recomendação agora (não dá pra mandar comando pra qualquer id).
        $atual = array_values(array_filter($this->gerar(), fn ($r) => $r['chave'] === $chave))[0] ?? null;
        $validas = array_column($atual['maquinas'] ?? [], 'ativo_id');
        $ativoIds = array_values(array_intersect(array_map('intval', $ativoIds), $validas));
        if (!$ativoIds) {
            return ['success' => false, 'message' => 'Nenhuma máquina válida para essa ação.'];
        }

        $ativos = new AtivoService();
        $enviadas = 0;
        $falhas = [];
        foreach ($ativoIds as $ativoId) {
            $resultado = $def['acao']['tipo'] === 'monitor'
                ? $ativos->acaoMonitorSeguranca($ativoId, $def['acao']['acao_monitor'], $def['acao']['dados'] ?? [], $usuario)
                : $ativos->solicitarListagem($ativoId, 'executar_powershell', $def['acao']['script'], $usuario . ' (Recomendações: ' . $def['titulo'] . ')', true);
            if (!empty($resultado['success']) && !empty($resultado['id'])) {
                $this->pdo->prepare('INSERT INTO seguranca_recomendacoes_acoes (chave, ativo_id, solicitacao_id, por) VALUES (?, ?, ?, ?)')
                    ->execute([$chave, $ativoId, (int)$resultado['id'], $usuario]);
                $enviadas++;
            } else {
                $falhas[] = $resultado['message'] ?? 'falhou';
            }
        }

        AuditService::registrar('Segurança', 'Recomendação aplicada', "{$def['titulo']}: enviada a {$enviadas} máquina(s)" . ($falhas ? '; falhas: ' . implode(' / ', array_unique($falhas)) : '') . '.');

        return [
            'success' => $enviadas > 0,
            'message' => $enviadas > 0
                ? "\"{$def['acao']['rotulo']}\" enviado a {$enviadas} máquina(s). Máquinas desligadas recebem quando ligarem; o resultado aparece aqui."
                : 'Não foi possível enviar: ' . implode(' / ', array_unique($falhas)),
        ];
    }

    public function ignorar(string $chave, ?int $ativoId, string $motivo, string $usuario): array
    {
        if (!isset($this->definicoes()[$chave])) {
            return ['success' => false, 'message' => 'Recomendação inválida.'];
        }
        $motivo = mb_substr(trim($motivo), 0, 300);
        if (mb_strlen($motivo) < 3) {
            return ['success' => false, 'message' => 'Diga por que ignorar (fica registrado para quem vier depois).'];
        }
        $this->pdo->prepare('INSERT INTO seguranca_recomendacoes_ignoradas (chave, ativo_id, motivo, por) VALUES (?, ?, ?, ?)')
            ->execute([$chave, $ativoId, $motivo, $usuario]);
        AuditService::registrar('Segurança', 'Recomendação ignorada', $this->definicoes()[$chave]['titulo'] . ($ativoId ? " (ativo #{$ativoId})" : ' (todas as máquinas)') . ": {$motivo}");

        return ['success' => true, 'message' => 'Ignorada. Dá para voltar a mostrar em "Ignoradas", no fim da página.'];
    }

    public function reativar(int $id): void
    {
        $this->pdo->prepare('DELETE FROM seguranca_recomendacoes_ignoradas WHERE id = ?')->execute([$id]);
    }

    /** @return list<array> recomendações ignoradas, com o nome da regra e da máquina */
    public function listarIgnoradas(): array
    {
        try {
            $linhas = $this->pdo->query(
                'SELECT i.*, a.codigo_patrimonio, a.nome FROM seguranca_recomendacoes_ignoradas i LEFT JOIN ativos a ON a.id = i.ativo_id ORDER BY i.em DESC'
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
        $defs = $this->definicoes();

        return array_map(fn ($l) => $l + ['titulo' => $defs[$l['chave']]['titulo'] ?? $l['chave']], $linhas);
    }

    // ------------------------------------------------------------ regras

    private function definicoes(): array
    {
        $diasDesde = static fn (?string $data) => $data ? (int)floor((time() - strtotime($data)) / 86400) : null;
        $padrao = (new SegurancaModuloService())->padrao();
        $alvo = self::SOMBRA_ALVO_PCT;

        return [
            'firewall_desligado' => [
                'titulo' => 'Firewall do Windows desligado',
                'porque' => 'Sem firewall a máquina aceita conexão de qualquer um na rede -- é por onde ransomware se espalha de uma máquina para outra (SMB, RDP).',
                'severidade' => 'alta',
                'acao' => ['tipo' => 'monitor', 'acao_monitor' => 'ligar_firewall', 'dados' => [], 'rotulo' => 'Ligar o firewall (todas as camadas)'],
                'por_maquina' => function (array $a, array $d) {
                    $desligadas = array_keys(array_filter(['Domínio' => $d['firewall_dominio'] ?? null, 'Privado' => $d['firewall_privado'] ?? null, 'Público' => $d['firewall_publico'] ?? null], fn ($v) => $v === 'Nao'));
                    return $desligadas ? 'desligado em: ' . implode(', ', $desligadas) : null;
                },
            ],
            'sem_copias_sombra' => [
                'titulo' => 'Sem nenhuma cópia de sombra (pontos de restauração)',
                'porque' => 'Se um ransomware criptografar os arquivos, não há versão anterior para recuperar. A proteção do sistema costuma vir desligada em máquinas reinstaladas.',
                'severidade' => 'alta',
                'acao' => ['tipo' => 'script', 'rotulo' => "Ligar a proteção do sistema ({$alvo}% do disco) e criar a primeira cópia", 'script' => self::scriptLigarProtecao($alvo)],
                'por_maquina' => function (array $a, array $d) {
                    $p = $d['protecao'] ?? null;
                    return is_array($p) && array_key_exists('sombra_copias', $p) && (int)$p['sombra_copias'] === 0 ? 'nenhuma cópia no disco do sistema' : null;
                },
            ],
            'sombra_pequena' => [
                'titulo' => "Área de cópias de sombra pequena (menos de 5% do disco)",
                'porque' => 'Com pouco espaço o Windows guarda só 1 ou 2 dias de cópias e apaga as antigas toda vez que cria uma nova -- protege pouco contra ransomware e dispara alerta de "shadow copies apagadas" (caso do AV-RECEPCAO em 13/10).',
                'severidade' => 'media',
                'acao' => ['tipo' => 'script', 'rotulo' => "Aumentar para {$alvo}% do disco", 'script' => self::scriptAumentarSombra($alvo)],
                'por_maquina' => function (array $a, array $d) {
                    $p = $d['protecao'] ?? null;
                    if (!is_array($p) || !isset($p['sombra_max_pct_disco']) || (int)($p['sombra_copias'] ?? 0) === 0) {
                        return null;
                    }
                    $pct = (float)$p['sombra_max_pct_disco'];
                    return $pct > 0 && $pct < 5
                        ? sprintf('%s%% do disco (%s de %s GB usados, %d cópia(s))', number_format($pct, 1, ',', ''), number_format((float)($p['sombra_usado_gb'] ?? 0), 1, ',', ''), number_format((float)($p['sombra_max_gb'] ?? 0), 1, ',', ''), (int)$p['sombra_copias'])
                        : null;
                },
            ],
            'defender_desligado' => [
                'titulo' => 'Sem antivírus ativo',
                'porque' => 'O Defender está desligado e não há outro antivírus ativo registrado no Windows -- nada impede um programa malicioso de rodar. (Com outro antivírus ativo, como o Acronis Cyber Protect, o Windows desliga o Defender e isso é normal: essas máquinas não aparecem aqui.)',
                'severidade' => 'alta',
                'acao' => ['tipo' => 'script', 'rotulo' => 'Ligar a proteção em tempo real do Defender', 'script' => self::scriptLigarDefender()],
                'por_maquina' => function (array $a, array $d) {
                    if (($d['defender_tempo_real'] ?? null) !== 'Nao' || $a['_outro_av'] !== null) {
                        return null;
                    }
                    return ($d['defender_ativo'] ?? '') === 'Nao' ? 'Defender desligado, nenhum outro antivírus encontrado' : 'Defender ligado, mas sem proteção em tempo real';
                },
            ],
            'assinaturas_antigas' => [
                'titulo' => 'Assinaturas do antivírus desatualizadas (mais de 7 dias)',
                'porque' => 'O Defender não reconhece ameaças novas. Costuma ser máquina sem Windows Update ou com atualização travada.',
                'severidade' => 'media',
                'acao' => ['tipo' => 'script', 'rotulo' => 'Atualizar as assinaturas agora', 'script' => self::scriptAtualizarAssinaturas()],
                'por_maquina' => function (array $a, array $d) use ($diasDesde) {
                    $dias = $diasDesde($d['defender_assinatura_data'] ?? null);
                    return $dias !== null && $dias > 7 && ($d['defender_ativo'] ?? '') === 'Sim' && $a['_outro_av'] === null ? "última atualização há {$dias} dias" : null;
                },
            ],
            'sem_verificacao_completa' => [
                'titulo' => 'Sem verificação completa do antivírus há mais de 90 dias',
                'porque' => 'A proteção em tempo real só olha o que é aberto; a verificação completa acha o que já estava no disco (o carregador Morvarith ficou 2 anos sem ser percebido).',
                'severidade' => 'baixa',
                'acao' => ['tipo' => 'script', 'rotulo' => 'Iniciar verificação completa (em segundo plano)', 'script' => self::scriptVerificacaoCompleta()],
                'por_maquina' => function (array $a, array $d) use ($diasDesde) {
                    $p = $d['protecao'] ?? null;
                    if (!is_array($p) || !array_key_exists('defender_ultima_completa', $p) || ($d['defender_ativo'] ?? '') !== 'Sim' || $a['_outro_av'] !== null) {
                        return null;
                    }
                    $dias = $diasDesde($p['defender_ultima_completa']);
                    return $dias === null ? 'nunca fez verificação completa' : ($dias > 90 ? "última há {$dias} dias" : null);
                },
            ],
            'ativador_pirata' => [
                'titulo' => 'Ativador pirata de Windows/Office (KMS)',
                'porque' => 'Licença irregular (risco em auditoria) e programa de origem duvidosa rodando como serviço/tarefa, com acesso de administrador. O certo é licenciar e depois remover o ativador pela aba Segurança da máquina.',
                'severidade' => 'media',
                'link' => ['rotulo' => 'Ver na máquina', 'aba' => 'abaSeguranca'],
                'por_maquina' => function (array $a, array $d, array $itens) {
                    $nomes = [];
                    foreach ($itens as $i) {
                        if (preg_match('/KMS-?R@1n|KMSAuto|AutoKMS|KMSpico|KMS_VL_ALL|SECOH-QAD|KMSInject|KMS_Aktivasyon|KMSELDI/i', ($i['nome'] ?? '') . ' ' . ($i['comando'] ?? '') . ' ' . ($i['caminho'] ?? ''))) {
                            $nomes[] = $i['nome'] ?? '?';
                        }
                    }
                    return $nomes ? implode(', ', array_slice(array_unique($nomes), 0, 4)) : null;
                },
            ],
            'politica_trava' => [
                'titulo' => 'Política que trava ferramentas do Windows',
                'porque' => 'Bloquear Gerenciador de Tarefas, Registro ou Prompt de Comando é tática de malware para impedir a remoção (caso do ARIIS). Se foi a TI que configurou, ignore para a máquina.',
                'severidade' => 'media',
                'link' => ['rotulo' => 'Ver na máquina', 'aba' => 'abaSeguranca'],
                'por_maquina' => function (array $a, array $d, array $itens) {
                    $nomes = array_map(fn ($i) => $i['nome'] ?? '?', array_filter($itens, fn ($i) => ($i['tipo'] ?? '') === 'politica'));
                    return $nomes ? implode(', ', array_slice($nomes, 0, 4)) : null;
                },
            ],
            'sem_contato' => [
                'titulo' => 'Máquina sem contato há mais de 7 dias',
                'porque' => 'Sem agente rodando não há proteção nem alerta. Pode estar desligada, fora da rede, ou o agente foi removido/parado.',
                'severidade' => 'baixa',
                'link' => ['rotulo' => 'Ver na máquina', 'aba' => ''],
                'por_maquina' => function (array $a) use ($diasDesde) {
                    $dias = $diasDesde($a['ultimo_heartbeat'] ?? $a['ultimo_checkin'] ?? null);
                    return $dias !== null && $dias > 7 ? "último contato há {$dias} dias" : null;
                },
            ],
            'detectores_desligados' => [
                'titulo' => 'Detectores do anti-ransomware desligados no padrão do cliente',
                'porque' => 'Arquivos-isca, cópias de sombra e mudança em massa são o que pega um ransomware no começo. Desligados no padrão, só valem nas máquinas onde foram ligados à mão.',
                'severidade' => 'media',
                'link' => ['rotulo' => 'Abrir a configuração', 'url' => '/ativos/seguranca/configuracao'],
                'geral' => fn () => empty($padrao['canary']) || empty($padrao['shadow_copy']) || empty($padrao['fim']),
            ],
            'criticos_antigos' => [
                'titulo' => 'Alertas críticos sem análise há mais de 24 horas',
                'porque' => 'Alerta crítico parado é o tipo de coisa que vira incidente. Analise e marque como resolvido, falso positivo ou ataque confirmado.',
                'severidade' => 'alta',
                'link' => ['rotulo' => 'Ver alertas', 'url' => '/ativos/seguranca?abertos=1&severidade=CRITICAL&dias=365'],
                'geral' => fn () => (int)$this->pdo->query("SELECT COUNT(*) FROM ativos_eventos_seguranca WHERE resolvido_em IS NULL AND severidade = 'CRITICAL' AND recebido_em < DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn() > 0,
            ],
        ];
    }

    // ------------------------------------------------------------ comandos prontos

    private static function scriptAumentarSombra(int $pct): string
    {
        return <<<PS
\$ErrorActionPreference = 'Stop'
\$u = \$env:SystemDrive
\$antes = (vssadmin list shadowstorage /for=\$u 2>&1 | Out-String)
\$r = vssadmin resize shadowstorage /for=\$u /on=\$u /maxsize={$pct}% 2>&1 | Out-String
if (\$LASTEXITCODE -ne 0) { throw "vssadmin falhou: \$r" }
"Área de cópias de sombra de \$u ajustada para {$pct}% do disco."
(vssadmin list shadowstorage /for=\$u 2>&1 | Select-String 'Máximo|Maximum|máximo') -join ' '
PS;
    }

    private static function scriptLigarProtecao(int $pct): string
    {
        return <<<PS
\$ErrorActionPreference = 'Stop'
\$u = \$env:SystemDrive
Enable-ComputerRestore -Drive "\$u\\"
vssadmin resize shadowstorage /for=\$u /on=\$u /maxsize={$pct}% 2>&1 | Out-Null
# O Windows só cria 1 ponto por dia por padrão; libera para criar agora.
New-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows NT\CurrentVersion\SystemRestore' -Name SystemRestorePointCreationFrequency -Value 0 -PropertyType DWord -Force | Out-Null
Checkpoint-Computer -Description 'RD.Intranet - primeira cópia' -RestorePointType MODIFY_SETTINGS
Remove-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows NT\CurrentVersion\SystemRestore' -Name SystemRestorePointCreationFrequency -ErrorAction SilentlyContinue
"Proteção do sistema ligada em \$u ({$pct}% do disco). Cópias agora: " + @(Get-CimInstance Win32_ShadowCopy).Count
PS;
    }

    private static function scriptLigarDefender(): string
    {
        return <<<'PS'
$ErrorActionPreference = 'Stop'
Set-MpPreference -DisableRealtimeMonitoring $false
Start-Sleep 3
$s = Get-MpComputerStatus
if (-not $s.RealTimeProtectionEnabled) { throw 'O Windows não deixou ligar (política de grupo, Proteção contra Adulteração ou outro antivírus instalado).' }
"Proteção em tempo real ligada."
PS;
    }

    private static function scriptAtualizarAssinaturas(): string
    {
        return <<<'PS'
$ErrorActionPreference = 'Stop'
Update-MpSignature
"Assinaturas atualizadas: " + (Get-MpComputerStatus).AntivirusSignatureLastUpdated
PS;
    }

    private static function scriptVerificacaoCompleta(): string
    {
        return <<<'PS'
$ErrorActionPreference = 'Stop'
Start-Process -FilePath "$env:ProgramFiles\Windows Defender\MpCmdRun.exe" -ArgumentList '-Scan', '-ScanType', '2' -WindowStyle Hidden
"Verificação completa iniciada às $(Get-Date -Format 'HH:mm') (roda em segundo plano; pode levar horas)."
PS;
    }

    // ------------------------------------------------------------ apoio

    /**
     * Outro antivírus ativo na máquina: o que o agente 1.0.70+ lê da Central de Segurança do
     * Windows ou, para agente mais antigo, um antivírus conhecido na lista de programas.
     * @return array<int, string> ativo_id -> nome
     */
    private function outroAntivirusPorAtivo(): array
    {
        $mapa = [];
        try {
            foreach ($this->pdo->query("SELECT id, detalhes FROM ativos WHERE origem = 'agente'")->fetchAll(PDO::FETCH_ASSOC) as $a) {
                $p = (json_decode((string)$a['detalhes'], true) ?: [])['protecao'] ?? [];
                if (!empty($p['antivirus_outro']) && !empty($p['antivirus_outro_ligado'])) {
                    $mapa[(int)$a['id']] = (string)$p['antivirus_outro'];
                }
            }
            $linhas = $this->pdo->query(
                "SELECT ativo_id, MIN(nome) nome FROM ativos_programas
                 WHERE nome REGEXP 'acronis cyber protect|kaspersky|eset (endpoint|nod32|internet|smart|security)|bitdefender|avast|^avg |norton|mcafee (endpoint|total|livesafe|antivirus)|sophos|trend micro|panda (dome|endpoint|adaptive)|avira|webroot|sentinel ?one|crowdstrike|cylance|f-secure|g data|emsisoft|malwarebytes'
                 GROUP BY ativo_id"
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($linhas as $l) {
                $mapa[(int)$l['ativo_id']] ??= (string)$l['nome'];
            }
        } catch (\Throwable) {
            // tabela de programas ausente: decide só pelo agente
        }

        return $mapa;
    }

    /** Itens do último retrato de inicialização de cada máquina. */
    private function retratosInicializacao(): array
    {
        try {
            $linhas = $this->pdo->query("SELECT ativo_id, dados FROM ativos_seguranca_retratos WHERE tipo = 'inicializacao'")->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
        $porAtivo = [];
        foreach ($linhas as $l) {
            $porAtivo[(int)$l['ativo_id']] = json_decode($l['dados'], true)['itens'] ?? [];
        }

        return $porAtivo;
    }

    /** @return array<string, array<int, true>> chave -> ativo_id (0 = regra inteira) */
    private function ignoradas(): array
    {
        try {
            $linhas = $this->pdo->query('SELECT chave, ativo_id FROM seguranca_recomendacoes_ignoradas')->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
        $mapa = [];
        foreach ($linhas as $l) {
            $mapa[$l['chave']][(int)($l['ativo_id'] ?? 0)] = true;
        }

        return $mapa;
    }

    /** Última ação enviada por recomendação+máquina, com o estado do comando. */
    private function ultimasAcoes(): array
    {
        try {
            $linhas = $this->pdo->query(
                "SELECT r.chave, r.ativo_id, r.em, r.por, s.status, s.resultado, s.erro_mensagem
                 FROM seguranca_recomendacoes_acoes r
                 JOIN (SELECT MAX(id) id FROM seguranca_recomendacoes_acoes GROUP BY chave, ativo_id) u ON u.id = r.id
                 LEFT JOIN ativos_solicitacoes s ON s.id = r.solicitacao_id"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
        $mapa = [];
        foreach ($linhas as $l) {
            $resultado = json_decode((string)($l['resultado'] ?? ''), true) ?: [];
            $ok = $l['status'] === 'concluido' && (int)($resultado['codigo_saida'] ?? 0) === 0 && empty($resultado['erro']) && ($resultado['sucesso'] ?? true) !== false;
            $mapa[$l['chave']][(int)$l['ativo_id']] = [
                'em' => $l['em'],
                'por' => $l['por'],
                'estado' => in_array($l['status'], ['pendente', 'enviado', 'em_andamento'], true) ? 'aguardando' : ($ok ? 'ok' : 'falhou'),
                'mensagem' => mb_substr(trim(preg_replace('/\s+/', ' ', preg_replace('/<[^>]+>|#< CLIXML/', ' ', (string)($resultado['saida'] ?? $resultado['mensagem'] ?? '') . ' ' . (string)($resultado['erro'] ?? $l['erro_mensagem'] ?? '')))), 0, 240),
            ];
        }

        return $mapa;
    }
}
