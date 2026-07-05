<?php

ob_start();
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong><i class="bi bi-file-earmark-code"></i> <?= htmlspecialchars($nome) ?></strong>
        <a href="<?= url('/apache/sites') ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>
    <div class="card-body">
        <pre class="bg-dark text-light p-3 rounded mb-0" style="white-space:pre-wrap"><?= htmlspecialchars($arquivoConteudo) ?></pre>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Ver Site Apache';

require __DIR__ . '/../layouts/main.php';
