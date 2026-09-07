<?php

use App\Components\Alert;

ob_start();
?>

<?= Alert::flash() ?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-calendar-event"></i> Expiração de senha</h5>
                <small class="text-muted">Usuário: <code><?= htmlspecialchars($username) ?></code></small>
            </div>

            <div class="card-body">
                <form method="post" action="<?= url('/samba/dominio/usuarios/expiracao') ?>" id="form-expiracao">
                    <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">

                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="chk-nunca" name="nunca" value="1" onchange="document.getElementById('bloco-dias').style.display = this.checked ? 'none' : ''">
                        <label class="form-check-label" for="chk-nunca">Senha nunca expira</label>
                    </div>

                    <div class="mb-3" id="bloco-dias">
                        <label class="form-label">Expira em quantos dias a partir de hoje</label>
                        <input type="number" name="dias" class="form-control" min="1" value="90">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Salvar
                    </button>
                    <a href="<?= url('/samba/dominio/usuarios') ?>" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Samba - Domínio - Expiração de Senha';
require __DIR__ . '/../../layouts/main.php';
