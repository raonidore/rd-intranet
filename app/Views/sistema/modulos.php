<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-grid-3x3-gap-fill me-1"></i> Módulos</h4>
    <small class="text-muted">
        Liga/desliga um grupo inteiro do menu pra esta instalação -- vale pra todo mundo, inclusive
        administradores. Útil pra não deixar módulos que este cliente não usa ocupando espaço no menu.
        Não afeta permissão individual por usuário (isso continua em Usuários do Sistema).
    </small>
</div>

<form method="post" action="<?= url('/administracao/modulos/salvar') ?>">
    <div class="row g-3">
        <?php foreach ($gruposTogleaveis as $grupo): ?>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="grupos[]" value="<?= htmlspecialchars($grupo) ?>"
                                   id="grupo-<?= htmlspecialchars($grupo) ?>"
                                   <?= in_array($grupo, $gruposHabilitados, true) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="grupo-<?= htmlspecialchars($grupo) ?>">
                                <?= htmlspecialchars($grupo) ?>
                            </label>
                        </div>
                        <div class="text-muted small mt-2">
                            <?= htmlspecialchars(implode(', ', $grupos[$grupo] ?? [])) ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-check-lg"></i> Salvar</button>
</form>

<?php
$conteudo = ob_get_clean();
$titulo = 'Módulos';

require __DIR__ . '/../layouts/main.php';
