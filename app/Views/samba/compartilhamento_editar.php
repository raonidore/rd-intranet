<?php ob_start(); ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-pencil"></i> Editar Compartilhamento</h5>
        <small class="text-muted"><?= htmlspecialchars($compartilhamento['nome']) ?></small>
    </div>

    <div class="card-body">
        <form method="post" action="<?= url('/samba/compartilhamentos/editar') ?>">
            <input type="hidden" name="id" value="<?= htmlspecialchars($compartilhamento['id']) ?>">

            <div class="mb-3">
                <label class="form-label">Nome</label>
                <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($compartilhamento['nome']) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Descrição</label>
                <input type="text" name="descricao" class="form-control" value="<?= htmlspecialchars($compartilhamento['descricao']) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Grupo Linux</label>
                <input type="text" name="grupo" class="form-control" list="grupos-existentes" value="<?= htmlspecialchars($compartilhamento['grupo']) ?>" required>
                <datalist id="grupos-existentes">
                    <?php foreach ($grupos as $grupo): ?>
                        <option value="<?= htmlspecialchars($grupo) ?>">
                    <?php endforeach; ?>
                </datalist>
                <small class="text-muted">Trocar o grupo re-aplica o dono da pasta no sistema (chown) ao salvar.</small>
            </div>

            <div class="mb-4">
                <label class="form-label">Caminho</label>
                <input type="text" name="caminho" class="form-control" value="<?= htmlspecialchars($compartilhamento['caminho']) ?>" required>
            </div>

            <button class="btn btn-primary"><i class="bi bi-save"></i> Salvar</button>
            <a href="<?= url('/samba/compartilhamentos') ?>" class="btn btn-secondary">Voltar</a>
        </form>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Editar Compartilhamento';
require __DIR__ . '/../layouts/main.php';
