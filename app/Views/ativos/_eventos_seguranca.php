<?php
/**
 * Tabela de eventos de segurança -- usada na aba Segurança da ficha e na
 * Central de Segurança. Espera:
 *   $eventosTabela   lista de eventos (SegurancaEventoService::listarDoAtivo/listarTodos)
 *   $mostrarMaquina  bool -- coluna "Máquina" (só na Central)
 *   $podeEditarTabela bool -- botões de ação
 */

use App\Components\Badge;
use App\Services\SegurancaEventoService;

$rotulosAcao = [
    'nenhuma' => '—',
    'processo_encerrado' => 'Processo encerrado',
    'rede_isolada' => 'Rede isolada',
    'isolamento_pendente' => 'Isolamento pendente',
    'isolamento_cancelado' => 'Isolamento cancelado',
];

$rotulosDetalhe = [
    'caminho' => 'Arquivo',
    'mudanca' => 'O que aconteceu',
    'pasta' => 'Pasta',
    'eventos' => 'Arquivos afetados',
    'janela_segundos' => 'Janela (s)',
    'alterados' => 'Alterados',
    'extensao_trocada' => 'Com extensão trocada',
    'excluidos' => 'Excluídos',
    'extensoes_novas' => 'Extensões novas',
    'estouro_buffer' => 'Estouro do monitor (volume muito alto)',
    'exemplos' => 'Exemplos',
    'contagem_anterior' => 'Shadow copies antes',
    'contagem_atual' => 'Shadow copies depois',
    'linha_comando' => 'Descrição',
    'processo' => 'Processo',
    'pid' => 'PID',
    'motivo' => 'Motivo',
    'por' => 'Por',
    'evento_origem' => 'Evento de origem',
    'solicitacao_id' => 'Comando nº',
    'apagadas_pelo_windows' => 'Apagadas pelo próprio Windows (Volsnap)',
    'sem_explicacao' => 'Sem explicação',
    'temporarias' => 'Temporárias (criadas há menos de 2h)',
    'expiradas' => 'Expiradas (mais de 55 dias)',
    'extensao_suspeita' => 'Com extensão suspeita',
    'conteudo_invalido' => 'Conteúdo inválido na amostra',
    'amostrados' => 'Arquivos amostrados',
    'extensao_ransomware' => 'Com extensão conhecida de ransomware',
    'nota_resgate' => 'Nota de resgate',
    'novos' => 'Situação',
    'itens' => 'Itens',
    'nome' => 'Nome',
    'nivel' => 'Nível',
    'pontos' => 'Pontuação de risco',
    'motivos' => 'Por que é suspeito',
    'assinatura' => 'Assinatura digital',
    'assinante' => 'Assinado por',
    'arquivo_criado_em' => 'Arquivo criado em',
    'oculto' => 'Arquivo oculto',
    'pai_nome' => 'Iniciado por',
    'pai_pid' => 'PID do pai',
    'iniciado_em' => 'Iniciado em',
    'como_system' => 'Roda como SYSTEM',
    'servico' => 'Serviço',
    'ips_distintos' => 'IPs diferentes',
    'janela_minutos' => 'Janela (min)',
    'tentativas' => 'Tentativas',
    'portas' => 'Portas',
    'portas_sensiveis' => 'Portas sensíveis',
    'destinos' => 'Destinos',
    'amostra_ips' => 'Amostra de IPs',
    'faixas' => 'Faixas',
    'estabelecidas' => 'Conexões estabelecidas',
    'estabelecidas_total' => 'Conexões estabelecidas (total)',
    'escutando' => 'Portas abertas (escutando)',
    'internet' => 'Na internet',
    'lan' => 'Na rede local',
    'filhos' => 'Processos filhos',
    'sha256' => 'SHA-256',
];

// Item da inicialização / processo analisado pelo agente (agente 1.0.56+).
$rotulosTipoItem = [
    'servico' => 'Serviço', 'tarefa' => 'Tarefa agendada', 'registro' => 'Registro (Run)', 'pasta_inicializar' => 'Pasta Inicializar',
    'winlogon' => 'Winlogon', 'ifeo' => 'IFEO -- sequestro de programa', 'wmi' => 'Assinatura WMI', 'appinit' => 'AppInit', 'politica' => 'Política que trava o Windows',
];
$rotulosAssinatura = [
    'valida' => ['assinado', 'success'], 'catalogo' => ['assinado (catálogo do Windows)', 'success'], 'pacote' => ['app da Microsoft Store', 'success'],
    'expirada' => ['assinatura expirada', 'warning'], 'nao_confiavel' => ['assinatura de emissor não confiável', 'warning'],
    'invalida' => ['assinatura INVÁLIDA', 'danger'], 'ausente' => ['sem assinatura', 'danger'],
];
$corNivel = ['critico' => 'danger', 'suspeito' => 'warning', 'atencao' => 'info', 'ok' => 'success'];

/** Lista de objetos do agente (itens, destinos...) vira algo legível em vez de JSON cru. */
$ehListaDeObjetos = static fn ($v): bool => is_array($v) && $v !== [] && array_is_list($v) && is_array($v[0]);

$renderItemAnalisado = static function (array $item) use ($rotulosTipoItem, $rotulosAssinatura, $corNivel, $rotulosDetalhe): string {
    $h = static fn ($v) => htmlspecialchars((string)$v);
    $nivel = (string)($item['nivel'] ?? '');
    $html = '<div class="border rounded p-2 mb-2 bg-body-tertiary">';
    $html .= '<div class="d-flex flex-wrap align-items-center gap-2 mb-1">';
    if ($nivel !== '') {
        $html .= Badge::make(['critico' => 'CRÍTICO', 'suspeito' => 'SUSPEITO', 'atencao' => 'ATENÇÃO'][$nivel] ?? mb_strtoupper($nivel), $corNivel[$nivel] ?? 'secondary');
    }
    $html .= '<strong>' . $h($item['nome'] ?? '?') . '</strong>';
    if (!empty($item['tipo'])) {
        $html .= '<span class="text-muted">· ' . $h($rotulosTipoItem[$item['tipo']] ?? $item['tipo']) . '</span>';
    }
    if (isset($item['pontos'])) {
        $html .= '<span class="ms-auto text-muted">risco ' . (int)$item['pontos'] . '/100</span>';
    }
    $html .= '</div>';

    if (!empty($item['motivos']) && is_array($item['motivos'])) {
        $html .= '<ul class="mb-1 ps-3">';
        foreach ($item['motivos'] as $m) {
            $html .= '<li>' . $h($m) . '</li>';
        }
        $html .= '</ul>';
    }

    $linhas = [];
    if (!empty($item['caminho'])) {
        $linhas['Arquivo'] = '<code class="text-break">' . $h($item['caminho']) . '</code>' . (isset($item['existe']) && !$item['existe'] ? ' <span class="text-danger">(não existe mais)</span>' : '');
    }
    if (!empty($item['comando']) && ($item['comando'] ?? '') !== ($item['caminho'] ?? '')) {
        $linhas['Comando'] = '<code class="text-break">' . $h($item['comando']) . '</code>';
    }
    if (!empty($item['linha_comando'])) {
        $linhas['Linha de comando'] = '<code class="text-break">' . $h($item['linha_comando']) . '</code>';
    }
    if (!empty($item['assinatura'])) {
        [$txt, $cor] = $rotulosAssinatura[$item['assinatura']] ?? [$item['assinatura'], 'secondary'];
        $linhas['Assinatura'] = '<span class="text-' . $cor . '">' . $h($txt) . '</span>' . (!empty($item['assinante']) ? ' -- ' . $h($item['assinante']) : '');
    }
    foreach (['conta' => 'Conta', 'pai_nome' => 'Iniciado por', 'pid' => 'PID', 'estado' => 'Estado', 'inicio' => 'Início'] as $k => $rot) {
        if (isset($item[$k]) && $item[$k] !== '' && $item[$k] !== null) {
            $linhas[$rot] = $h($item[$k]);
        }
    }
    if (!empty($item['arquivo_criado_em'])) {
        $linhas['Arquivo criado em'] = $h(data_br($item['arquivo_criado_em'], 'd/m/Y H:i'));
    }
    if (!empty($item['oculto'])) {
        $linhas['Arquivo oculto'] = '<span class="text-danger">sim</span>';
    }
    if (!empty($item['origem'])) {
        $linhas['Onde está registrado'] = '<code class="text-break text-muted">' . $h($item['origem']) . '</code>';
    }
    if (!empty($item['sha256'])) {
        $linhas['SHA-256'] = '<code class="text-break text-muted" style="font-size:.75em">' . $h($item['sha256']) . '</code>'
            . ' <a href="https://www.virustotal.com/gui/file/' . $h(strtolower($item['sha256'])) . '" target="_blank" rel="noopener">VirusTotal</a>';
    }
    // Campos que não têm lugar acima (ex.: detecção de rede) aparecem do jeito simples.
    $conhecidos = ['tipo', 'nome', 'id', 'caminho', 'existe', 'comando', 'linha_comando', 'assinatura', 'assinante', 'conta', 'pai_nome', 'pid', 'estado', 'inicio',
                   'arquivo_criado_em', 'oculto', 'origem', 'sha256', 'nivel', 'pontos', 'motivos', 'ms'];
    foreach ($item as $k => $v) {
        if (in_array($k, $conhecidos, true) || $v === null || $v === '' || $v === [] || $v === false || is_array($v)) {
            continue;
        }
        $linhas[$rotulosDetalhe[$k] ?? $k] = $h(is_bool($v) ? 'sim' : $v);
    }

    if ($linhas) {
        $html .= '<table class="table table-sm table-borderless mb-0 small"><tbody>';
        foreach ($linhas as $rot => $valor) {
            $html .= '<tr><td class="text-muted text-nowrap pe-2 py-0" style="width:1%">' . $h($rot) . '</td><td class="py-0">' . $valor . '</td></tr>';
        }
        $html .= '</tbody></table>';
    }

    return $html . '</div>';
};

/** Lista de objetos genérica (destinos, portas, tentativas...) vira tabelinha. */
$renderTabela = static function (array $lista) use ($rotulosDetalhe): string {
    $h = static fn ($v) => htmlspecialchars((string)$v);
    $colunas = [];
    foreach ($lista as $linha) {
        foreach ((array)$linha as $k => $v) {
            if (!is_array($v)) {
                $colunas[$k] = true;
            }
        }
    }
    $html = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0 small"><thead><tr>';
    foreach (array_keys($colunas) as $c) {
        $html .= '<th class="fw-normal text-muted">' . $h($rotulosDetalhe[$c] ?? $c) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($lista as $linha) {
        $html .= '<tr>';
        foreach (array_keys($colunas) as $c) {
            $v = $linha[$c] ?? '';
            $html .= '<td>' . $h(is_bool($v) ? ($v ? 'sim' : 'não') : $v) . '</td>';
        }
        $html .= '</tr>';
    }
    return $html . '</tbody></table></div>';
};

$formatarValor = static function ($valor): string {
    if (is_bool($valor)) {
        return $valor ? 'sim' : 'não';
    }
    if (is_array($valor)) {
        return implode("\n", array_map(fn ($v) => is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE), $valor));
    }
    return (string)$valor;
};
?>
<?php if (empty($eventosTabela)): ?>
    <p class="text-muted small p-3 mb-0">Nenhum evento registrado.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead>
                <tr>
                    <th>Quando</th>
                    <?php if ($mostrarMaquina): ?><th>Máquina</th><?php endif; ?>
                    <th>Evento</th>
                    <th>Detalhe</th>
                    <th>Resposta</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($eventosTabela as $ev): ?>
                    <?php
                        $corSeveridade = ['CRITICAL' => 'danger', 'WARNING' => 'warning', 'INFO' => 'secondary'][$ev['severidade']] ?? 'secondary';
                        $pendente = !empty($ev['isolamento_pendente_ate']) && $ev['resolvido_em'] === null;
                        $detalhes = is_array($ev['detalhes'] ?? null) ? $ev['detalhes'] : [];
                        $execucao = $ev['execucao'] ?? null;
                    ?>
                    <tr class="<?= $ev['resolvido_em'] ? 'text-muted' : '' ?>">
                        <td class="small text-nowrap align-top"><?= htmlspecialchars(data_br($ev['ocorrido_em'] ?? $ev['recebido_em'], 'd/m/Y H:i:s')) ?></td>
                        <?php if ($mostrarMaquina): ?>
                            <td class="small text-nowrap align-top">
                                <a href="<?= url('/ativos/ver?id=' . (int)$ev['ativo_id'] . '#abaSeguranca') ?>"><?= htmlspecialchars($ev['codigo_patrimonio'] ?? ('#' . $ev['ativo_id'])) ?></a>
                                <div class="text-muted"><?= htmlspecialchars($ev['ativo_nome'] ?? '') ?></div>
                            </td>
                        <?php endif; ?>
                        <td class="small align-top">
                            <?= Badge::make($ev['severidade'], $corSeveridade) ?>
                            <?= htmlspecialchars(SegurancaEventoService::rotuloTipo($ev['tipo'])) ?>
                        </td>
                        <td class="small align-top" style="min-width:420px; max-width:640px; word-break:break-word">
                            <?= htmlspecialchars($ev['resumo']) ?>
                            <?php if ($detalhes || $execucao): ?>
                                <details class="mt-1">
                                    <summary class="text-primary" style="cursor:pointer">Ver detalhes</summary>
                                    <?php
                                        // Evento de processo suspeito: os próprios detalhes SÃO o item analisado.
                                        $detalheEhItem = isset($detalhes['nivel'], $detalhes['motivos']) && !isset($detalhes['itens']);
                                    ?>
                                    <?php if ($detalheEhItem): ?>
                                        <div class="mt-2"><?= $renderItemAnalisado($detalhes) ?></div>
                                    <?php endif; ?>
                                    <?php if (!$detalheEhItem && !empty($detalhes['itens']) && $ehListaDeObjetos($detalhes['itens'])): ?>
                                        <div class="mt-2">
                                            <?php foreach ($detalhes['itens'] as $item): ?>
                                                <?= $renderItemAnalisado($item) ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <dl class="row small mb-0 mt-2">
                                        <?php foreach ($detalheEhItem ? [] : $detalhes as $chave => $valor): ?>
                                            <?php if ($valor === null || $valor === '' || $valor === [] || ($chave === 'itens' && $ehListaDeObjetos($valor))) continue; ?>
                                            <?php if ($chave === 'novos'): ?>
                                                <dt class="col-sm-4 fw-normal text-muted">Situação</dt>
                                                <dd class="col-sm-8 mb-1"><?= $valor ? 'itens novos ou que pioraram desde a última análise' : 'primeira análise desta máquina (tudo que já existia)' ?></dd>
                                                <?php continue; ?>
                                            <?php endif; ?>
                                            <dt class="col-sm-4 fw-normal text-muted"><?= htmlspecialchars($rotulosDetalhe[$chave] ?? $chave) ?></dt>
                                            <?php if ($ehListaDeObjetos($valor)): ?>
                                                <dd class="col-sm-8 mb-1"><?= $renderTabela($valor) ?></dd>
                                            <?php else: ?>
                                                <dd class="col-sm-8 mb-1" style="white-space:pre-line"><?= htmlspecialchars($formatarValor($valor)) ?></dd>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                        <dt class="col-sm-4 fw-normal text-muted">Recebido pelo portal</dt>
                                        <dd class="col-sm-8 mb-1"><?= htmlspecialchars(data_br($ev['recebido_em'], 'd/m/Y H:i:s')) ?></dd>
                                        <?php if ($ev['resolvido_em']): ?>
                                            <dt class="col-sm-4 fw-normal text-muted">Resolvido</dt>
                                            <dd class="col-sm-8 mb-1"><?= htmlspecialchars(data_br($ev['resolvido_em'], 'd/m/Y H:i')) ?> por <?= htmlspecialchars($ev['resolvido_por'] ?? '?') ?>
                                                <?= !empty($ev['resolucao']) ? '(' . htmlspecialchars(SegurancaEventoService::RESOLUCOES[$ev['resolucao']] ?? $ev['resolucao']) . ')' : '' ?></dd>
                                        <?php endif; ?>
                                    </dl>
                                    <?php if ($execucao): ?>
                                        <div class="small text-muted mt-2">
                                            Execução na máquina: <?= htmlspecialchars($execucao['status']) ?>
                                            <?= $execucao['respondido_em'] ? 'em ' . htmlspecialchars(data_br($execucao['respondido_em'], 'd/m/Y H:i:s')) : '(aguardando a máquina)' ?>
                                        </div>
                                        <?php if ($execucao['saida'] !== ''): ?>
                                            <pre class="small bg-light border rounded p-2 mb-1" style="white-space:pre-wrap"><?= htmlspecialchars($execucao['saida']) ?></pre>
                                        <?php endif; ?>
                                        <?php if ($execucao['erro'] !== ''): ?>
                                            <pre class="small bg-danger-subtle border border-danger-subtle rounded p-2 mb-0" style="white-space:pre-wrap"><?= htmlspecialchars($execucao['erro']) ?></pre>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </details>
                            <?php endif; ?>
                        </td>
                        <td class="small text-nowrap align-top">
                            <?php if ($pendente): ?>
                                <span class="text-danger">Isola às <?= htmlspecialchars(data_br($ev['isolamento_pendente_ate'], 'H:i')) ?></span>
                            <?php else: ?>
                                <?= htmlspecialchars($rotulosAcao[$ev['acao_automatica']] ?? $ev['acao_automatica']) ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap align-top">
                            <?php if ($podeEditarTabela && $pendente): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger js-seguranca-acao" data-acao="cancelar-isolamento"
                                        data-ativo="<?= (int)$ev['ativo_id'] ?>" data-evento="<?= (int)$ev['id'] ?>"
                                        data-confirmar="Cancelar o isolamento automático deste evento? Use só se tiver certeza de que é falso positivo.">Cancelar isolamento</button>
                            <?php endif; ?>
                            <?php if ($podeEditarTabela && $ev['severidade'] !== 'INFO' && $ev['resolvido_em'] === null): ?>
                                <div class="btn-group btn-group-sm" role="group" aria-label="Resolver evento">
                                    <button type="button" class="btn btn-outline-warning js-seguranca-acao" data-acao="resolver-evento" data-resolucao="falso_positivo"
                                            data-ativo="<?= (int)$ev['ativo_id'] ?>" data-evento="<?= (int)$ev['id'] ?>"
                                            data-pedir-nota="O que causou este falso positivo? (ex.: backup noturno, sincronização do OneDrive) -- ajuda a criar a exceção certa."
                                            title="Não foi ataque -- entra na aba Exceções e falsos positivos">Falso positivo</button>
                                    <button type="button" class="btn btn-outline-secondary js-seguranca-acao" data-acao="resolver-evento" data-resolucao="resolvido"
                                            data-ativo="<?= (int)$ev['ativo_id'] ?>" data-evento="<?= (int)$ev['id'] ?>">Resolvido</button>
                                </div>
                            <?php elseif ($ev['resolvido_em']): ?>
                                <?php
                                    $resolucao = $ev['resolucao'] ?? null;
                                    $corResolucao = ['falso_positivo' => 'warning', 'ataque_confirmado' => 'danger'][$resolucao] ?? 'secondary';
                                ?>
                                <?php if ($resolucao && $resolucao !== 'resolvido'): ?>
                                    <?= Badge::make(SegurancaEventoService::RESOLUCOES[$resolucao] ?? $resolucao, $corResolucao) ?>
                                <?php endif; ?>
                                <span class="small">por <?= htmlspecialchars($ev['resolvido_por'] ?? '?') ?></span>
                                <?php if (!empty($ev['resolucao_nota'])): ?>
                                    <div class="small text-muted text-wrap" style="max-width:220px"><?= htmlspecialchars($ev['resolucao_nota']) ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
