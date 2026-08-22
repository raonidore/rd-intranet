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
                <label class="form-check-label" for="anexosAtivos">Atendente pode enviar e receber anexos</label>
            </div>
            <p class="text-muted small mb-3">
                Libera imagem, áudio e documento na conversa (envio pelo atendente e recebimento do cliente).
                <?php if (!$anexosAtivos): ?>
                    Hoje o módulo só troca texto -- essa opção fica pronta pra quando o suporte a mídia entrar.
                <?php endif; ?>
            </p>
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        </form>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'WhatsApp - Configurações';

require __DIR__ . '/../layouts/main.php';
