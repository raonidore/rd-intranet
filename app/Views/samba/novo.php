<?php

use App\Components\Alert;

ob_start();
?>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">Novo usuário Samba</h5>
        <small class="text-muted">Cria o usuário no Linux, Samba e no cadastro da RD Intranet.</small>
    </div>

    <div class="card-body">
        <form method="post" action="<?= url('/samba/usuarios/novo') ?>">
            <div class="mb-3">
                <label class="form-label">Nome completo</label>
                <input type="text" name="nome" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Login</label>
                <input type="text" name="login" class="form-control" required placeholder="ex: luisaoliveira">
            </div>

            <div class="mb-3">
                <label class="form-label">Grupo</label>
                <input type="text" name="grupo" class="form-control" list="grupos-existentes" required>
                <datalist id="grupos-existentes">
                    <?php foreach ($grupos as $grupo): ?>
                        <option value="<?= htmlspecialchars($grupo) ?>">
                    <?php endforeach; ?>
                </datalist>
                <small class="text-muted">Grupo Linux do usuário. Escolha um já existente ou digite um novo (é criado automaticamente).</small>
            </div>

            <div class="mb-3">
                <label class="form-label">Acesso SSH</label>
                <select name="ssh" class="form-select" required>
                    <option value="nao">Não</option>
                    <option value="sim">Sim</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Senha inicial</label>
                <input type="password" name="senha" class="form-control" required minlength="8">
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Criar usuário
            </button>

            <a href="<?= url('/samba/usuarios') ?>" class="btn btn-secondary">
                Voltar
            </a>
        </form>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Novo Usuário Samba';

require __DIR__ . '/../layouts/main.php';
