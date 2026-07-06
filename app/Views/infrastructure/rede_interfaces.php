<?php
ob_start();

use App\Components\Alert;
?>

<style>
.server-card { border:0; border-radius:14px; box-shadow:0 4px 14px rgba(0,0,0,.06); }
</style>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-ethernet me-1"></i> Interfaces de Rede</h4>
        <small class="text-muted">Estado, endereços e tráfego acumulado de cada interface.</small>
    </div>
    <a href="<?= url('/infraestrutura/servidor') ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Servidor
    </a>
</div>

<div class="card server-card">
    <div class="card-body p-0">
        <div id="interfaces-container">
            <?= renderInterfacesNet($interfaces) ?>
        </div>
    </div>
</div>

<?php
function renderInterfacesNet(array $interfaces): string
{
    if (empty($interfaces)) {
        return '<div class="text-center text-muted py-4"><i class="bi bi-diagram-3 display-6"></i><p class="mt-2">Nenhuma interface encontrada</p></div>';
    }

    $html = '<table class="table table-hover align-middle mb-0"><thead><tr><th>Interface</th><th>Estado</th><th>Endereços</th><th>MAC</th><th><i class="bi bi-arrow-down"></i> Download</th><th><i class="bi bi-arrow-up"></i> Upload</th><th></th></tr></thead><tbody>';
    foreach ($interfaces as $i) {
        $estadoBadge = $i['estado'] === 'up' ? '<span class="badge bg-success">Up</span>' : '<span class="badge bg-secondary">Down</span>';
        $enderecos = array_merge($i['ipv4'], $i['ipv6']);
        $editarBtn = $i['nome'] === 'lo' ? '' : '<a href="' . url('/infraestrutura/servidor/rede/editar?interface=' . urlencode($i['nome'])) . '" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>';
        $html .= '<tr><td><strong>' . htmlspecialchars($i['nome']) . '</strong></td>'
            . '<td>' . $estadoBadge . '</td>'
            . '<td>' . (empty($enderecos) ? '-' : htmlspecialchars(implode(', ', $enderecos))) . '</td>'
            . '<td><code>' . htmlspecialchars($i['mac']) . '</code></td>'
            . '<td>' . htmlspecialchars($i['rx_fmt']) . '</td>'
            . '<td>' . htmlspecialchars($i['tx_fmt']) . '</td>'
            . '<td>' . $editarBtn . '</td></tr>';
    }
    return $html . '</tbody></table>';
}
?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - Interfaces de Rede';

require __DIR__ . '/../layouts/main.php';
