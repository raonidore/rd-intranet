<?php
ob_start();

use App\Components\Alert;

function wppDuracao(?int $segundos): string
{
    if ($segundos === null) {
        return '—';
    }

    $h = intdiv($segundos, 3600);
    $m = intdiv($segundos % 3600, 60);
    $s = $segundos % 60;

    return sprintf('%02d:%02d:%02d', $h, $m, $s);
}

$rotulosPeriodoGeral = ['mes' => 'Mês', 'semana' => 'Semana', 'dia' => 'Dia', 'hora' => 'Hora'];
$rotulosPeriodoRanking = ['geral' => 'Histórico geral', 'mes' => 'Este mês', 'semana' => 'Esta semana', 'hoje' => 'Hoje', 'hora' => 'Última hora'];
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-bar-chart-line me-1"></i> WhatsApp - Estatísticas</h4>
    <small class="text-muted">Volume de atendimento, tempos e satisfação (NPS) por período e por atendente.</small>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $aba === 'tempo-real' ? 'active' : '' ?>" href="<?= url('/whatsapp/estatisticas?aba=tempo-real') ?>">Em tempo real</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $aba === 'geral' ? 'active' : '' ?>" href="<?= url('/whatsapp/estatisticas?aba=geral') ?>">Geral</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $aba === 'ranking' ? 'active' : '' ?>" href="<?= url('/whatsapp/estatisticas?aba=ranking') ?>">Ranking</a>
    </li>
</ul>

<?php if ($aba === 'tempo-real'): ?>

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-muted small">Em espera no chatbot</div>
                    <div class="fs-2"><?= (int)$tempoReal['em_espera_bot'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-muted small">Na fila</div>
                    <div class="fs-2"><?= (int)$tempoReal['fila'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-muted small">Em atendimento</div>
                    <div class="fs-2"><?= (int)$tempoReal['em_atendimento'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-muted small">Atendentes ativos agora</div>
                    <div class="fs-2"><?= (int)$tempoReal['atendentes_ativos'] ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($tempoReal['fila_por_setor'])): ?>
        <div class="card border-0 shadow-sm" style="max-width:600px">
            <div class="card-body">
                <h6 class="mb-2">Fila por setor</h6>
                <?php foreach ($tempoReal['fila_por_setor'] as $item): ?>
                    <div class="d-flex justify-content-between border-bottom py-1">
                        <span><?= htmlspecialchars($item['setor_nome']) ?></span>
                        <strong><?= (int)$item['total'] ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

<?php elseif ($aba === 'geral'): ?>

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div class="btn-group btn-group-sm">
            <?php foreach ($rotulosPeriodoGeral as $valor => $rotulo): ?>
                <a href="<?= url('/whatsapp/estatisticas?aba=geral&periodo_geral=' . $valor) ?>"
                   class="btn <?= $periodoGeral === $valor ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $rotulo ?></a>
            <?php endforeach; ?>
        </div>
        <input type="text" id="buscaGeral" class="form-control form-control-sm" style="max-width:220px" placeholder="Buscar setor...">
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($geral['linhas'])): ?>
                <p class="text-muted text-center py-4 mb-0">Nenhum atendimento encerrado ainda nesse período.</p>
            <?php else: ?>
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Período</th>
                            <th>Setor</th>
                            <th>Total de Atendimentos</th>
                            <th>Tempo Total de Atendimento</th>
                            <th>Tempo Médio de Espera</th>
                        </tr>
                    </thead>
                    <tbody id="corpoTabelaGeral">
                        <?php foreach ($geral['linhas'] as $linha): ?>
                            <tr data-busca="<?= htmlspecialchars(mb_strtolower($linha['setor_nome'])) ?>">
                                <td><?= htmlspecialchars($linha['periodo_label']) ?></td>
                                <td><?= htmlspecialchars($linha['setor_nome']) ?></td>
                                <td><?= (int)$linha['total_atendimentos'] ?></td>
                                <td><?= wppDuracao((int)$linha['tempo_total_atendimento']) ?></td>
                                <td><?= wppDuracao($linha['tempo_medio_espera'] !== null ? (int)round((float)$linha['tempo_medio_espera']) : null) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold">
                            <td colspan="2">Total</td>
                            <td><?= (int)$geral['total_atendimentos'] ?></td>
                            <td><?= wppDuracao($geral['tempo_total']) ?></td>
                            <td><?= wppDuracao($geral['tempo_medio_espera']) ?></td>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.getElementById('buscaGeral').addEventListener('input', function () {
        const filtro = this.value.trim().toLowerCase();
        document.querySelectorAll('#corpoTabelaGeral tr').forEach(function (linha) {
            linha.style.display = linha.dataset.busca.includes(filtro) ? '' : 'none';
        });
    });
    </script>

<?php elseif ($aba === 'ranking'): ?>

    <div class="btn-group btn-group-sm mb-3">
        <?php foreach ($rotulosPeriodoRanking as $valor => $rotulo): ?>
            <a href="<?= url('/whatsapp/estatisticas?aba=ranking&periodo_ranking=' . $valor) ?>"
               class="btn <?= $periodoRanking === $valor ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $rotulo ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($ranking)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted py-4">
                <i class="bi bi-trophy" style="font-size:2rem;"></i>
                <p class="mb-0 mt-2">Nenhum atendimento encerrado nesse período ainda.</p>
            </div>
        </div>
    <?php else: ?>

        <?php $podio = array_slice($ranking, 0, 3); ?>
        <?php if (count($podio) >= 1): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="row text-center justify-content-center g-4">
                        <?php foreach ($podio as $posicao => $item): ?>
                            <?php
                            $medalha = ['🥇', '🥈', '🥉'][$posicao] ?? '';
                            $corBorda = [1 => 'border-warning', 0 => 'border-secondary', 2 => 'border-danger'][$posicao] ?? 'border-secondary';
                            ?>
                            <div class="col-4">
                                <div class="rounded-circle bg-light border <?= $corBorda ?> d-inline-flex align-items-center justify-content-center mb-2"
                                     style="width:64px; height:64px; font-size:1.5rem; font-weight:bold;">
                                    <?= htmlspecialchars(mb_substr($item['usuario_nome'], 0, 1)) ?>
                                </div>
                                <div class="fw-bold"><?= htmlspecialchars($item['usuario_nome']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($item['setor_nome'] ?? '-') ?></div>
                                <div class="mt-1"><?= $medalha ?> <span class="badge text-bg-light border"><?= $item['total_atendimentos'] ?> atend.</span></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Atendente</th>
                            <th style="width:35%">Atendimentos</th>
                            <th>Tempo médio</th>
                            <th>Índice de Satisfação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $maiorTotal = max(array_column($ranking, 'total_atendimentos')); ?>
                        <?php foreach ($ranking as $indice => $item): ?>
                            <?php $pct = $maiorTotal > 0 ? round(($item['total_atendimentos'] / $maiorTotal) * 100) : 0; ?>
                            <tr>
                                <td>#<?= $indice + 1 ?></td>
                                <td>
                                    <?= htmlspecialchars($item['usuario_nome']) ?>
                                    <div class="text-muted small"><?= htmlspecialchars($item['setor_nome'] ?? '-') ?></div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="flex-grow-1 bg-light rounded" style="height:10px">
                                            <div class="bg-primary rounded" style="height:10px; width:<?= $pct ?>%"></div>
                                        </div>
                                        <span class="text-nowrap"><?= $item['total_atendimentos'] ?></span>
                                    </div>
                                </td>
                                <td><i class="bi bi-clock text-muted"></i> <?= wppDuracao($item['tempo_medio']) ?></td>
                                <td>
                                    <?php if ($item['indice_satisfacao'] !== null): ?>
                                        <span class="badge text-bg-<?= $item['indice_satisfacao'] >= 70 ? 'success' : ($item['indice_satisfacao'] >= 40 ? 'warning' : 'danger') ?>">
                                            <?= htmlspecialchars((string)$item['indice_satisfacao']) ?>%
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>

<div class="card border-0 shadow-sm mt-4" style="max-width:900px">
    <div class="card-header bg-white" style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#configNps">
        <i class="bi bi-gear me-1"></i> Configurar pesquisa de satisfação (NPS)
    </div>
    <div class="collapse" id="configNps">
        <div class="card-body">
            <p class="text-muted small">
                Ativa por setor em <a href="<?= url('/whatsapp/setores') ?>">Setores</a>. As notas/opções abaixo de cada
                pergunta são fixas (alimentam as estatísticas) -- só o texto da pergunta em si pode ser mudado.
            </p>
            <form method="post" action="<?= url('/whatsapp/estatisticas/nps') ?>">
                <div class="mb-2">
                    <label class="form-label small text-muted">Mensagem de espera (mandada antes da 1ª pergunta)</label>
                    <textarea name="mensagem_espera" class="form-control" rows="2" required><?= htmlspecialchars($npsMensagemEspera) ?></textarea>
                </div>
                <div class="mb-2">
                    <label class="form-label small text-muted">Pergunta sobre o atendente (nota fixa de 1 a 5, legenda automática)</label>
                    <textarea name="pergunta_atendente" class="form-control" rows="2" required><?= htmlspecialchars($npsPerguntaAtendente) ?></textarea>
                </div>
                <div class="mb-2">
                    <label class="form-label small text-muted">Pergunta sobre a resolução (opções fixas 1-Sim / 2-Não)</label>
                    <textarea name="pergunta_resolucao" class="form-control" rows="2" required><?= htmlspecialchars($npsPerguntaResolucao) ?></textarea>
                </div>
                <div class="mb-2">
                    <label class="form-label small text-muted">Agradecimento (mandada depois das 2 respostas)</label>
                    <textarea name="agradecimento" class="form-control" rows="2" required><?= htmlspecialchars($npsAgradecimento) ?></textarea>
                </div>
                <div class="mb-3" style="max-width:200px">
                    <label class="form-label small text-muted">Tempo limite pra responder (minutos)</label>
                    <input type="number" name="timeout_minutos" class="form-control form-control-sm" min="1" max="1440" value="<?= (int)$npsTimeoutMinutos ?>" required>
                </div>
                <p class="text-muted small mb-2">Use <code>{nome}</code> pro nome do cliente. Emojis também funcionam.</p>
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
            </form>

            <hr>
            <h6 class="mb-2">Resumo (últimos 90 dias)</h6>
            <div class="row g-3">
                <div class="col-4">
                    <div class="text-muted small">Respostas</div>
                    <div class="fs-4"><?= (int)$resumoNps['total'] ?></div>
                </div>
                <div class="col-4">
                    <div class="text-muted small">Nota média (1-5)</div>
                    <div class="fs-4"><?= $resumoNps['media'] !== null ? htmlspecialchars((string)$resumoNps['media']) : '—' ?></div>
                </div>
                <div class="col-4">
                    <div class="text-muted small">% resolvido</div>
                    <div class="fs-4"><?= $resumoNps['pct_resolvido'] !== null ? (int)$resumoNps['pct_resolvido'] . '%' : '—' ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'WhatsApp - Estatísticas';

require __DIR__ . '/../layouts/main.php';
