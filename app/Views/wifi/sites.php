<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-3">
    <a href="<?= url('/wifi/relatorios') ?>" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Relatórios</a>
    <h4 class="mb-1 mt-1"><i class="bi bi-globe me-1"></i> Sites consultados</h4>
    <small class="text-muted">Consultas de DNS da rede do portal, guardadas no máximo <?= (int)$dias ?> dia(s) -- só para diagnóstico. Esta consulta fica registrada na auditoria.</small>
</div>

<form class="card border-0 shadow-sm mb-3" method="get" action="<?= url('/wifi/relatorios/sites') ?>">
    <div class="card-body d-flex flex-wrap gap-2 align-items-end">
        <div><label class="form-label small mb-0">IP do aparelho</label><input name="ip" class="form-control form-control-sm" value="<?= htmlspecialchars((string)$ip) ?>" placeholder="10.50.0.23"></div>
        <div><label class="form-label small mb-0">ou site</label><input name="busca" class="form-control form-control-sm" value="<?= htmlspecialchars((string)$busca) ?>" placeholder="exemplo.com"></div>
        <button class="btn btn-sm btn-primary">Buscar</button>
    </div>
</form>

<?php if ($erro): ?>
<div class="alert alert-warning"><?= htmlspecialchars($erro) ?></div>
<?php else: ?>
<div class="card border-0 shadow-sm"><div class="card-body p-0">
    <?php if (!$lista): ?><p class="text-muted p-3 mb-0">Nada encontrado.</p><?php else: ?>
    <div class="table-responsive">
    <table class="table table-sm align-middle mb-0 small">
        <thead><tr><th class="ps-3">Quando</th><th>Aparelho</th><th>Site</th><th>Resultado</th></tr></thead>
        <?php foreach ($lista as $l): ?>
        <tr class="<?= $l['bloqueado'] ? 'table-danger' : '' ?>">
            <td class="ps-3 text-nowrap"><?= $l['quando'] ? date('d/m H:i:s', strtotime($l['quando'])) : '' ?></td>
            <td><?= htmlspecialchars($l['ip']) ?> <span class="text-muted"><?= htmlspecialchars($l['cliente']) ?></span></td>
            <td class="text-break"><?= htmlspecialchars($l['dominio']) ?></td>
            <td><?= $l['bloqueado'] ? '<span class="badge text-bg-danger">bloqueado</span>' : '<span class="text-muted">liberado</span>' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
    <?php endif; ?>
</div></div>
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Wi-Fi - Sites consultados';

require __DIR__ . '/../layouts/main.php';
