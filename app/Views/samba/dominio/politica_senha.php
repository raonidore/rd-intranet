<?php

use App\Components\Alert;

ob_start();

$falhou = empty($politica['success']) && isset($politica['success']);
?>

<div class="mb-4">
    <a href="<?= url('/samba/dominio') ?>" class="text-decoration-none small text-muted d-block mb-1">
        <i class="bi bi-arrow-left"></i> Domínio
    </a>
    <h5 class="mb-0"><i class="bi bi-shield-lock"></i> Política de Senha do Domínio</h5>
    <small class="text-muted">Vale pra toda conta do domínio -- <code>samba-tool domain passwordsettings</code>.</small>
</div>

<?= Alert::flash() ?>

<?php if ($falhou): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($politica['message'] ?? 'Falha ao consultar política.') ?></div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="post" action="<?= url('/samba/dominio/politica-senha') ?>">
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="chk-complexidade" name="complexidade" value="1" <?= !empty($politica['complexidade']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="chk-complexidade">Exigir complexidade (maiúsculas, números, símbolos)</label>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Tamanho mínimo</label>
                        <input type="number" name="tamanho_minimo" class="form-control" min="0" value="<?= (int)($politica['tamanho_minimo'] ?? 7) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Histórico (últimas senhas)</label>
                        <input type="number" name="historico" class="form-control" min="0" value="<?= (int)($politica['historico'] ?? 24) ?>">
                    </div>
                    <div class="col-md-4"></div>

                    <div class="col-md-4">
                        <label class="form-label small mb-1">Idade mínima (dias)</label>
                        <input type="number" name="idade_minima_dias" class="form-control" min="0" value="<?= (int)($politica['idade_minima_dias'] ?? 1) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Idade máxima (dias)</label>
                        <input type="number" name="idade_maxima_dias" class="form-control" min="0" value="<?= (int)($politica['idade_maxima_dias'] ?? 42) ?>">
                    </div>
                    <div class="col-md-4"></div>

                    <div class="col-md-4">
                        <label class="form-label small mb-1">Bloquear após N tentativas erradas (0 = nunca)</label>
                        <input type="number" name="bloqueio_limite_tentativas" class="form-control" min="0" value="<?= (int)($politica['bloqueio_limite_tentativas'] ?? 0) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Duração do bloqueio (minutos)</label>
                        <input type="number" name="bloqueio_duracao_min" class="form-control" min="0" value="<?= (int)($politica['bloqueio_duracao_min'] ?? 30) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Zerar contador de tentativas após (minutos)</label>
                        <input type="number" name="bloqueio_reset_min" class="form-control" min="0" value="<?= (int)($politica['bloqueio_reset_min'] ?? 30) ?>">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary mt-4">
                    <i class="bi bi-check-lg"></i> Salvar política
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Samba - Domínio - Política de Senha';
require __DIR__ . '/../../layouts/main.php';
