<?php

use App\Components\Avatar;

ob_start();
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">Alterar senha Samba</h5>
        <small class="text-muted">Defina uma nova senha de acesso ao compartilhamento.</small>
    </div>

    <div class="card-body">
        <div class="d-flex align-items-center gap-3 mb-4">
            <?= Avatar::initials($usuarioSamba['nome']) ?>

            <div>
                <strong><?= htmlspecialchars($usuarioSamba['nome']) ?></strong><br>
                <small class="text-muted"><?= htmlspecialchars($usuarioSamba['login']) ?></small>
            </div>
        </div>

        <form method="post" action="<?= url('/samba/usuarios/senha') ?>">
            <input type="hidden" name="id" value="<?= htmlspecialchars($usuarioSamba['id']) ?>">

            <div class="mb-3">
                <label class="form-label">Nova senha</label>
                <input type="password" name="senha" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Confirmar senha</label>
                <input type="password" name="confirmacao" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-key"></i> Alterar senha
            </button>

            <a href="<?= url('/samba/usuarios') ?>" class="btn btn-secondary">
                Voltar
            </a>
        </form>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Alterar senha Samba';

require __DIR__ . '/../layouts/main.php';
