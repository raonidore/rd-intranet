<?php

ob_start();
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-danger text-white">
                <h4 class="mb-0"><i class="bi bi-trash"></i> Confirmar exclusão</h4>
            </div>

            <div class="card-body">
                <div class="mb-4">
                    <h5 class="mb-1"><?= htmlspecialchars($segredo['nome']) ?></h5>
                    <small class="text-muted"><?= htmlspecialchars($segredo['categoria']) ?></small>
                </div>

                <div class="alert alert-warning">
                    <strong>Atenção!</strong> Esta operação irá remover permanentemente este segredo
                    e a senha encriptada associada a ele.
                </div>

                <form method="post" action="<?= url('/seguranca/cofre-senhas/excluir') ?>">
                    <input type="hidden" name="id" value="<?= (int)$segredo['id'] ?>">

                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Sim, excluir segredo
                    </button>
                    <a href="<?= url('/seguranca/cofre-senhas') ?>" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Excluir Segredo';

require __DIR__ . '/../layouts/main.php';
