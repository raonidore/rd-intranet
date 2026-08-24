<?php
/** @var array|null $avisos */

$severidadeInfo = [
    'informativo' => ['label' => 'Informativo', 'classe' => 'text-bg-info'],
    'atencao'     => ['label' => 'Atenção',     'classe' => 'text-bg-warning'],
    'urgente'     => ['label' => 'Urgente',     'classe' => 'text-bg-danger'],
];
?>
<?php if ($avisos !== null && !empty($avisos['itens'])): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <strong><i class="bi bi-megaphone-fill me-1"></i> Mural de Avisos</strong>
            <div class="d-flex align-items-center gap-2">
                <?php if ($avisos['nao_lidos'] > 0): ?>
                    <span class="badge text-bg-primary"><?= (int)$avisos['nao_lidos'] ?> novo(s)</span>
                <?php endif; ?>
                <a href="<?= url('/avisos') ?>" class="small text-decoration-none">Ver mural completo</a>
            </div>
        </div>

        <?php foreach ($avisos['itens'] as $aviso):
            $sev = $severidadeInfo[$aviso['severidade']] ?? $severidadeInfo['informativo'];
            $naoLido = empty($aviso['visto_em']);
        ?>
            <a href="<?= url('/avisos') ?>" class="d-block border rounded p-2 mb-2 text-decoration-none text-body"
               style="<?= $naoLido ? 'border-left:3px solid var(--rd-primary, #2f5fed) !important;' : '' ?>">
                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                    <?php if ($aviso['fixado']): ?><i class="bi bi-pin-angle-fill text-muted small"></i><?php endif; ?>
                    <span class="badge <?= $sev['classe'] ?>" style="font-size:10px;"><?= $sev['label'] ?></span>
                    <strong class="small"><?= htmlspecialchars($aviso['titulo']) ?></strong>
                </div>
                <div class="text-muted small text-truncate"><?= htmlspecialchars(mb_strimwidth($aviso['conteudo'], 0, 100, '...')) ?></div>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
