<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-chat-dots me-1"></i> WhatsApp - Atendimentos</h4>
    <small class="text-muted">Suas conversas em andamento. Novos atendimentos aparecem em <a href="<?= url('/whatsapp/fila') ?>">Fila</a>.</small>
</div>

<?php if (empty($atendimentos)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-4">
            <i class="bi bi-chat" style="font-size:2rem;"></i>
            <p class="mb-0 mt-2">Você não tem nenhum atendimento em andamento.</p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($atendimentos as $item): ?>
        <a href="<?= url('/whatsapp/atendimentos/ver?id=' . (int)$item['id']) ?>" class="text-decoration-none text-reset">
            <div class="card border-0 shadow-sm mb-2">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?= htmlspecialchars($item['contato_nome'] ?: '(sem nome)') ?></strong>
                        <span class="text-muted small ms-1"><?= htmlspecialchars($item['numero']) ?></span>
                        <div class="text-muted small text-truncate" style="max-width:480px">
                            <?= $item['ultima_mensagem'] !== null ? htmlspecialchars($item['ultima_mensagem']) : '(sem mensagens)' ?>
                        </div>
                    </div>
                    <small class="text-muted text-nowrap"><?= htmlspecialchars($item['ultima_mensagem_em']) ?></small>
                </div>
            </div>
        </a>
    <?php endforeach; ?>
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'WhatsApp - Atendimentos';

require __DIR__ . '/../layouts/main.php';
