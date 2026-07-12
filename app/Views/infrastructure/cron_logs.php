<?php

use App\Components\Badge;

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-journal-text me-1"></i> Log: <?= htmlspecialchars($job['nome']) ?></h4>
        <small class="text-muted font-monospace"><?= htmlspecialchars($job['expressao']) ?> · <?= htmlspecialchars($job['usuario_execucao']) ?></small>
    </div>
    <a href="<?= url('/infraestrutura/cron') ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <strong>Última execução manual ("Executar agora")</strong>
    </div>
    <div class="card-body">
        <?php if ($job['ultima_execucao_em']): ?>
            <p class="mb-2">
                <?= htmlspecialchars(data_br($job['ultima_execucao_em'])) ?>
                <?= (int)$job['ultima_execucao_sucesso'] === 1 ? Badge::make('OK', 'success') : Badge::make('Falha', 'danger') ?>
            </p>
            <pre class="bg-dark text-light p-3 rounded mb-0"><?= htmlspecialchars($job['ultima_execucao_saida'] ?: '(sem saída)') ?></pre>
        <?php else: ?>
            <p class="text-muted mb-0">Nenhuma execução manual registrada ainda.</p>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <strong>Saída das execuções agendadas (últimas linhas)</strong>
    </div>
    <div class="card-body">
        <?php if ($logAgendado !== ''): ?>
            <pre class="bg-dark text-light p-3 rounded mb-0" style="max-height:500px; overflow:auto"><?= htmlspecialchars($logAgendado) ?></pre>
        <?php else: ?>
            <p class="text-muted mb-0">Ainda não há saída registrada pelo agendamento (o job precisa ter rodado pelo menos uma vez).</p>
        <?php endif; ?>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Log - ' . $job['nome'];

require __DIR__ . '/../layouts/main.php';
