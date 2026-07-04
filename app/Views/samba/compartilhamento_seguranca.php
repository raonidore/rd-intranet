<?php ob_start(); ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-shield-lock"></i> Segurança do Compartilhamento</h5>
        <small class="text-muted"><?= htmlspecialchars($compartilhamento['nome']) ?></small>
    </div>

    <div class="card-body">
        <form method="post" action="<?= url('/samba/compartilhamentos/seguranca') ?>">
            <input type="hidden" name="id" value="<?= htmlspecialchars($compartilhamento['id']) ?>">

            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="somente_leitura" <?= (int)$compartilhamento['somente_leitura'] ? 'checked' : '' ?>>
                <label class="form-check-label">Somente leitura</label>
            </div>

            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="lixeira" <?= (int)$compartilhamento['lixeira'] ? 'checked' : '' ?>>
                <label class="form-check-label">Habilitar lixeira</label>
            </div>

            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" name="bloqueio_extensoes" <?= (int)$compartilhamento['bloqueio_extensoes'] ? 'checked' : '' ?>>
                <label class="form-check-label">Bloquear extensões perigosas</label>
            </div>

            <button class="btn btn-primary"><i class="bi bi-save"></i> Salvar Segurança</button>
            <a href="<?= url('/samba/compartilhamentos') ?>" class="btn btn-secondary">Voltar</a>
        </form>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Segurança do Compartilhamento';
require __DIR__ . '/../layouts/main.php';
