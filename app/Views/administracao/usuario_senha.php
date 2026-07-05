<?php

use App\Components\Alert;

ob_start();
?>

<?= Alert::flash() ?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-key"></i> Redefinir senha</h5>
                <small class="text-muted"><?= htmlspecialchars($usuario['nome']) ?> (<?= htmlspecialchars($usuario['login']) ?>)</small>
            </div>

            <div class="card-body">
                <form method="post" action="<?= url('/administracao/usuarios/senha') ?>">
                    <input type="hidden" name="id" value="<?= (int)$usuario['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label">Nova senha</label>
                        <input type="password" name="senha" class="form-control" minlength="8" required>
                        <small class="text-muted">Mínimo de 8 caracteres.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirmar senha</label>
                        <input type="password" name="confirmacao" class="form-control" minlength="8" required>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="<?= url('/administracao/usuarios') ?>" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Salvar nova senha
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Redefinir Senha';

require __DIR__ . '/../layouts/main.php';
