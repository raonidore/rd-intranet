<?php

use App\Components\Alert;

ob_start();
?>

<div class="mb-4">
    <a href="<?= url('/samba/dominio/grupos') ?>" class="text-decoration-none small text-muted d-block mb-1">
        <i class="bi bi-arrow-left"></i> Grupos do Domínio
    </a>
    <h5 class="mb-0"><i class="bi bi-people"></i> Membros de <code><?= htmlspecialchars($nome) ?></code></h5>
</div>

<?= Alert::flash() ?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">Membros atuais</div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php if (empty($membros)): ?>
                        <li class="list-group-item text-muted text-center py-4">Nenhum membro.</li>
                    <?php endif; ?>
                    <?php foreach ($membros as $m): ?>
                        <li class="list-group-item font-monospace d-flex justify-content-between align-items-center">
                            <?= htmlspecialchars($m) ?>
                            <a href="<?= url('/samba/dominio/grupos/membros/remover?grupo=' . urlencode($nome) . '&usuario=' . urlencode($m)) ?>"
                               class="btn btn-sm btn-outline-danger" title="Remover"
                               onclick="return confirm('Remover \'<?= htmlspecialchars($m, ENT_QUOTES) ?>\' do grupo?')">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">Adicionar membro</div>
            <div class="card-body">
                <form method="post" action="<?= url('/samba/dominio/grupos/membros') ?>">
                    <input type="hidden" name="grupo" value="<?= htmlspecialchars($nome) ?>">

                    <div class="mb-3">
                        <label class="form-label">Usuário</label>
                        <select name="usuario" class="form-select" required>
                            <option value="" disabled selected>Selecione...</option>
                            <?php foreach ($usuarios as $u): ?>
                                <option value="<?= htmlspecialchars($u['username']) ?>"><?= htmlspecialchars($u['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Adicionar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Samba - Domínio - Membros do Grupo';
require __DIR__ . '/../../layouts/main.php';
