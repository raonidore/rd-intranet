<?php

use App\Components\Alert;

ob_start();
?>

<?= Alert::flash() ?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-diagram-3"></i> Novo Mapa de Rede</h5>
            </div>
            <div class="card-body">
                <form method="post" action="<?= url('/infraestrutura/rede/mapa/novo') ?>">
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" name="nome" class="form-control" required placeholder="ex: Matriz, Data Center, Cliente X">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição (opcional)</label>
                        <input type="text" name="descricao" class="form-control">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Criar mapa
                    </button>
                    <a href="<?= url('/infraestrutura/rede/mapa') ?>" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - Novo Mapa de Rede';
require __DIR__ . '/../layouts/main.php';
