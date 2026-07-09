<?php
ob_start();

use App\Components\Alert;

function formatBytesHistorico(int $bytes): string
{
    if ($bytes <= 0) return '0 B';
    $unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = (int)floor(log($bytes, 1024));
    $i = min($i, count($unidades) - 1);
    return round($bytes / (1024 ** $i), 1) . ' ' . $unidades[$i];
}

$totalDownload = array_sum(array_column($consumo, 'download'));
$totalUpload = array_sum(array_column($consumo, 'upload'));
?>

<style>
.tech-card {
    background: #0f172a;
    border-radius: 16px;
    border: 1px solid #1e293b;
    color: #e2e8f0;
}
.tech-num { font-family:'SFMono-Regular',Consolas,monospace; font-weight:700; }
.tech-label { font-size:11px; text-transform:uppercase; letter-spacing:.08em; color:#94a3b8; }
.tabela-trafego th, .tabela-trafego td { vertical-align: middle; }
</style>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-bar-chart-line me-1"></i> Histórico de Tráfego</h4>
        <small class="text-muted">Consumo de download/upload por dia e interface, a partir do histórico coletado no servidor.</small>
    </div>
    <a href="<?= url('/infraestrutura/rede/trafego') ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-speedometer"></i> Tráfego ao vivo
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="tech-card p-3">
            <div class="tech-label mb-1">Período</div>
            <div class="tech-num" style="font-size:22px">Últimos <?= (int)$dias ?> dias</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="tech-card p-3">
            <div class="tech-label mb-1"><i class="bi bi-arrow-down-circle me-1"></i> Total baixado</div>
            <div class="tech-num" style="font-size:22px; color:#22c55e"><?= formatBytesHistorico($totalDownload) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="tech-card p-3">
            <div class="tech-label mb-1"><i class="bi bi-arrow-up-circle me-1"></i> Total enviado</div>
            <div class="tech-num" style="font-size:22px; color:#06b6d4"><?= formatBytesHistorico($totalUpload) ?></div>
        </div>
    </div>
</div>

<?php if (empty($consumo)): ?>
    <div class="tech-card p-5 text-center text-muted">
        <i class="bi bi-hourglass-split display-6"></i>
        <p class="mt-2 mb-0">Ainda não há amostras suficientes para calcular o consumo diário.</p>
        <small>A coleta roda periodicamente (cron); volte em algumas horas.</small>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm tabela-trafego align-middle">
            <thead>
                <tr>
                    <th>Dia</th>
                    <th>Interface</th>
                    <th class="text-end">Download</th>
                    <th class="text-end">Upload</th>
                    <th class="text-end">Pacotes RX</th>
                    <th class="text-end">Pacotes TX</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($consumo as $dia): ?>
                    <?php foreach ($dia['interfaces'] as $i): ?>
                        <tr>
                            <td class="font-monospace"><?= htmlspecialchars($dia['dia']) ?></td>
                            <td><?= htmlspecialchars($i['nome']) ?></td>
                            <td class="text-end font-monospace" style="color:#22c55e"><?= formatBytesHistorico($i['download']) ?></td>
                            <td class="text-end font-monospace" style="color:#06b6d4"><?= formatBytesHistorico($i['upload']) ?></td>
                            <td class="text-end font-monospace"><?= number_format($i['rx_packets'], 0, ',', '.') ?></td>
                            <td class="text-end font-monospace"><?= number_format($i['tx_packets'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - Histórico de Tráfego';

require __DIR__ . '/../layouts/main.php';
