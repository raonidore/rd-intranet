<?php
ob_start();

use App\Components\Alert;

$host = $info['host'];
$uptime = $info['uptime'];
$cpu = $info['cpu'];
$memoria = $info['memoria'];
$disco = $info['disco'];
$carga = $info['carga'];
$temp = $info['temperatura'];

function corPercentualHw(float $pct): string
{
    return $pct >= 90 ? 'danger' : ($pct >= 75 ? 'warning' : 'success');
}

$discoPrincipal = $disco['principal'];
$discoPct = (int)($discoPrincipal['percentual'] ?? 0);
?>

<style>
.server-card { border:0; border-radius:14px; box-shadow:0 4px 14px rgba(0,0,0,.06); }
.server-card .card-body { padding:18px 20px; }
.server-metric-label { font-size:12px; color:#9ca3af; text-transform:uppercase; font-weight:600; }
.server-metric-value { font-size:26px; font-weight:700; line-height:1.2; }
.scroll-box { max-height:320px; overflow-y:auto; }
</style>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-cpu me-1"></i> Hardware</h4>
        <span class="text-muted" style="font-size:13px">
            <?= htmlspecialchars($host['hostname']) ?> &mdash; <?= htmlspecialchars($host['os']) ?>
            &mdash; Atualizado em: <span id="gerado-em"><?= htmlspecialchars($info['gerado_em']) ?></span>
        </span>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <a href="<?= url('/infraestrutura/servidor') ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Servidor
        </a>
        <button class="btn btn-sm btn-outline-secondary" id="btn-pause">
            <i class="bi bi-pause-fill"></i> Pausar
        </button>
        <select class="form-select form-select-sm" id="refresh-interval" style="width:auto">
            <option value="5000">5s</option>
            <option value="10000" selected>10s</option>
            <option value="30000">30s</option>
        </select>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card server-card h-100">
            <div class="card-body">
                <div class="server-metric-label">CPU</div>
                <div class="server-metric-value text-<?= corPercentualHw($cpu['percentual']) ?>" id="v-cpu"><?= $cpu['percentual'] ?>%</div>
                <small class="text-muted"><?= $cpu['nucleos'] ?> núcleo(s)</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card server-card h-100">
            <div class="card-body">
                <div class="server-metric-label">RAM</div>
                <div class="server-metric-value text-<?= corPercentualHw($memoria['percentual']) ?>" id="v-mem"><?= $memoria['percentual'] ?>%</div>
                <small class="text-muted" id="v-mem-fmt"><?= htmlspecialchars($memoria['usado_fmt']) ?> / <?= htmlspecialchars($memoria['total_fmt']) ?></small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card server-card h-100">
            <div class="card-body">
                <div class="server-metric-label">Disco (/)</div>
                <div class="server-metric-value text-<?= corPercentualHw($discoPct) ?>" id="v-disco"><?= $discoPct ?>%</div>
                <small class="text-muted" id="v-disco-fmt"><?= htmlspecialchars($discoPrincipal['usado']) ?> / <?= htmlspecialchars($discoPrincipal['total']) ?></small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card server-card h-100">
            <div class="card-body">
                <div class="server-metric-label">Uptime</div>
                <div class="server-metric-value" id="v-uptime" style="font-size:22px"><?= htmlspecialchars($uptime['texto']) ?></div>
                <small class="text-muted">desde a última reinicialização</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card server-card h-100">
            <div class="card-body">
                <div class="server-metric-label">Carga</div>
                <div class="server-metric-value text-<?= corPercentualHw($carga['percentual']) ?>" id="v-carga"><?= $carga['1min'] ?></div>
                <small class="text-muted" id="v-carga-fmt">5m: <?= $carga['5min'] ?> | 15m: <?= $carga['15min'] ?></small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card server-card h-100">
            <div class="card-body">
                <div class="server-metric-label">Temperatura</div>
                <?php if ($temp): ?>
                    <div class="server-metric-value" id="v-temp"><?= max(array_column($temp, 'celsius')) ?>°C</div>
                    <small class="text-muted">máxima entre <?= count($temp) ?> sensor(es)</small>
                <?php else: ?>
                    <div class="server-metric-value text-muted" id="v-temp" style="font-size:18px">N/D</div>
                    <small class="text-muted">sem sensores disponíveis</small>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card server-card mb-4">
    <div class="card-header bg-white">
        <i class="bi bi-hdd me-1"></i> Armazenamento
    </div>
    <div class="card-body p-0 scroll-box">
        <div id="discos-container">
            <?= renderDiscosHw($disco['discos']) ?>
        </div>
    </div>
</div>

<?php
function renderDiscosHw(array $discos): string
{
    if (empty($discos)) {
        return '<div class="text-center text-muted py-4"><i class="bi bi-hdd display-6"></i><p class="mt-2">Nenhum disco encontrado</p></div>';
    }

    $html = '<table class="table table-hover align-middle mb-0"><thead><tr><th>Dispositivo</th><th>Ponto</th><th>Usado</th><th>Total</th><th>%</th></tr></thead><tbody>';
    foreach ($discos as $d) {
        $cor = corPercentualHw($d['percentual']);
        $html .= '<tr><td><small>' . htmlspecialchars($d['dispositivo']) . '</small></td>'
            . '<td>' . htmlspecialchars($d['ponto']) . '</td>'
            . '<td>' . htmlspecialchars($d['usado']) . '</td>'
            . '<td>' . htmlspecialchars($d['total']) . '</td>'
            . '<td><span class="badge bg-' . $cor . '">' . $d['percentual'] . '%</span></td></tr>';
    }
    return $html . '</tbody></table>';
}
?>

<script>
(function () {
    const API_URL = '<?= url('/infraestrutura/hardware/api') ?>';

    let paused = false;
    let timer = null;
    const btn = document.getElementById('btn-pause');
    const sel = document.getElementById('refresh-interval');

    function esc(str) {
        const d = document.createElement('div');
        d.textContent = (str === null || str === undefined || str === '') ? '-' : str;
        return d.innerHTML;
    }

    function corPct(pct) { return pct >= 90 ? 'danger' : (pct >= 75 ? 'warning' : 'success'); }

    function renderDiscos(lista) {
        if (!lista.length) return '<div class="text-center text-muted py-4"><i class="bi bi-hdd display-6"></i><p class="mt-2">Nenhum disco encontrado</p></div>';
        const linhas = lista.map(function (d) {
            return '<tr><td><small>' + esc(d.dispositivo) + '</small></td><td>' + esc(d.ponto) + '</td><td>' + esc(d.usado) + '</td><td>' + esc(d.total) + '</td>' +
                '<td><span class="badge bg-' + corPct(d.percentual) + '">' + d.percentual + '%</span></td></tr>';
        }).join('');
        return '<table class="table table-hover align-middle mb-0"><thead><tr><th>Dispositivo</th><th>Ponto</th><th>Usado</th><th>Total</th><th>%</th></tr></thead><tbody>' + linhas + '</tbody></table>';
    }

    function atualizarCard(id, valor, cor) {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = valor;
        if (cor) el.className = el.className.replace(/text-(success|warning|danger)/, 'text-' + cor);
    }

    async function refresh() {
        try {
            const res = await fetch(API_URL);
            const d = await res.json();

            document.getElementById('gerado-em').textContent = d.gerado_em;
            atualizarCard('v-cpu', d.cpu.percentual + '%', corPct(d.cpu.percentual));
            atualizarCard('v-mem', d.memoria.percentual + '%', corPct(d.memoria.percentual));
            document.getElementById('v-mem-fmt').textContent = d.memoria.usado_fmt + ' / ' + d.memoria.total_fmt;
            const discoPct = (d.disco.principal && d.disco.principal.percentual) || 0;
            atualizarCard('v-disco', discoPct + '%', corPct(discoPct));
            if (d.disco.principal) {
                document.getElementById('v-disco-fmt').textContent = d.disco.principal.usado + ' / ' + d.disco.principal.total;
            }
            atualizarCard('v-uptime', d.uptime.texto);
            atualizarCard('v-carga', d.carga['1min'], corPct(d.carga.percentual));
            document.getElementById('v-carga-fmt').textContent = '5m: ' + d.carga['5min'] + ' | 15m: ' + d.carga['15min'];
            if (d.temperatura && d.temperatura.length) {
                const maxT = Math.max.apply(null, d.temperatura.map(function (t) { return t.celsius; }));
                atualizarCard('v-temp', maxT + '°C');
            }
            document.getElementById('discos-container').innerHTML = renderDiscos(d.disco.discos);
        } catch (e) {
            console.warn('Falha ao atualizar hardware:', e);
        }
    }

    function agendar() {
        if (timer) clearInterval(timer);
        if (!paused) timer = setInterval(refresh, parseInt(sel.value, 10));
    }

    btn.addEventListener('click', function () {
        paused = !paused;
        btn.innerHTML = paused ? '<i class="bi bi-play-fill"></i> Retomar' : '<i class="bi bi-pause-fill"></i> Pausar';
        agendar();
    });
    sel.addEventListener('change', agendar);

    agendar();
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - Hardware';

require __DIR__ . '/../layouts/main.php';
