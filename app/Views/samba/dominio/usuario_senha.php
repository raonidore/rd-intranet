<?php

use App\Components\Alert;

ob_start();
?>

<?= Alert::flash() ?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-key"></i> Resetar senha</h5>
                <small class="text-muted">Usuário: <code><?= htmlspecialchars($username) ?></code></small>
            </div>

            <div class="card-body">
                <form method="post" action="<?= url('/samba/dominio/usuarios/senha') ?>">
                    <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">

                    <div class="mb-3">
                        <label class="form-label">Nova senha</label>
                        <input type="password" name="senha" class="form-control" required minlength="8">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirmar senha</label>
                        <input type="password" name="confirmacao" class="form-control" required minlength="8">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Redefinir senha
                    </button>
                    <a href="<?= url('/samba/dominio/usuarios') ?>" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Samba - Domínio - Resetar Senha';
require __DIR__ . '/../../layouts/main.php';
