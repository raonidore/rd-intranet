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
                    <h5 class="mb-1"><?= htmlspecialchars($job['nome']) ?></h5>
                    <small class="text-muted font-monospace"><?= htmlspecialchars($job['expressao']) ?> · <?= htmlspecialchars($job['usuario_execucao']) ?> · <?= htmlspecialchars($job['comando']) ?></small>
                </div>

                <div class="alert alert-warning">
                    <strong>Atenção!</strong> Esta operação remove o job permanentemente e regenera
                    o cron do sistema sem ele.
                </div>

                <form method="post" action="<?= url('/infraestrutura/cron/excluir') ?>">
                    <input type="hidden" name="id" value="<?= (int)$job['id'] ?>">

                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Sim, excluir job
                    </button>
                    <a href="<?= url('/infraestrutura/cron') ?>" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Excluir Job de Cron';

require __DIR__ . '/../layouts/main.php';
