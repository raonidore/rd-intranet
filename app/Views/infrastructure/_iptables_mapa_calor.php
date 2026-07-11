<?php
/**
 * Grade compacta das regras ativas, em ordem de aplicação, colorida pela
 * ação -- visão de relance da prioridade/composição do ruleset sem precisar
 * ler a tabela inteira. Espera $porGrupo (mesmo array de iptables.php).
 */
$celulas = [];
foreach ($porGrupo as $tabela => $porCadeia) {
    foreach ($porCadeia as $cadeia => $lista) {
        foreach ($lista as $r) {
            if ((int)$r['ativo'] === 1) {
                $celulas[] = $r + ['cadeia' => $cadeia];
            }
        }
    }
}
?>
<?php if (!empty($celulas)): ?>
<style>
.mapa-calor { display: flex; flex-wrap: wrap; gap: 3px; }
.mapa-celula {
    width: 22px; height: 22px; border-radius: 4px; cursor: default;
    display: flex; align-items: center; justify-content: center;
}
</style>

<div class="card fw-card">
    <div class="card-header"><i class="bi bi-grid-3x3-gap me-1"></i> Mapa de regras ativas (ordem de aplicação)</div>
    <div class="card-body">
        <div class="mapa-calor">
            <?php foreach ($celulas as $c): ?>
                <div class="mapa-celula text-bg-<?= iptablesAcaoCor($c['acao']) ?>"
                     title="<?= htmlspecialchars($c['cadeia'] . ': ' . $c['nome'] . ' (' . $c['acao'] . ')') ?>"
                     data-bs-toggle="tooltip"></div>
            <?php endforeach; ?>
        </div>
        <div class="d-flex gap-3 mt-3 small text-muted flex-wrap">
            <span><span class="mapa-celula text-bg-success d-inline-flex" style="width:12px;height:12px;"></span> ACCEPT</span>
            <span><span class="mapa-celula text-bg-danger d-inline-flex" style="width:12px;height:12px;"></span> DROP/REJECT</span>
            <span><span class="mapa-celula text-bg-info d-inline-flex" style="width:12px;height:12px;"></span> NAT</span>
            <span><span class="mapa-celula text-bg-secondary d-inline-flex" style="width:12px;height:12px;"></span> LOG</span>
        </div>
    </div>
</div>

<script>
(function () {
    document.querySelectorAll('.mapa-celula[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });
})();
</script>
<?php endif; ?>
