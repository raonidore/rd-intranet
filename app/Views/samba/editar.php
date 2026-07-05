<?php

use App\Components\Avatar;
use App\Components\Badge;

ob_start();
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">Editar usuário Samba</h5>
        <small class="text-muted">Atualize nome, departamento e acesso SSH.</small>
    </div>

    <div class="card-body">
        <div class="d-flex align-items-center gap-3 mb-4">
            <?= Avatar::initials($usuarioSamba['nome']) ?>

            <div>
                <strong><?= htmlspecialchars($usuarioSamba['nome']) ?></strong><br>
                <small class="text-muted"><?= htmlspecialchars($usuarioSamba['login']) ?></small><br>
                <?= Badge::status($usuarioSamba['status']) ?>
            </div>
        </div>

        <form method="post" action="<?= url('/samba/usuarios/editar') ?>">
            <input type="hidden" name="id" value="<?= htmlspecialchars($usuarioSamba['id']) ?>">

            <div class="mb-3">
                <label class="form-label">Nome completo</label>
                <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($usuarioSamba['nome']) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Login</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($usuarioSamba['login']) ?>" disabled>
                <small class="text-muted">O login não será alterado para evitar inconsistências no Linux/Samba.</small>
            </div>

            <div class="mb-3">
                <label class="form-label">Grupo</label>
                <input type="text" name="departamento" class="form-control" list="grupos-existentes"
                       value="<?= htmlspecialchars($usuarioSamba['departamento']) ?>" required>
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
                    <option value="0" <?= (int)$usuarioSamba['ssh'] === 0 ? 'selected' : '' ?>>Não</option>
                    <option value="1" <?= (int)$usuarioSamba['ssh'] === 1 ? 'selected' : '' ?>>Sim</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> Salvar alterações
            </button>

            <a href="<?= url('/samba/usuarios') ?>" class="btn btn-secondary">
                Voltar
            </a>
        </form>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Editar usuário Samba';

require __DIR__ . '/../layouts/main.php';
