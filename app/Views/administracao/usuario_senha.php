<?php

use App\Components\Alert;
use App\Services\PasswordPolicyService;

ob_start();
?>

<style>
.senha-ui-requisito { display: flex; align-items: center; gap: 6px; font-size: 12.5px; }
</style>

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
                        <input type="password" name="senha" id="usSenha" class="form-control" required>
                        <div id="usSenhaChecklist" class="mt-2"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirmar senha</label>
                        <input type="password" name="confirmacao" id="usConfirmacao" class="form-control" required>
                        <div id="usSenhaMatch"></div>
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

<script src="<?= url('/assets/js/senha-ui.js') ?>"></script>
<script>
RdSenhaUI.aplicar({
    campoSenha: 'usSenha',
    campoConfirmacao: 'usConfirmacao',
    checklistContainer: 'usSenhaChecklist',
    matchContainer: 'usSenhaMatch',
    politica: <?= json_encode(PasswordPolicyService::politicaParaJs()) ?>,
    dadosObvios: <?= json_encode(['nome' => $usuario['nome'], 'login' => $usuario['login'], 'email' => $usuario['email'] ?? '']) ?>
});
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Redefinir Senha';

require __DIR__ . '/../layouts/main.php';
