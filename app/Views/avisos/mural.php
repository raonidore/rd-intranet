<?php
ob_start();

use App\Components\Alert;

$severidadeInfo = [
    'informativo' => ['label' => 'Informativo', 'classe' => 'text-bg-info'],
    'atencao'     => ['label' => 'Atenção',     'classe' => 'text-bg-warning'],
    'urgente'     => ['label' => 'Urgente',     'classe' => 'text-bg-danger'],
];
?>

<?= Alert::flash() ?>

<div class="mb-4 d-flex justify-content-between align-items-start">
    <div>
        <h4 class="mb-1"><i class="bi bi-megaphone-fill me-1"></i> Mural de Avisos</h4>
        <small class="text-muted">Comunicados endereçados a você.</small>
    </div>
    <?php if ($podeGerenciar): ?>
        <a href="<?= url('/avisos/gerenciar') ?>" class="btn btn-outline-secondary text-nowrap">
            <i class="bi bi-gear"></i> Gerenciar avisos
        </a>
    <?php endif; ?>
</div>

<?php if (empty($avisos)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-megaphone" style="font-size:2rem;"></i>
            <p class="mb-0 mt-2">Nenhum aviso por aqui no momento.</p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($avisos as $aviso):
        $sev = $severidadeInfo[$aviso['severidade']] ?? $severidadeInfo['informativo'];
        $naoLido = empty($aviso['visto_em']);
        $precisaConfirmar = !empty($aviso['confirmacao_obrigatoria']) && empty($aviso['confirmado_em']);
    ?>
        <div class="card border-0 shadow-sm mb-3 aviso-card" data-aviso-id="<?= (int)$aviso['id'] ?>" style="<?= $naoLido ? 'border-left:3px solid var(--rd-primary, #2f5fed) !important;' : '' ?>">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <?php if ($aviso['fixado']): ?><span class="badge text-bg-light border"><i class="bi bi-pin-angle-fill"></i> Fixado</span><?php endif; ?>
                    <span class="badge <?= $sev['classe'] ?>"><?= $sev['label'] ?></span>
                    <?php if ($naoLido): ?><span class="badge text-bg-primary">Novo</span><?php endif; ?>
                    <strong class="fs-5 mb-0"><?= htmlspecialchars($aviso['titulo']) ?></strong>
                </div>
                <p class="mb-2" style="white-space: pre-wrap;"><?= htmlspecialchars($aviso['conteudo']) ?></p>
                <div class="text-muted small">Publicado em <?= date('d/m/Y \à\s H:i', strtotime($aviso['criado_em'])) ?></div>

                <?php if ($precisaConfirmar): ?>
                    <form method="post" action="<?= url('/avisos/confirmar') ?>" class="mt-3">
                        <input type="hidden" name="id" value="<?= (int)$aviso['id'] ?>">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-circle"></i> Confirmar que li</button>
                    </form>
                <?php elseif (!empty($aviso['confirmado_em'])): ?>
                    <div class="text-success small mt-2"><i class="bi bi-check-circle-fill"></i> Você confirmou a leitura em <?= date('d/m/Y H:i', strtotime($aviso['confirmado_em'])) ?></div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
(function () {
    const urlMarcar = <?= json_encode(url('/avisos/marcar-visto')) ?>;
    const marcados = new Set();

    document.querySelectorAll('.aviso-card').forEach(function (card) {
        card.addEventListener('click', function () {
            const id = card.dataset.avisoId;
            if (marcados.has(id)) return;
            marcados.add(id);

            card.style.borderLeft = 'none';
            const badgeNovo = card.querySelector('.badge.text-bg-primary');
            if (badgeNovo) badgeNovo.remove();

            fetch(urlMarcar, { method: 'POST', body: new URLSearchParams({ id: id }) }).catch(() => {});
        }, { once: true });
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Mural de Avisos';

require __DIR__ . '/../layouts/main.php';
