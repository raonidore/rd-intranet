<?php
ob_start();
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0">
                <i class="bi bi-journal-text"></i> Logs do serviço
            </h5>
            <small class="text-muted"><?= htmlspecialchars($servico) ?></small>
        </div>

        <a href="<?= url('/infraestrutura/servicos') ?>" class="btn btn-secondary btn-sm">
            Voltar
        </a>
    </div>

    <div class="card-body">
        <pre class="bg-dark text-light p-3 rounded" style="max-height:600px; overflow:auto;"><?= htmlspecialchars($logs['output'] ?? '') ?></pre>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Logs do serviço';

require __DIR__ . '/../layouts/main.php';
