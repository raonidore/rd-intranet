<?php
ob_start();

use App\Components\Alert;

$usadas = array_column($diag['redes'], 'vlan');
$vlanSugerida = 50;
while (in_array($vlanSugerida, $usadas, true)) {
    $vlanSugerida += 10;
}
?>

<?= Alert::flash() ?>

<div class="mb-3">
    <a href="<?= url('/wifi/configuracoes') ?>" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Configurações</a>
    <h4 class="mb-1 mt-1"><i class="bi bi-diagram-3 me-1"></i> Assistente de rede do portal</h4>
    <small class="text-muted">Cria a rede de visitantes (VLAN + DHCP), o Wi-Fi aberto com portal, aponta o portal do equipamento para este servidor e leva a VLAN pelos switches de outras marcas.</small>
</div>

<?php if ($resultado && !empty($resultado['passos'])): ?>
<div class="alert <?= $resultado['success'] ? 'alert-success' : 'alert-warning' ?>">
    <strong><?= $resultado['success'] ? 'Feito:' : 'Parou no meio. Já feito:' ?></strong>
    <ul class="mb-0"><?php foreach ($resultado['passos'] as $p): ?><li><?= htmlspecialchars($p) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<?php if (!$diag['controlador']): ?>
<div class="alert alert-danger">A integração UniFi não está configurada (Ativos &gt; Integrações &gt; UniFi). O assistente usa a API dela.</div>
<?php else: ?>

<div class="row g-3 mb-3">
    <div class="col-lg-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="fw-semibold mb-2">Portal no equipamento</div>
        <?php if (!$diag['portal']): ?><span class="text-muted small">não foi possível ler</span>
        <?php elseif ($diag['portal']['aponta_para_nos']): ?><span class="badge text-bg-success">apontando para este servidor (<?= htmlspecialchars($diag['ip']) ?>)</span>
        <?php else: ?><span class="badge text-bg-warning">ainda não aponta para este servidor</span>
            <div class="small text-muted mt-1">Hoje: <?= htmlspecialchars($diag['portal']['modo'] ?: 'sem portal') ?> <?= htmlspecialchars($diag['portal']['ip']) ?></div><?php endif; ?>
    </div></div></div>
    <div class="col-lg-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="fw-semibold mb-2">Redes (VLANs)</div>
        <ul class="list-unstyled small mb-0">
        <?php foreach ($diag['redes'] as $r): ?>
            <li>VLAN <?= (int)$r['vlan'] ?> &middot; <?= htmlspecialchars($r['nome']) ?> <span class="text-muted"><?= htmlspecialchars($r['subnet']) ?></span><?= $r['dns'] === $diag['ip'] ? ' <span class="badge text-bg-success">DNS filtro</span>' : '' ?></li>
        <?php endforeach; ?>
        </ul>
    </div></div></div>
    <div class="col-lg-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="fw-semibold mb-2">Wi-Fis</div>
        <ul class="list-unstyled small mb-0">
        <?php foreach ($diag['wifis'] as $w): ?>
            <li><?= htmlspecialchars($w['nome']) ?> <?= $w['portal'] ? '<span class="badge text-bg-primary">portal</span>' : '' ?> <?= $w['ativa'] ? '' : '<span class="badge text-bg-secondary">desligado</span>' ?></li>
        <?php endforeach; ?>
        <?php if (!$diag['wifis']): ?><li class="text-muted">nenhum (nenhum AP adotado ainda?)</li><?php endif; ?>
        </ul>
    </div></div></div>
</div>

<form method="post" action="<?= url('/wifi/rede/aplicar') ?>" onsubmit="return confirm('Aplicar estas mudanças no equipamento e nos switches marcados?')">
<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-transparent fw-semibold">1. Rede de visitantes</div><div class="card-body row g-3">
    <div class="col-12">
        <div class="form-check"><input class="form-check-input" type="radio" name="modo_rede" value="nova" id="redeNova" checked onchange="trocarModo()"><label class="form-check-label" for="redeNova">Criar uma rede nova (recomendado)</label></div>
        <div class="form-check"><input class="form-check-input" type="radio" name="modo_rede" value="existente" id="redeExist" onchange="trocarModo()"><label class="form-check-label" for="redeExist">Usar uma rede que já existe</label></div>
    </div>
    <div id="camposNova" class="row g-3 m-0 p-0">
        <div class="col-md-2"><label class="form-label small">VLAN</label><input type="number" name="vlan" class="form-control" value="<?= $vlanSugerida ?>" min="2" max="4094" id="vlanNum"></div>
        <div class="col-md-4"><label class="form-label small">Nome</label><input name="nome" class="form-control" value="WiFi-Portal" maxlength="32"></div>
        <div class="col-md-6"><label class="form-label small">Gateway / sub-rede</label><input name="subnet" class="form-control" value="10.<?= $vlanSugerida ?>.0.1/24" id="subnet"></div>
        <div class="col-md-6"><label class="form-label small">DHCP de</label><input name="dhcp_inicio" class="form-control" value="10.<?= $vlanSugerida ?>.0.10"></div>
        <div class="col-md-6"><label class="form-label small">até</label><input name="dhcp_fim" class="form-control" value="10.<?= $vlanSugerida ?>.0.250"></div>
        <div class="col-12 small text-muted">Os aparelhos ficam só com internet: a política de visitantes do UniFi bloqueia as redes internas e libera apenas este servidor (portal e DNS)<?= $filtroAtivo ? ', entregando o DNS do filtro (' . htmlspecialchars($diag['ip']) . ')' : '' ?>.</div>
    </div>
    <div id="camposExist" class="col-md-6 d-none">
        <select name="rede_existente" class="form-select" disabled>
            <?php foreach ($diag['redes'] as $r): ?><option value="<?= htmlspecialchars($r['id']) ?>" data-vlan="<?= (int)$r['vlan'] ?>">VLAN <?= (int)$r['vlan'] ?> - <?= htmlspecialchars($r['nome']) ?></option><?php endforeach; ?>
        </select>
    </div>
</div></div>

<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-transparent fw-semibold">2. Wi-Fi com portal</div><div class="card-body row g-3">
    <div class="col-md-6"><label class="form-label small">Nome do Wi-Fi (SSID) <small class="text-muted">vazio = não criar</small></label><input name="ssid" class="form-control" maxlength="32" placeholder="Visitantes"></div>
    <div class="col-md-6"><label class="form-label small">Tempo máximo de autorização no equipamento (min)</label><input type="number" name="expira_min" class="form-control" value="480" min="60">
        <div class="form-text">O tempo real de cada pessoa vem do perfil dela; este é só o padrão do equipamento.</div></div>
</div></div>

<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-transparent fw-semibold">3. Switches de outras marcas no caminho</div><div class="card-body">
    <?php if (!$diag['switches']): ?>
    <p class="text-muted small mb-0">Nenhum switch de outra marca cadastrado -- os switches UniFi já levam todas as VLANs.</p>
    <?php else: ?>
    <p class="small text-muted">Marque as portas por onde a VLAN precisa passar (ligação com o gateway, com o AP e entre switches). As portas com vizinho identificado por LLDP já vêm sugeridas. Só é <strong>acrescentada</strong> a VLAN tagueada; nada é retirado.</p>
    <?php foreach ($diag['switches'] as $s): ?>
    <div class="mb-3">
        <div class="fw-semibold"><?= htmlspecialchars($s['nome']) ?> <span class="text-muted small"><?= htmlspecialchars((string)$s['ip']) ?> &middot; <?= htmlspecialchars((string)$s['driver']) ?></span>
            <?= $s['pode'] ? '' : '<span class="badge text-bg-warning">sem acesso de configuração (credencial SSH)</span>' ?></div>
        <div class="d-flex flex-wrap gap-2 mt-1">
        <?php foreach ($s['portas'] as $p): if (!$p['link']) { continue; } ?>
            <input type="checkbox" class="btn-check" name="portas[<?= (int)$s['id'] ?>][]" value="<?= htmlspecialchars($p['porta']) ?>" id="p<?= (int)$s['id'] . '_' . md5($p['porta']) ?>" <?= $p['sugerida'] ? 'checked' : '' ?> <?= $s['pode'] ? '' : 'disabled' ?>>
            <label class="btn btn-sm btn-outline-secondary text-start" for="p<?= (int)$s['id'] . '_' . md5($p['porta']) ?>" title="VLANs: <?= htmlspecialchars(implode(', ', $p['vlans'])) ?>">
                <?= htmlspecialchars($p['porta']) ?><?= $p['resumo'] ? '<br><small class="text-muted">' . htmlspecialchars(mb_strimwidth($p['resumo'], 0, 28, '…')) . '</small>' : '' ?>
            </label>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div></div>

<button class="btn btn-primary mb-4"><i class="bi bi-magic"></i> Aplicar</button>
</form>

<script>
function trocarModo() {
    const existente = document.getElementById('redeExist').checked;
    document.getElementById('camposNova').classList.toggle('d-none', existente);
    document.getElementById('camposExist').classList.toggle('d-none', !existente);
    document.querySelector('[name=rede_existente]').disabled = !existente;
    document.querySelectorAll('#camposNova input').forEach(function (i) { i.disabled = existente && i.id !== 'vlanNum'; });
    if (existente) {
        const sel = document.querySelector('[name=rede_existente]');
        document.getElementById('vlanNum').value = sel.options[sel.selectedIndex]?.dataset.vlan || '';
    }
}
document.querySelector('[name=rede_existente]')?.addEventListener('change', trocarModo);
</script>
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Wi-Fi - Assistente de rede';

require __DIR__ . '/../layouts/main.php';
