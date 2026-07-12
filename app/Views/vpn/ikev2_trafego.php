<?php
ob_start();

use App\Components\Alert;

function formatBytesIkev2Trafego(int $bytes): string
{
    if ($bytes <= 0) return '0 B';
    $unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = (int)floor(log($bytes, 1024));
    $i = min($i, count($unidades) - 1);
    return round($bytes / (1024 ** $i), 1) . ' ' . $unidades[$i];
}

$clientes = array_values(array_filter($status['clientes'], fn($c) => (int)$c['ativo'] === 1));
?>

<style>
.mapa-calor { display: flex; flex-wrap: wrap; gap: 3px; }
.mapa-celula { width: 18px; height: 18px; border-radius: 4px; cursor: default; }
</style>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-bar-chart-line me-1"></i> IKEv2 - Tráfego</h4>
        <small class="text-muted"><a href="<?= url('/vpn') ?>"><i class="bi bi-arrow-left"></i> Dashboard VPN</a></small>
    </div>
</div>

<?php if (!$coletaAtiva): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div class="small text-muted">
                <i class="bi bi-info-circle"></i> Coleta automática de histórico não está ativa (roda a cada 5 minutos).
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="botaoAtivarColeta">Ativar coleta</button>
        </div>
    </div>
<?php endif; ?>

<?php if (empty($clientes)): ?>
    <div class="alert alert-info">Nenhum cliente ativo ainda.</div>
<?php else: ?>

    <form method="get" action="<?= url('/vpn/ikev2/trafego') ?>" class="mb-4">
        <label class="form-label">Cliente</label>
        <select name="cliente_id" class="form-select" style="max-width:320px" onchange="this.form.submit()">
            <?php foreach ($clientes as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === (int)$clienteSelecionado ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['nome']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if (empty($historico)): ?>
        <div class="alert alert-secondary">
            Ainda não há histórico suficiente para este cliente.
            <?= !$coletaAtiva ? ' Ative a coleta acima para começar a acumular dados.' : '' ?>
        </div>
    <?php else: ?>
        <div class="card fw-card mb-4">
            <div class="card-header"><i class="bi bi-grid-3x3-gap me-1"></i> Atividade recente (mais nova à esquerda)</div>
            <div class="card-body">
                <div class="mapa-calor">
                    <?php foreach ($historico as $h): ?>
                        <?php $ativo = ((int)$h['rx_bytes'] + (int)$h['tx_bytes']) > 0; ?>
                        <div class="mapa-celula text-bg-<?= $ativo ? 'success' : 'secondary' ?>"
                             title="<?= htmlspecialchars(data_br($h['coletado_em'])) ?>: <?= formatBytesIkev2Trafego((int)$h['rx_bytes']) ?> ↓ / <?= formatBytesIkev2Trafego((int)$h['tx_bytes']) ?> ↑"
                             data-bs-toggle="tooltip"></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><strong>Histórico detalhado</strong></div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Coletado em</th>
                            <th class="text-end">Download</th>
                            <th class="text-end">Upload</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historico as $h): ?>
                            <tr>
                                <td class="small"><?= htmlspecialchars(data_br($h['coletado_em'])) ?></td>
                                <td class="text-end font-monospace"><?= formatBytesIkev2Trafego((int)$h['rx_bytes']) ?></td>
                                <td class="text-end font-monospace"><?= formatBytesIkev2Trafego((int)$h['tx_bytes']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>

<script>
(function () {
    document.querySelectorAll('.mapa-celula[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    const botaoAtivarColeta = document.getElementById('botaoAtivarColeta');
    if (botaoAtivarColeta) {
        botaoAtivarColeta.addEventListener('click', async function () {
            botaoAtivarColeta.disabled = true;
            try {
                const res = await fetch(<?= json_encode(url('/vpn/ikev2/ativar-coleta')) ?>, { method: 'POST' });
                const dados = await res.json();
                alert(dados.message || (dados.success ? 'Ativado.' : 'Falha ao ativar.'));
                location.reload();
            } catch (e) {
                botaoAtivarColeta.disabled = false;
            }
        });
    }
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'VPN - IKEv2 - Tráfego';

require __DIR__ . '/../layouts/main.php';
