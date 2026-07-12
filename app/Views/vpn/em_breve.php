<?php
ob_start();
?>

<style>
.tech-card { background: #0f172a; border-radius: 16px; border: 1px solid #1e293b; color: #e2e8f0; }
.tech-label { font-size:11px; text-transform:uppercase; letter-spacing:.08em; color:#94a3b8; }
</style>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi <?= htmlspecialchars($icone) ?> me-1"></i> <?= htmlspecialchars($titulo) ?></h4>
    <small class="text-muted"><a href="<?= url('/vpn') ?>"><i class="bi bi-arrow-left"></i> Voltar ao Dashboard VPN</a></small>
</div>

<div class="tech-card p-5 text-center">
    <i class="bi bi-cone-striped display-5 text-muted"></i>
    <h5 class="mt-3">Ainda não disponível nesta fase</h5>
    <p class="text-muted mb-0" style="max-width: 560px; margin: 0 auto;">
        <?= htmlspecialchars($descricao) ?>
    </p>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'VPN - ' . $titulo;

require __DIR__ . '/../layouts/main.php';
