<?php

ob_start();

$banco = '';
$tabela = '';
?>

<div class="db-layout">
    <?php require __DIR__ . '/_console_sidebar.php'; ?>

    <div class="db-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1"><i class="bi bi-terminal me-1"></i> Console - <?= htmlspecialchars($conexao['nome']) ?></h4>
                <small class="text-muted"><?= htmlspecialchars($conexao['host']) ?>:<?= (int)$conexao['porta'] ?></small>
            </div>
            <div>
                <a href="<?= url('/banco-dados/console/sql?conexao=' . $conexao['id']) ?>" class="btn btn-sm btn-outline-dark">
                    <i class="bi bi-code-slash"></i> Console SQL
                </a>
                <a href="<?= url('/banco-dados/conexoes') ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Conexões
                </a>
            </div>
        </div>

        <?php if ($erro): ?>
            <div class="alert alert-danger"><i class="bi bi-x-circle"></i> Não foi possível listar os bancos: <?= htmlspecialchars($erro) ?></div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($bancos as $b): ?>
                    <div class="col-md-4">
                        <a href="<?= url('/banco-dados/console/tabelas?conexao=' . $conexao['id'] . '&banco=' . urlencode($b)) ?>"
                           class="card border-0 shadow-sm text-decoration-none h-100">
                            <div class="card-body">
                                <i class="bi bi-database text-primary"></i>
                                <div class="mt-1 text-dark"><?= htmlspecialchars($b) ?></div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($bancos)): ?>
                    <div class="col-12 text-muted">Nenhum banco encontrado.</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Console - ' . $conexao['nome'];

require __DIR__ . '/../layouts/main.php';
