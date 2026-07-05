<?php

use App\Components\Alert;

ob_start();

$totalCompartilhamentos = count($grupo['compartilhamentos']);
$totalUsuarios = count($grupo['usuarios']);
?>

<?= Alert::flash() ?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Renomear grupo</h5>
                <small class="text-muted">Grupo atual: <code><?= htmlspecialchars($grupo['nome']) ?></code></small>
            </div>

            <div class="card-body">
                <div class="alert alert-warning">
                    <strong>Atenção!</strong> Esta operação vai:
                    <ul class="mt-2 mb-0">
                        <li>Renomear o grupo Linux de verdade (<code>groupmod -n</code>).</li>
                        <li>Atualizar <?= $totalCompartilhamentos ?> compartilhamento(s) e <?= $totalUsuarios ?> usuário(s) que usam esse grupo.</li>
                        <li>Reaplicar o <code>smb.conf</code> na hora (não fica pendente — o compartilhamento ficaria sem ninguém conseguindo acessar até o próximo deploy).</li>
                    </ul>
                </div>

                <form method="post" action="<?= url('/samba/grupos/renomear') ?>">
                    <input type="hidden" name="antigo" value="<?= htmlspecialchars($grupo['nome']) ?>">

                    <div class="mb-3">
                        <label class="form-label">Novo nome do grupo</label>
                        <input type="text" name="novo" class="form-control" required
                               pattern="[a-z][a-z0-9_-]*" placeholder="ex: rh">
                        <small class="text-muted">Letras minúsculas, números, "_" e "-", começando com letra.</small>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Renomear
                    </button>
                    <a href="<?= url('/samba/grupos') ?>" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Renomear Grupo';

require __DIR__ . '/../layouts/main.php';
