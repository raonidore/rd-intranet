<?php

use App\Components\Alert;

ob_start();
?>

<?= Alert::flash() ?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-collection"></i> Novo grupo do domínio</h5>
            </div>

            <div class="card-body">
                <form method="post" action="<?= url('/samba/dominio/grupos/novo') ?>">
                    <div class="mb-3">
                        <label class="form-label">Nome do grupo</label>
                        <input type="text" name="nome" class="form-control" required pattern="[a-zA-Z][a-zA-Z0-9._-]{0,63}" placeholder="ex: Financeiro">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Criar grupo
                    </button>
                    <a href="<?= url('/samba/dominio/grupos') ?>" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Samba - Domínio - Novo Grupo';
require __DIR__ . '/../../layouts/main.php';
