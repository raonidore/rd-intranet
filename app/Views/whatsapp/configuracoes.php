<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-gear me-1"></i> WhatsApp - Configurações</h4>
    <small class="text-muted">Regras gerais do módulo, valem pra todos os setores.</small>
</div>

<div class="card border-0 shadow-sm" style="max-width:700px">
    <div class="card-body">
        <form method="post" action="<?= url('/whatsapp/configuracoes/salvar') ?>">
            <div class="form-check form-switch mb-2">
                <input type="checkbox" name="anexos_ativos" class="form-check-input" id="anexosAtivos" role="switch" <?= $anexosAtivos ? 'checked' : '' ?>>
                <label class="form-check-label" for="anexosAtivos"><strong>Atendente pode enviar e receber anexos</strong></label>
            </div>
            <p class="text-muted small mb-3">
                Chave-mestra -- desligada, nenhum anexo passa, não importa o que as chaves abaixo digam.
            </p>

            <div class="ms-4">
                <div class="form-check form-switch mb-2">
                    <input type="checkbox" name="imagens_ativas" class="form-check-input" id="imagensAtivas" role="switch" <?= $imagensAtivas ? 'checked' : '' ?>>
                    <label class="form-check-label" for="imagensAtivas"><i class="bi bi-image me-1"></i> Imagens</label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input type="checkbox" name="documentos_ativos" class="form-check-input" id="documentosAtivos" role="switch" <?= $documentosAtivos ? 'checked' : '' ?>>
                    <label class="form-check-label" for="documentosAtivos"><i class="bi bi-file-earmark me-1"></i> Documentos</label>
                </div>
                <div class="form-check form-switch mb-3">
                    <input type="checkbox" name="audios_ativos" class="form-check-input" id="audiosAtivos" role="switch" <?= $audiosAtivos ? 'checked' : '' ?>>
                    <label class="form-check-label" for="audiosAtivos"><i class="bi bi-mic me-1"></i> Áudios</label>
                </div>
            </div>

            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        </form>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'WhatsApp - Configurações';

require __DIR__ . '/../layouts/main.php';
