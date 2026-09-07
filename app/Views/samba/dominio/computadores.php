<?php

use App\Components\Alert;

ob_start();
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h5 class="mb-1"><i class="bi bi-pc-display"></i> Computadores do Domínio</h5>
        <small class="text-muted">Máquinas ingressadas no Active Directory (somente leitura nesta versão).</small>
    </div>
</div>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr><th>Nome</th></tr>
            </thead>
            <tbody>
                <?php if (empty($computadores)): ?>
                    <tr><td class="text-center text-muted py-4">Nenhum computador ingressado.</td></tr>
                <?php endif; ?>
                <?php foreach ($computadores as $c): ?>
                    <tr><td class="font-monospace"><?= htmlspecialchars($c) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Samba - Domínio - Computadores';
require __DIR__ . '/../../layouts/main.php';
