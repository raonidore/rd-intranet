<?php
ob_start();

use App\Components\Alert;

function chamadoDuracao(?int $segundos): string
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

<style>
#chamadosPainelTempoReal:fullscreen { background:#f4f6f9; padding:2.5rem; overflow-y:auto; }
</style>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-bar-chart-line me-1"></i> Chamados - Estatísticas</h4>
    <small class="text-muted">Volume de chamados, tempos e cumprimento de SLA por período e por atendente.</small>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $aba === 'tempo-real' ? 'active' : '' ?>" href="<?= url('/chamados/estatisticas?aba=tempo-real') ?>">Em tempo real</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $aba === 'geral' ? 'active' : '' ?>" href="<?= url('/chamados/estatisticas?aba=geral') ?>">Geral</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $aba === 'ranking' ? 'active' : '' ?>" href="<?= url('/chamados/estatisticas?aba=ranking') ?>">Ranking</a>
    </li>
</ul>

<?php if ($aba === 'tempo-real'): ?>

    <div class="d-flex justify-content-end mb-2">
        <button type="button" id="chamadosBtnTelaCheia" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrows-fullscreen"></i> Tela cheia
        </button>
    </div>

    <div id="chamadosPainelTempoReal">
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100"><div class="card-body text-center">
                    <div class="text-muted small">Na fila</div>
                    <div class="fs-2" id="chamadosFila"><?= (int)$tempoReal['fila'] ?></div>
                </div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100"><div class="card-body text-center">
                    <div class="text-muted small">Em atendimento</div>
                    <div class="fs-2" id="chamadosEmAtendimento"><?= (int)$tempoReal['em_atendimento'] ?></div>
                </div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100"><div class="card-body text-center">
                    <div class="text-muted small">SLA em risco (&lt; 1h)</div>
                    <div class="fs-2 text-warning" id="chamadosSlaRisco"><?= (int)$tempoReal['sla_em_risco'] ?></div>
                </div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100"><div class="card-body text-center">
                    <div class="text-muted small">SLA estourado</div>
                    <div class="fs-2 text-danger" id="chamadosSlaEstourado"><?= (int)$tempoReal['sla_estourado'] ?></div>
                </div></div>
            </div>
        </div>

        <div class="card border-0 shadow-sm" id="chamadosCardFilaPorSetor" style="max-width:600px; <?= empty($tempoReal['fila_por_setor']) ? 'display:none' : '' ?>">
            <div class="card-body">
                <h6 class="mb-2">Fila por setor</h6>
                <div id="chamadosFilaPorSetorLista">
                    <?php foreach ($tempoReal['fila_por_setor'] as $item): ?>
                        <div class="d-flex justify-content-between border-bottom py-1">
                            <span><?= htmlspecialchars($item['setor_nome']) ?></span>
                            <strong><?= (int)$item['total'] ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        const painel = document.getElementById('chamadosPainelTempoReal');
        const btn = document.getElementById('chamadosBtnTelaCheia');
        let timer = null;

        function estaEmTelaCheia() { return document.fullscreenElement === painel; }

        function atualizarBotao() {
            btn.innerHTML = estaEmTelaCheia()
                ? '<i class="bi bi-fullscreen-exit"></i> Sair da tela cheia'
                : '<i class="bi bi-arrows-fullscreen"></i> Tela cheia';
        }

        function escapeHtml(texto) {
            const div = document.createElement('div');
            div.textContent = texto;
            return div.innerHTML;
        }

        async function atualizarPainel() {
            try {
                const resp = await fetch('<?= url('/chamados/estatisticas/tempo-real-api') ?>');
                const dados = await resp.json();
                if (!dados.success) return;

                const t = dados.tempoReal;
                document.getElementById('chamadosFila').textContent = t.fila;
                document.getElementById('chamadosEmAtendimento').textContent = t.em_atendimento;
                document.getElementById('chamadosSlaRisco').textContent = t.sla_em_risco;
                document.getElementById('chamadosSlaEstourado').textContent = t.sla_estourado;

                const card = document.getElementById('chamadosCardFilaPorSetor');
                const lista = document.getElementById('chamadosFilaPorSetorLista');
                if (t.fila_por_setor.length === 0) {
                    card.style.display = 'none';
                } else {
                    card.style.display = '';
                    lista.innerHTML = t.fila_por_setor.map((item) =>
                        '<div class="d-flex justify-content-between border-bottom py-1"><span>' + escapeHtml(item.setor_nome) + '</span><strong>' + item.total + '</strong></div>'
                    ).join('');
                }
            } catch (e) { /* rede instável -- tenta de novo no próximo ciclo */ }
        }

        btn.addEventListener('click', () => {
            if (estaEmTelaCheia()) document.exitFullscreen();
            else painel.requestFullscreen();
        });

        document.addEventListener('fullscreenchange', () => {
            atualizarBotao();
            if (estaEmTelaCheia()) {
                atualizarPainel();
                timer = setInterval(atualizarPainel, 15000);
            } else if (timer) {
                clearInterval(timer);
                timer = null;
            }
        });
    })();
    </script>

<?php elseif ($aba === 'geral'): ?>

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div class="btn-group btn-group-sm">
            <?php foreach ($rotulosPeriodoGeral as $valor => $rotulo): ?>
                <a href="<?= url('/chamados/estatisticas?aba=geral&periodo_geral=' . $valor) ?>"
                   class="btn <?= $periodoGeral === $valor ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $rotulo ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body text-center">
                <div class="text-muted small">Chamados resolvidos</div>
                <div class="fs-2"><?= (int)$geral['total_chamados'] ?></div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body text-center">
                <div class="text-muted small">Tempo médio de espera</div>
                <div class="fs-4 font-monospace"><?= chamadoDuracao($geral['tempo_medio_espera']) ?></div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body text-center">
                <div class="text-muted small">Tempo total de atendimento</div>
                <div class="fs-4 font-monospace"><?= chamadoDuracao($geral['tempo_total']) ?></div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body text-center">
                <div class="text-muted small">SLA cumprido</div>
                <div class="fs-2"><?= $geral['pct_sla_cumprido'] !== null ? $geral['pct_sla_cumprido'] . '%' : '—' ?></div>
            </div></div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body text-center">
                <div class="text-muted small">Satisfação (avaliações -- 90d)</div>
                <div class="fs-2"><?= $avaliacoes['media'] !== null ? $avaliacoes['media'] . ' <small class="fs-6 text-muted">/5</small>' : '—' ?></div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body text-center">
                <div class="text-muted small">Índice de satisfação</div>
                <div class="fs-2"><?= $avaliacoes['indice_satisfacao'] !== null ? $avaliacoes['indice_satisfacao'] . '%' : '—' ?></div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body text-center">
                <div class="text-muted small">Resolvido de primeira</div>
                <div class="fs-2"><?= $avaliacoes['pct_resolvido'] !== null ? $avaliacoes['pct_resolvido'] . '%' : '—' ?></div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body text-center">
                <div class="text-muted small">Avaliações recebidas</div>
                <div class="fs-2"><?= (int)$avaliacoes['total'] ?></div>
            </div></div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($geral['linhas'])): ?>
                <p class="text-muted text-center py-4 mb-0">Nenhum chamado resolvido nesse período ainda.</p>
            <?php else: ?>
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Período</th><th>Setor</th><th>Total</th><th>Tempo total</th><th>Espera média</th></tr></thead>
                    <tbody>
                        <?php foreach ($geral['linhas'] as $linha): ?>
                            <tr>
                                <td><?= htmlspecialchars($linha['periodo_label']) ?></td>
                                <td><?= htmlspecialchars($linha['setor_nome']) ?></td>
                                <td><?= (int)$linha['total_chamados'] ?></td>
                                <td class="font-monospace"><?= chamadoDuracao((int)$linha['tempo_total_atendimento']) ?></td>
                                <td class="font-monospace"><?= chamadoDuracao($linha['tempo_medio_espera'] !== null ? (int)$linha['tempo_medio_espera'] : null) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($aba === 'ranking'): ?>

    <div class="btn-group btn-group-sm mb-3">
        <?php foreach ($rotulosPeriodoRanking as $valor => $rotulo): ?>
            <a href="<?= url('/chamados/estatisticas?aba=ranking&periodo_ranking=' . $valor) ?>"
               class="btn <?= $periodoRanking === $valor ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $rotulo ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($ranking)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted py-4">
                <i class="bi bi-trophy" style="font-size:2rem;"></i>
                <p class="mb-0 mt-2">Nenhum chamado resolvido nesse período ainda.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Atendente</th><th>Setor</th><th>Chamados</th><th>Tempo médio</th></tr></thead>
                    <tbody>
                        <?php foreach ($ranking as $linha): ?>
                            <tr>
                                <td><?= htmlspecialchars($linha['usuario_nome']) ?></td>
                                <td><?= htmlspecialchars($linha['setor_nome'] ?? '—') ?></td>
                                <td><?= (int)$linha['total_chamados'] ?></td>
                                <td class="font-monospace"><?= chamadoDuracao($linha['tempo_medio']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Chamados - Estatísticas';

require __DIR__ . '/../layouts/main.php';
