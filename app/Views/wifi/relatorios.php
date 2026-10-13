<?php
ob_start();

use App\Components\Alert;

require __DIR__ . '/_util.php';
$q = http_build_query($filtro);
?>

<?= Alert::flash() ?>

<div class="mb-4 d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-clipboard-data me-1"></i> Relatórios e LGPD</h4>
        <small class="text-muted">Registros de conexão (guardados <?= (int)$retencao ?> dias e depois anonimizados). Toda consulta e exportação fica na auditoria.</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('/wifi/relatorios/sites') ?>" class="btn btn-outline-secondary"><i class="bi bi-globe"></i> Sites consultados</a>
        <a href="<?= url('/wifi/relatorios?' . $q . '&formato=csv') ?>" class="btn btn-outline-primary"><i class="bi bi-filetype-csv"></i> Exportar CSV</a>
    </div>
</div>

<form class="card border-0 shadow-sm mb-3" method="get" action="<?= url('/wifi/relatorios') ?>">
    <div class="card-body d-flex flex-wrap gap-2 align-items-end">
        <div><label class="form-label small mb-0">De</label><input type="date" name="de" class="form-control form-control-sm" value="<?= htmlspecialchars($filtro['de']) ?>"></div>
        <div><label class="form-label small mb-0">Até</label><input type="date" name="ate" class="form-control form-control-sm" value="<?= htmlspecialchars($filtro['ate']) ?>"></div>
        <div><label class="form-label small mb-0">Perfil</label>
            <select name="perfil_id" class="form-select form-select-sm"><option value="">Todos</option>
            <?php foreach ($perfis as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (string)$filtro['perfil_id'] === (string)$p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nome']) ?></option><?php endforeach; ?>
            </select></div>
        <button class="btn btn-sm btn-primary">Filtrar</button>
    </div>
</form>

<div class="row g-3 mb-3">
<?php foreach ($resumo as $r): ?>
    <div class="col-md-6 col-xl-4"><div class="card border-0 shadow-sm h-100" style="border-left:4px solid <?= htmlspecialchars($r['cor']) ?> !important"><div class="card-body">
        <div class="fw-semibold mb-1"><?= htmlspecialchars($r['nome']) ?></div>
        <div class="small text-muted"><?= (int)$r['pessoas'] ?> pessoa(s) &middot; <?= (int)$r['aparelhos'] ?> aparelho(s) &middot; <?= (int)$r['sessoes'] ?> conexão(ões)</div>
        <div class="small">&darr; <?= wifi_bytes($r['bytes_down']) ?> &middot; &uarr; <?= wifi_bytes($r['bytes_up']) ?></div>
    </div></div></div>
<?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent fw-semibold">Conexões <small class="text-muted fw-normal">(até 300 mais recentes; o CSV traz todas)</small></div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 small">
            <thead><tr><th class="ps-3">Início</th><th>Fim</th><th>Pessoa</th><th>Perfil</th><th>Aparelho / IP</th><th class="text-end">Download</th><th class="text-end">Upload</th><th>Encerramento</th></tr></thead>
            <?php foreach ($sessoes as $s): ?>
            <tr>
                <td class="ps-3"><?= date('d/m/Y H:i', strtotime($s['inicio'])) ?></td>
                <td><?= $s['fim'] ? date('d/m H:i', strtotime($s['fim'])) : '<span class="badge text-bg-success">conectado</span>' ?></td>
                <td><?= $s['anonimizado'] ? '<span class="text-muted">(anonimizado)</span>' : ($s['pessoa_id'] ? '<a href="' . url('/wifi/pessoas/ver?id=' . (int)$s['pessoa_id']) . '">' . htmlspecialchars((string)$s['pessoa_nome']) . '</a>' : '') ?></td>
                <td><?= wifi_perfil($s['perfil_nome'], $s['perfil_cor']) ?></td>
                <td><code><?= htmlspecialchars(strtoupper((string)$s['mac'])) ?></code> <?= htmlspecialchars((string)$s['ip']) ?></td>
                <td class="text-end"><?= wifi_bytes($s['bytes_down']) ?></td>
                <td class="text-end"><?= wifi_bytes($s['bytes_up']) ?></td>
                <td class="text-muted"><?= htmlspecialchars((string)$s['motivo_fim']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        </div>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Wi-Fi - Relatórios';

require __DIR__ . '/../layouts/main.php';
