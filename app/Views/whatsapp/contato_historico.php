<?php
ob_start();

use App\Components\Alert;
use App\Components\Badge;

require __DIR__ . '/_bolha_mensagem.php';

$corStatus = [
    'bot' => 'secondary',
    'fila' => 'warning',
    'em_atendimento' => 'primary',
    'aguardando_nps_atendente' => 'info',
    'aguardando_nps_resolucao' => 'info',
    'encerrado' => 'dark',
];
$rotuloStatus = [
    'bot' => 'Com o bot',
    'fila' => 'Na fila',
    'em_atendimento' => 'Em atendimento',
    'aguardando_nps_atendente' => 'Aguardando avaliação',
    'aguardando_nps_resolucao' => 'Aguardando avaliação',
    'encerrado' => 'Encerrado',
];
?>

<?= Alert::flash() ?>

<div class="mb-4 d-flex justify-content-between align-items-start">
    <div>
        <small class="text-muted"><a href="<?= url('/whatsapp/contatos') ?>"><i class="bi bi-arrow-left"></i> Contatos</a></small>
        <h4 class="mb-1 mt-1"><i class="bi bi-chat-square-text me-1"></i> <?= htmlspecialchars($contato['nome'] ?: '(sem nome)') ?></h4>
        <small class="text-muted"><?= htmlspecialchars(telefone_br($contato['numero'])) ?> &middot; <?= count($atendimentos) ?> atendimento<?= count($atendimentos) === 1 ? '' : 's' ?></small>
    </div>
    <form method="post" action="<?= url('/whatsapp/contatos/reabrir') ?>">
        <input type="hidden" name="id" value="<?= (int)$contato['id'] ?>">
        <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-repeat"></i> Reabrir atendimento</button>
    </form>
</div>

<?php if (empty($atendimentos)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-4">
            <i class="bi bi-chat" style="font-size:2rem;"></i>
            <p class="mb-0 mt-2">Esse contato ainda não teve nenhum atendimento.</p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($atendimentos as $atendimento): ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>
                    <strong>Atendimento #<?= (int)$atendimento['id'] ?></strong>
                    <?= Badge::make(htmlspecialchars($rotuloStatus[$atendimento['status']] ?? $atendimento['status']), $corStatus[$atendimento['status']] ?? 'secondary') ?>
                    <?php if ($atendimento['setor_nome']): ?>
                        <span class="badge text-bg-light border"><?= htmlspecialchars($atendimento['setor_nome']) ?></span>
                    <?php endif; ?>
                    <?php if ($atendimento['usuario_nome']): ?>
                        <span class="text-muted small">Atendido por <?= htmlspecialchars($atendimento['usuario_nome']) ?></span>
                    <?php endif; ?>
                </span>
                <small class="text-muted">
                    Aberto em <?= data_br($atendimento['aberto_em']) ?>
                    <?= $atendimento['encerrado_em'] ? ' &middot; encerrado em ' . data_br($atendimento['encerrado_em']) : '' ?>
                </small>
            </div>
            <div class="card-body" style="max-height:360px; overflow-y:auto; background:#f5f7fb;">
                <?php if (empty($atendimento['mensagens'])): ?>
                    <p class="text-muted small mb-0">Nenhuma mensagem registrada.</p>
                <?php else: ?>
                    <?php foreach ($atendimento['mensagens'] as $m): ?>
                        <?= renderizarBolha($m) ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'WhatsApp - Histórico do contato';

require __DIR__ . '/../layouts/main.php';
