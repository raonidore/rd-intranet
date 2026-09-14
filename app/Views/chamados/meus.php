<?php
ob_start();

use App\Components\Alert;
use App\Components\Badge;
use App\Services\ChamadoService;

$corPrioridade = ['baixa' => 'secondary', 'media' => 'primary', 'alta' => 'warning', 'urgente' => 'danger'];
$corStatus = ['fila' => 'secondary', 'em_atendimento' => 'primary', 'aguardando_cliente' => 'warning', 'resolvido' => 'success', 'fechado' => 'dark'];
?>

<?= Alert::flash() ?>

<div class="mb-4 d-flex justify-content-between align-items-start">
    <div>
        <h4 class="mb-1"><i class="bi bi-inbox me-1"></i> Meus Chamados</h4>
        <small class="text-muted">Chamados que você abriu pelo painel -- acompanhe as respostas aqui.</small>
    </div>
    <a href="<?= url('/chamados/atendimentos/novo') ?>" class="btn btn-primary text-nowrap"><i class="bi bi-plus-lg"></i> Abrir Chamado</a>
</div>

<?php if (empty($chamados)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-4">
            <i class="bi bi-inbox" style="font-size:2rem;"></i>
            <p class="mb-0 mt-2">Você ainda não abriu nenhum chamado pelo painel.</p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($chamados as $item): ?>
        <a href="<?= url('/chamados/meus/ver?id=' . (int)$item['id']) ?>" class="text-decoration-none text-reset">
            <div class="card border-0 shadow-sm mb-2">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div style="min-width:0">
                        <span class="font-monospace text-muted small">#<?= htmlspecialchars($item['numero_controle'] ?? $item['id']) ?></span>
                        <strong><?= htmlspecialchars($item['titulo']) ?></strong>
                        <?= Badge::make(htmlspecialchars(ChamadoService::STATUS[$item['status']]), $corStatus[$item['status']] ?? 'secondary') ?>
                        <?= Badge::make(htmlspecialchars(ChamadoService::PRIORIDADES[$item['prioridade']]), $corPrioridade[$item['prioridade']] ?? 'secondary') ?>
                        <div class="text-muted small">
                            <?= htmlspecialchars($item['categoria_nome']) ?> ·
                            <?= htmlspecialchars($item['unidade_nome']) ?>
                        </div>
                    </div>
                    <small class="text-muted text-nowrap"><?= data_br($item['ultima_mensagem_em']) ?></small>
                </div>
            </div>
        </a>
    <?php endforeach; ?>
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Meus Chamados';

require __DIR__ . '/../layouts/main.php';
