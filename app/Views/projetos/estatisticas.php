<?php
ob_start();

$maxArea = max([1, ...array_column($porArea, 'total')]);
$maxMes = max([1, ...array_column($porMes, 'total')]);

$colunaLabels = [
    'a_fazer' => 'A fazer',
    'em_andamento' => 'Em andamento',
    'aguardando_terceiro' => 'Aguardando terceiro',
    'concluido' => 'Concluído',
];
?>

<div class="mb-4">
    <a href="<?= url('/projetos') ?>" class="text-decoration-none small text-muted d-block mb-1">
        <i class="bi bi-arrow-left"></i> Projetos
    </a>
    <h4 class="mb-1"><i class="bi bi-bar-chart-line me-1"></i> Estatísticas</h4>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-2 col-6">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold"><?= (int)$resumo['total'] ?></div>
                <div class="text-muted small">Total</div>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-primary"><?= (int)$resumo['em_andamento'] ?></div>
                <div class="text-muted small">Em andamento</div>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold"><?= (int)$resumo['planejamento'] ?></div>
                <div class="text-muted small">Planejamento</div>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-warning"><?= (int)$resumo['pausado'] ?></div>
                <div class="text-muted small">Pausados</div>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-success"><?= (int)$resumo['concluido'] ?></div>
                <div class="text-muted small">Concluídos</div>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-secondary"><?= (int)$resumo['cancelado'] ?></div>
                <div class="text-muted small">Cancelados</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="card-title mb-3">Projetos por área</h6>
                <?php if (empty($porArea)): ?>
                    <p class="text-muted small mb-0">Sem dados ainda.</p>
                <?php else: foreach ($porArea as $linha): ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><?= htmlspecialchars($linha['area_nome']) ?></span>
                            <strong><?= (int)$linha['total'] ?></strong>
                        </div>
                        <div class="progress" style="height:8px">
                            <div class="progress-bar" style="width: <?= round($linha['total'] / $maxArea * 100) ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="card-title mb-3">Tarefas por coluna do Kanban</h6>
                <canvas id="graficoColunas" height="180"></canvas>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="card-title mb-3">Projetos abertos -- últimos 6 meses</h6>
                <div class="d-flex align-items-end gap-2" style="height: 140px;">
                    <?php foreach ($porMes as $linha): ?>
                        <div class="flex-fill text-center d-flex flex-column justify-content-end h-100">
                            <div class="small mb-1"><?= (int)$linha['total'] ?></div>
                            <div class="bg-primary mx-auto" style="width: 60%; height: <?= max(4, round($linha['total'] / $maxMes * 100)) ?>%; border-radius: 4px 4px 0 0;"></div>
                            <div class="small text-muted mt-1"><?= date('M/y', strtotime($linha['mes'] . '-01')) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('graficoColunas'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_map(fn ($c) => $colunaLabels[$c['coluna']] ?? $c['coluna'], $tarefasPorColuna)) ?>,
        datasets: [{
            data: <?= json_encode(array_map(fn ($c) => (int)$c['total'], $tarefasPorColuna)) ?>,
            backgroundColor: ['#8b949e', '#3987e5', '#c98500', '#199e70'],
        }],
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } },
});
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Estatísticas de Projetos';

require __DIR__ . '/../layouts/main.php';
