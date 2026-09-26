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
];

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
                        <td class="small align-top" style="max-width:520px; word-break:break-word">
                            <?= htmlspecialchars($ev['resumo']) ?>
                            <?php if ($detalhes || $execucao): ?>
                                <details class="mt-1">
                                    <summary class="text-primary" style="cursor:pointer">Ver detalhes</summary>
                                    <dl class="row small mb-0 mt-2">
                                        <?php foreach ($detalhes as $chave => $valor): ?>
                                            <?php if ($valor === null || $valor === '' || $valor === []) continue; ?>
                                            <dt class="col-sm-4 fw-normal text-muted"><?= htmlspecialchars($rotulosDetalhe[$chave] ?? $chave) ?></dt>
                                            <dd class="col-sm-8 mb-1" style="white-space:pre-line"><?= htmlspecialchars($formatarValor($valor)) ?></dd>
                                        <?php endforeach; ?>
                                        <dt class="col-sm-4 fw-normal text-muted">Recebido pelo portal</dt>
                                        <dd class="col-sm-8 mb-1"><?= htmlspecialchars(data_br($ev['recebido_em'], 'd/m/Y H:i:s')) ?></dd>
                                        <?php if ($ev['resolvido_em']): ?>
                                            <dt class="col-sm-4 fw-normal text-muted">Resolvido</dt>
                                            <dd class="col-sm-8 mb-1"><?= htmlspecialchars(data_br($ev['resolvido_em'], 'd/m/Y H:i')) ?> por <?= htmlspecialchars($ev['resolvido_por'] ?? '?') ?></dd>
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
                                <button type="button" class="btn btn-sm btn-outline-secondary js-seguranca-acao" data-acao="resolver-evento"
                                        data-ativo="<?= (int)$ev['ativo_id'] ?>" data-evento="<?= (int)$ev['id'] ?>">Marcar resolvido</button>
                            <?php elseif ($ev['resolvido_em']): ?>
                                <span class="small">Resolvido por <?= htmlspecialchars($ev['resolvido_por'] ?? '?') ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
