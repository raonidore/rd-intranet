<?php

use App\Components\Avatar;

ob_start();
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-danger text-white">
                <h4 class="mb-0"><i class="bi bi-trash"></i> Confirmar exclusão</h4>
            </div>

            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <?= Avatar::initials($usuario['nome']) ?>
                    <div>
                        <h5 class="mb-1"><?= htmlspecialchars($usuario['nome']) ?></h5>
                        <small class="text-muted"><?= htmlspecialchars($usuario['login']) ?></small>
                    </div>
                </div>

                <div class="alert alert-warning">
                    <strong>Atenção!</strong> Esta operação irá remover permanentemente o cadastro
                    deste usuário e o acesso dele ao sistema.
                </div>

                <form method="post" action="<?= url('/administracao/usuarios/excluir') ?>">
                    <input type="hidden" name="id" value="<?= (int)$usuario['id'] ?>">

                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Sim, excluir usuário
                    </button>
                    <a href="<?= url('/administracao/usuarios') ?>" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Excluir Usuário';

require __DIR__ . '/../layouts/main.php';
