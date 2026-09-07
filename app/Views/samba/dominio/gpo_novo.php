<?php

use App\Components\Alert;

ob_start();
?>

<?= Alert::flash() ?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-journal-check"></i> Nova GPO</h5>
                <small class="text-muted">Cria o objeto/esqueleto -- o conteúdo da política é editado pelo GPMC a partir de uma estação Windows.</small>
            </div>

            <div class="card-body">
                <form method="post" action="<?= url('/samba/dominio/gpos/novo') ?>">
                    <div class="mb-3">
                        <label class="form-label">Nome da GPO</label>
                        <input type="text" name="nome" class="form-control" required placeholder="ex: Bloqueio de Tela">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Criar GPO
                    </button>
                    <a href="<?= url('/samba/dominio/gpos') ?>" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Samba - Domínio - Nova GPO';
require __DIR__ . '/../../layouts/main.php';
