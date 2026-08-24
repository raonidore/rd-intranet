<?php
ob_start();

$maxFornecedor = max([1, ...array_column($porFornecedor, 'total')]);
$maxCategoria = max([1, ...array_column($porCategoria, 'total')]);
$maxMes = max([1, ...array_column($porMes, 'total')]);
?>

<div class="mb-4">
    <a href="<?= url('/chamados-externos') ?>" class="text-decoration-none small text-muted d-block mb-1">
        <i class="bi bi-arrow-left"></i> Chamados Externos
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
                <div class="fs-4 fw-bold text-primary"><?= (int)$resumo['aberto'] ?></div>
                <div class="text-muted small">Abertos</div>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-warning"><?= (int)$resumo['aguardando_fornecedor'] ?></div>
                <div class="text-muted small">Aguard. fornecedor</div>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-info"><?= (int)$resumo['em_andamento'] ?></div>
                <div class="text-muted small">Em andamento</div>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-success"><?= (int)$resumo['resolvido'] ?></div>
                <div class="text-muted small">Resolvidos</div>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-secondary"><?= (int)$resumo['fechado'] ?></div>
                <div class="text-muted small">Fechados</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="card-title mb-3">Chamados por fornecedor</h6>
                <?php if (empty($porFornecedor)): ?>
                    <p class="text-muted small mb-0">Sem dados ainda.</p>
                <?php else: foreach ($porFornecedor as $linha): ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><?= htmlspecialchars($linha['fornecedor_nome']) ?></span>
                            <strong><?= (int)$linha['total'] ?></strong>
                        </div>
                        <div class="progress" style="height:8px">
                            <div class="progress-bar" style="width: <?= round($linha['total'] / $maxFornecedor * 100) ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="card-title mb-3">Chamados por categoria</h6>
                <?php if (empty($porCategoria)): ?>
                    <p class="text-muted small mb-0">Sem dados ainda.</p>
                <?php else: foreach ($porCategoria as $linha): ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><?= htmlspecialchars($linha['categoria_nome']) ?></span>
                            <strong><?= (int)$linha['total'] ?></strong>
                        </div>
                        <div class="progress" style="height:8px">
                            <div class="progress-bar bg-info" style="width: <?= round($linha['total'] / $maxCategoria * 100) ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="card-title mb-3">Últimos 6 meses</h6>
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

<?php
$conteudo = ob_get_clean();
$titulo = 'Estatísticas de Chamados Externos';

require __DIR__ . '/../layouts/main.php';
