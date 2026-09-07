<?php

use App\Components\Alert;

ob_start();
?>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">Novo usuário do domínio</h5>
        <small class="text-muted">Cria a conta no Active Directory via <code>samba-tool user create</code>.</small>
    </div>

    <div class="card-body">
        <form method="post" action="<?= url('/samba/dominio/usuarios/novo') ?>">
            <div class="mb-3">
                <label class="form-label">Nome de usuário</label>
                <input type="text" name="username" class="form-control" required pattern="[a-zA-Z][a-zA-Z0-9._-]{0,19}" placeholder="ex: jsilva">
            </div>

            <div class="mb-3">
                <label class="form-label">Nome completo</label>
                <input type="text" name="nome_completo" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Senha inicial</label>
                <input type="password" name="senha" class="form-control" required minlength="8">
            </div>

            <div class="mb-3">
                <label class="form-label">Confirmar senha</label>
                <input type="password" name="confirmacao" class="form-control" required minlength="8">
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Criar usuário
            </button>
            <a href="<?= url('/samba/dominio/usuarios') ?>" class="btn btn-secondary">Voltar</a>
        </form>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Samba - Domínio - Novo Usuário';
require __DIR__ . '/../../layouts/main.php';
