<?php

use App\Components\Alert;

ob_start();
?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-person-circle me-1"></i> Meu Perfil</h4>
    <small class="text-muted">Seus dados de acesso ao RD Intranet.</small>
</div>

<?= Alert::flash() ?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-person"></i> Dados pessoais</h5>
            </div>
            <div class="card-body">
                <form method="post" action="<?= url('/perfil/atualizar') ?>">
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" name="nome" class="form-control" required value="<?= htmlspecialchars($usuario['nome']) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Usuário</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($usuario['login']) ?>" disabled readonly>
                        <small class="text-muted">O usuário de login não pode ser alterado.</small>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-key"></i> Alterar senha</h5>
            </div>
            <div class="card-body">
                <form method="post" action="<?= url('/perfil/senha') ?>">
                    <div class="mb-3">
                        <label class="form-label">Senha atual</label>
                        <input type="password" name="senha_atual" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nova senha</label>
                        <input type="password" name="senha" class="form-control" minlength="8" required>
                        <small class="text-muted">Mínimo de 8 caracteres.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirmar nova senha</label>
                        <input type="password" name="confirmacao" class="form-control" minlength="8" required>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Alterar senha
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Meu Perfil';

require __DIR__ . '/../layouts/main.php';
