<?php

use App\Components\Alert;
use App\Components\Badge;

ob_start();

$falhou = empty($detalhes['success']) && isset($detalhes['success']);
?>

<div class="mb-4">
    <a href="<?= url('/samba/dominio/usuarios') ?>" class="text-decoration-none small text-muted d-block mb-1">
        <i class="bi bi-arrow-left"></i> Usuários do Domínio
    </a>
    <h5 class="mb-0"><i class="bi bi-person"></i> <code><?= htmlspecialchars($username) ?></code></h5>
</div>

<?= Alert::flash() ?>

<?php if ($falhou): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($detalhes['message'] ?? 'Falha ao consultar usuário.') ?></div>
<?php else: ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="text-muted small">Status</div>
                    <div><?= !empty($detalhes['habilitado']) ? Badge::make('Ativo', 'success') : Badge::make('Desativado', 'secondary') ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Bloqueio</div>
                    <div><?= !empty($detalhes['bloqueado']) ? Badge::make('Bloqueado', 'danger') : Badge::make('Sem bloqueio', 'success') ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Senha definida em</div>
                    <div class="font-monospace"><?= htmlspecialchars($detalhes['senha_definida_em'] ?? '-') ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">Grupos</div>
        <div class="card-body">
            <?php if (empty($detalhes['grupos'])): ?>
                <span class="text-muted">Nenhum grupo.</span>
            <?php else: ?>
                <?php foreach ($detalhes['grupos'] as $g): ?>
                    <span class="badge text-bg-light border me-1"><?= htmlspecialchars($g) ?></span>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Samba - Domínio - Usuário';
require __DIR__ . '/../../layouts/main.php';
