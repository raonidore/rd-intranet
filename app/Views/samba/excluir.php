<?php

use App\Components\Avatar;
use App\Components\Badge;

ob_start();
?>

<div class="row justify-content-center">
    <div class="col-lg-7">

        <div class="card border-0 shadow-sm">

            <div class="card-header bg-danger text-white">
                <h4 class="mb-0">
                    <i class="bi bi-trash"></i>
                    Confirmar exclusão
                </h4>
            </div>

            <div class="card-body">

                <div class="d-flex align-items-center gap-3 mb-4">

                    <?= Avatar::initials($usuarioSamba['nome']) ?>

                    <div>
                        <h5 class="mb-1">
                            <?= htmlspecialchars($usuarioSamba['nome']) ?>
                        </h5>

                        <small class="text-muted">
                            <?= htmlspecialchars($usuarioSamba['login']) ?>
                        </small>

                        <br>

                        <?= Badge::status($usuarioSamba['status']) ?>

                    </div>

                </div>

                <div class="alert alert-warning">

                    <strong>Atenção!</strong>

                    <br><br>

                    Esta operação irá:

                    <ul class="mt-2 mb-0">
                        <li>Excluir o usuário Linux.</li>
                        <li>Excluir o usuário Samba.</li>
                        <li>Remover o cadastro da RD Intranet.</li>
                        <li>Registrar a operação na Auditoria.</li>
                    </ul>

                </div>

                <form method="post"
                      action="<?= url('/samba/usuarios/excluir') ?>">

                    <input
                        type="hidden"
                        name="id"
                        value="<?= htmlspecialchars($usuarioSamba['id']) ?>">

                    <button
                        type="submit"
                        class="btn btn-danger">

                        <i class="bi bi-trash"></i>

                        Sim, excluir usuário

                    </button>

                    <a
                        href="<?= url('/samba/usuarios') ?>"
                        class="btn btn-secondary">

                        Cancelar

                    </a>

                </form>

            </div>

        </div>

    </div>
</div>

<?php

$conteudo = ob_get_clean();

$titulo = 'Excluir usuário Samba';

require __DIR__.'/../layouts/main.php';
