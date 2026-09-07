<?php

use App\Components\Alert;

ob_start();
?>

<?= Alert::flash() ?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-diagram-2"></i> Nova OU</h5>
                <small class="text-muted">Criada na raiz do domínio (sem aninhamento nesta versão).</small>
            </div>

            <div class="card-body">
                <form method="post" action="<?= url('/samba/dominio/ous/novo') ?>">
                    <div class="mb-3">
                        <label class="form-label">Nome da OU</label>
                        <input type="text" name="nome" class="form-control" required placeholder="ex: Financeiro">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Criar OU
                    </button>
                    <a href="<?= url('/samba/dominio/ous') ?>" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Samba - Domínio - Nova OU';
require __DIR__ . '/../../layouts/main.php';
