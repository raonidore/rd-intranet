<?php
ob_start();

use App\Components\Alert;

$host    = $info['host'];
$uptime  = $info['uptime'];
$cpu     = $info['cpu'];
$memoria = $info['memoria'];
$disco   = $info['disco'];
$carga   = $info['carga'];
$temp    = $info['temperatura'];
$rede    = $info['rede'];
$usuarios = $info['usuarios'];
$servicos = $info['servicos'];
$saude   = $info['saude'];

function corPercentual(float $pct): string
{
    return $pct >= 90 ? 'danger' : ($pct >= 75 ? 'warning' : 'success');
}

function corSaude(int $pct): string
{
    return $pct >= 90 ? 'success' : ($pct >= 70 ? 'warning' : 'danger');
}

$corSaudeAtual = corSaude($saude['percentual']);
$discoPrincipal = $disco['principal'];
$discoPct = (int)($discoPrincipal['percentual'] ?? 0);
?>

<style>
.server-card { border:0; border-radius:14px; box-shadow:0 4px 14px rgba(0,0,0,.06); }
.server-card .card-body { padding:18px 20px; }
.server-metric-label { font-size:12px; color:#9ca3af; text-transform:uppercase; font-weight:600; }
.server-metric-value { font-size:26px; font-weight:700; line-height:1.2; }
.saude-ring { width:96px; height:96px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:22px; font-weight:700; flex-shrink:0; }
.table th { font-size:11px; text-transform:uppercase; color:#6b7280; font-weight:600; }
.scroll-box { max-height:280px; overflow-y:auto; }
</style>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-hdd-rack me-1"></i> Servidor</h4>
        <span class="text-muted" style="font-size:13px">
            Atualizado em: <span id="gerado-em"><?= htmlspecialchars($info['gerado_em']) ?></span>
        </span>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <button class="btn btn-sm btn-outline-secondary" id="btn-pause">
            <i class="bi bi-pause-fill"></i> Pausar
        </button>
        <select class="form-select form-select-sm" id="refresh-interval" style="width:auto">
            <option value="5000">5s</option>
            <option value="10000" selected>10s</option>
            <option value="30000">30s</option>
            <option value="60000">60s</option>
        </select>
    </div>
</div>

<div class="card server-card mb-4">
    <div class="card-body d-flex flex-wrap gap-4 align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <div id="saude-ring" class="saude-ring text-white bg-<?= $corSaudeAtual ?>"><?= $saude['percentual'] ?>%</div>
            <div>
                <div class="server-metric-label">Saúde Geral</div>
                <ul class="mb-0 ps-3" id="saude-motivos" style="font-size:13px">
                    <?php foreach ($saude['motivos'] as $motivo): ?>
                        <li><?= htmlspecialchars($motivo) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <div class="text-md-end">
            <h5 class="mb-1"><?= htmlspecialchars($host['os']) ?></h5>
            <div class="text-muted"><i class="bi bi-hdd-rack me-1"></i><?= htmlspecialchars($host['hostname']) ?></div>
            <small class="text-muted">Kernel <?= htmlspecialchars($host['kernel']) ?> (<?= htmlspecialchars($host['arch']) ?>)</small>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card server-card h-100">
            <div class="card-body">
                <div class="server-metric-label">CPU</div>
                <div class="server-metric-value text-<?= corPercentual($cpu['percentual']) ?>" id="v-cpu"><?= $cpu['percentual'] ?>%</div>
                <small class="text-muted"><?= $cpu['nucleos'] ?> núcleo(s)</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card server-card h-100">
            <div class="card-body">
                <div class="server-metric-label">RAM</div>
                <div class="server-metric-value text-<?= corPercentual($memoria['percentual']) ?>" id="v-mem"><?= $memoria['percentual'] ?>%</div>
                <small class="text-muted" id="v-mem-fmt"><?= htmlspecialchars($memoria['usado_fmt']) ?> / <?= htmlspecialchars($memoria['total_fmt']) ?></small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card server-card h-100">
            <div class="card-body">
                <div class="server-metric-label">Disco (/)</div>
                <div class="server-metric-value text-<?= corPercentual($discoPct) ?>" id="v-disco"><?= $discoPct ?>%</div>
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
                <div class="server-metric-value text-<?= corPercentual($carga['percentual']) ?>" id="v-carga"><?= $carga['1min'] ?></div>
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

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card server-card h-100">
            <div class="card-header bg-white">
                <i class="bi bi-diagram-3 me-1"></i> Interfaces de Rede
            </div>
            <div class="card-body p-0">
                <div id="rede-interfaces-container">
                    <?= renderInterfaces($rede['interfaces']) ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card server-card h-100">
            <div class="card-header bg-white">
                <i class="bi bi-signpost-split me-1"></i> Rotas
            </div>
            <div class="card-body p-0 scroll-box">
                <div id="rede-rotas-container">
                    <?= renderRotas($rede['rotas']) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card server-card h-100">
            <div class="card-header bg-white">
                <i class="bi bi-hdd me-1"></i> Armazenamento
            </div>
            <div class="card-body p-0">
                <div id="discos-container">
                    <?= renderDiscos($disco['discos']) ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card server-card h-100">
            <div class="card-header bg-white">
                <i class="bi bi-person-check me-1"></i> Usuários Logados
                <span class="badge bg-secondary ms-1" id="badge-usuarios"><?= count($usuarios) ?></span>
            </div>
            <div class="card-body p-0 scroll-box">
                <div id="usuarios-container">
                    <?= renderUsuarios($usuarios) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card server-card mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span><i class="bi bi-gear-wide-connected me-1"></i> Serviços do Sistema</span>
        <div>
            <span class="badge bg-success" id="badge-rodando"><?= $servicos['rodando'] ?> em execução</span>
            <?php if ($servicos['falharam'] > 0): ?>
                <span class="badge bg-danger" id="badge-falhas"><?= $servicos['falharam'] ?> com falha</span>
            <?php endif; ?>
            <a href="<?= url('/infraestrutura/servicos') ?>" class="btn btn-sm btn-outline-primary ms-2">
                <i class="bi bi-hdd-network"></i> Serviços gerenciados
            </a>
        </div>
    </div>
    <div class="card-body p-0 scroll-box" id="servicos-container">
        <?= renderServicos($servicos) ?>
    </div>
</div>

<?php
function renderInterfaces(array $interfaces): string
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

function renderRotas(array $rotas): string
{
    if (empty($rotas)) {
        return '<div class="text-center text-muted py-4"><i class="bi bi-signpost-split display-6"></i><p class="mt-2">Nenhuma rota encontrada</p></div>';
    }

    $html = '<table class="table table-hover align-middle mb-0"><thead><tr><th>Destino</th><th>Via</th><th>Interface</th><th>Origem</th></tr></thead><tbody>';
    foreach ($rotas as $r) {
        $html .= '<tr><td>' . htmlspecialchars($r['destino']) . '</td>'
            . '<td>' . htmlspecialchars($r['via']) . '</td>'
            . '<td>' . htmlspecialchars($r['dev']) . '</td>'
            . '<td>' . htmlspecialchars($r['src']) . '</td></tr>';
    }
    return $html . '</tbody></table>';
}

function renderDiscos(array $discos): string
{
    if (empty($discos)) {
        return '<div class="text-center text-muted py-4"><i class="bi bi-hdd display-6"></i><p class="mt-2">Nenhum disco encontrado</p></div>';
    }

    $html = '<table class="table table-hover align-middle mb-0"><thead><tr><th>Dispositivo</th><th>Ponto</th><th>Usado</th><th>Total</th><th>%</th></tr></thead><tbody>';
    foreach ($discos as $d) {
        $cor = corPercentual($d['percentual']);
        $html .= '<tr><td><small>' . htmlspecialchars($d['dispositivo']) . '</small></td>'
            . '<td>' . htmlspecialchars($d['ponto']) . '</td>'
            . '<td>' . htmlspecialchars($d['usado']) . '</td>'
            . '<td>' . htmlspecialchars($d['total']) . '</td>'
            . '<td><span class="badge bg-' . $cor . '">' . $d['percentual'] . '%</span></td></tr>';
    }
    return $html . '</tbody></table>';
}

function renderUsuarios(array $usuarios): string
{
    if (empty($usuarios)) {
        return '<div class="text-center text-muted py-4"><i class="bi bi-person-check display-6"></i><p class="mt-2">Nenhum usuário logado</p></div>';
    }

    $html = '<table class="table table-hover align-middle mb-0"><thead><tr><th>Usuário</th><th>Terminal</th><th>Desde</th><th>Origem</th></tr></thead><tbody>';
    foreach ($usuarios as $u) {
        $html .= '<tr><td><i class="bi bi-person-circle me-1 text-primary"></i>' . htmlspecialchars($u['usuario']) . '</td>'
            . '<td>' . htmlspecialchars($u['terminal']) . '</td>'
            . '<td style="font-size:12px">' . htmlspecialchars($u['desde']) . '</td>'
            . '<td>' . htmlspecialchars($u['origem']) . '</td></tr>';
    }
    return $html . '</tbody></table>';
}

function renderServicos(array $servicos): string
{
    $html = '';

    if (!empty($servicos['falhas'])) {
        $html .= '<div class="p-3 pb-0"><strong class="text-danger">Falharam:</strong> ';
        $html .= htmlspecialchars(implode(', ', $servicos['falhas']));
        $html .= '</div>';
    }

    if (empty($servicos['lista'])) {
        return $html . '<div class="text-center text-muted py-4"><i class="bi bi-gear-wide-connected display-6"></i><p class="mt-2">Nenhum serviço em execução encontrado</p></div>';
    }

    $html .= '<table class="table table-hover align-middle mb-0"><thead><tr><th>Unidade</th><th>Descrição</th></tr></thead><tbody>';
    foreach ($servicos['lista'] as $s) {
        $html .= '<tr><td><code>' . htmlspecialchars($s['unidade']) . '</code></td><td>' . htmlspecialchars($s['descricao']) . '</td></tr>';
    }
    return $html . '</tbody></table>';
}
?>

<script>
(function () {
    const API_URL = '<?= url('/infraestrutura/servidor/api') ?>';

    let paused = false;
    let timer  = null;
    const btn = document.getElementById('btn-pause');
    const sel = document.getElementById('refresh-interval');

    function esc(str) {
        const d = document.createElement('div');
        d.textContent = (str === null || str === undefined || str === '') ? '-' : str;
        return d.innerHTML;
    }

    function corPct(pct) { return pct >= 90 ? 'danger' : (pct >= 75 ? 'warning' : 'success'); }
    function corSaude(pct) { return pct >= 90 ? 'success' : (pct >= 70 ? 'warning' : 'danger'); }

    function renderEmpty(icon, msg) {
        return '<div class="text-center text-muted py-4"><i class="bi bi-' + icon + ' display-6"></i><p class="mt-2">' + msg + '</p></div>';
    }

    function renderInterfaces(lista) {
        if (!lista.length) return renderEmpty('diagram-3', 'Nenhuma interface encontrada');
        const linhas = lista.map(function (i) {
            const estado = i.estado === 'up' ? '<span class="badge bg-success">Up</span>' : '<span class="badge bg-secondary">Down</span>';
            const enderecos = i.ipv4.concat(i.ipv6);
            const editarBtn = i.nome === 'lo' ? '' : '<a href="<?= url('/infraestrutura/servidor/rede/editar') ?>?interface=' + encodeURIComponent(i.nome) + '" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>';
            return '<tr><td><strong>' + esc(i.nome) + '</strong></td><td>' + estado + '</td><td>' +
                (enderecos.length ? esc(enderecos.join(', ')) : '-') + '</td><td><code>' + esc(i.mac) + '</code></td>' +
                '<td>' + esc(i.rx_fmt) + '</td><td>' + esc(i.tx_fmt) + '</td><td>' + editarBtn + '</td></tr>';
        }).join('');
        return '<table class="table table-hover align-middle mb-0"><thead><tr><th>Interface</th><th>Estado</th><th>Endereços</th><th>MAC</th><th><i class="bi bi-arrow-down"></i> Download</th><th><i class="bi bi-arrow-up"></i> Upload</th><th></th></tr></thead><tbody>' + linhas + '</tbody></table>';
    }

    function renderRotas(lista) {
        if (!lista.length) return renderEmpty('signpost-split', 'Nenhuma rota encontrada');
        const linhas = lista.map(function (r) {
            return '<tr><td>' + esc(r.destino) + '</td><td>' + esc(r.via) + '</td><td>' + esc(r.dev) + '</td><td>' + esc(r.src) + '</td></tr>';
        }).join('');
        return '<table class="table table-hover align-middle mb-0"><thead><tr><th>Destino</th><th>Via</th><th>Interface</th><th>Origem</th></tr></thead><tbody>' + linhas + '</tbody></table>';
    }

    function renderDiscos(lista) {
        if (!lista.length) return renderEmpty('hdd', 'Nenhum disco encontrado');
        const linhas = lista.map(function (d) {
            return '<tr><td><small>' + esc(d.dispositivo) + '</small></td><td>' + esc(d.ponto) + '</td><td>' + esc(d.usado) + '</td><td>' + esc(d.total) + '</td>' +
                '<td><span class="badge bg-' + corPct(d.percentual) + '">' + d.percentual + '%</span></td></tr>';
        }).join('');
        return '<table class="table table-hover align-middle mb-0"><thead><tr><th>Dispositivo</th><th>Ponto</th><th>Usado</th><th>Total</th><th>%</th></tr></thead><tbody>' + linhas + '</tbody></table>';
    }

    function renderUsuarios(lista) {
        if (!lista.length) return renderEmpty('person-check', 'Nenhum usuário logado');
        const linhas = lista.map(function (u) {
            return '<tr><td><i class="bi bi-person-circle me-1 text-primary"></i>' + esc(u.usuario) + '</td><td>' + esc(u.terminal) + '</td>' +
                '<td style="font-size:12px">' + esc(u.desde) + '</td><td>' + esc(u.origem) + '</td></tr>';
        }).join('');
        return '<table class="table table-hover align-middle mb-0"><thead><tr><th>Usuário</th><th>Terminal</th><th>Desde</th><th>Origem</th></tr></thead><tbody>' + linhas + '</tbody></table>';
    }

    function renderServicos(servicos) {
        let html = '';
        if (servicos.falhas && servicos.falhas.length) {
            html += '<div class="p-3 pb-0"><strong class="text-danger">Falharam:</strong> ' + esc(servicos.falhas.join(', ')) + '</div>';
        }
        if (!servicos.lista.length) {
            return html + renderEmpty('gear-wide-connected', 'Nenhum serviço em execução encontrado');
        }
        const linhas = servicos.lista.map(function (s) {
            return '<tr><td><code>' + esc(s.unidade) + '</code></td><td>' + esc(s.descricao) + '</td></tr>';
        }).join('');
        return html + '<table class="table table-hover align-middle mb-0"><thead><tr><th>Unidade</th><th>Descrição</th></tr></thead><tbody>' + linhas + '</tbody></table>';
    }

    function atualizarCard(id, valor, cor) {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = valor;
        if (cor) el.className = el.className.replace(/text-(success|warning|danger)/, 'text-' + cor);
    }

    async function refresh() {
        try {
            const res  = await fetch(API_URL);
            const data = await res.json();

            document.getElementById('gerado-em').textContent = data.gerado_em;

            atualizarCard('v-cpu', data.cpu.percentual + '%', corPct(data.cpu.percentual));
            atualizarCard('v-mem', data.memoria.percentual + '%', corPct(data.memoria.percentual));
            document.getElementById('v-mem-fmt').textContent = data.memoria.usado_fmt + ' / ' + data.memoria.total_fmt;

            const discoPrincipal = data.disco.principal;
            atualizarCard('v-disco', discoPrincipal.percentual + '%', corPct(discoPrincipal.percentual));
            document.getElementById('v-disco-fmt').textContent = discoPrincipal.usado + ' / ' + discoPrincipal.total;

            document.getElementById('v-uptime').textContent = data.uptime.texto;

            atualizarCard('v-carga', data.carga['1min'], corPct(data.carga.percentual));
            document.getElementById('v-carga-fmt').textContent = '5m: ' + data.carga['5min'] + ' | 15m: ' + data.carga['15min'];

            if (data.temperatura && data.temperatura.length) {
                const max = Math.max.apply(null, data.temperatura.map(function (t) { return t.celsius; }));
                document.getElementById('v-temp').textContent = max + '°C';
            }

            const ring = document.getElementById('saude-ring');
            ring.textContent = data.saude.percentual + '%';
            ring.className = 'saude-ring text-white bg-' + corSaude(data.saude.percentual);
            document.getElementById('saude-motivos').innerHTML = data.saude.motivos.map(function (m) { return '<li>' + esc(m) + '</li>'; }).join('');

            document.getElementById('rede-interfaces-container').innerHTML = renderInterfaces(data.rede.interfaces);
            document.getElementById('rede-rotas-container').innerHTML = renderRotas(data.rede.rotas);
            document.getElementById('discos-container').innerHTML = renderDiscos(data.disco.discos);
            document.getElementById('usuarios-container').innerHTML = renderUsuarios(data.usuarios);
            document.getElementById('badge-usuarios').textContent = data.usuarios.length;
            document.getElementById('servicos-container').innerHTML = renderServicos(data.servicos);
            document.getElementById('badge-rodando').textContent = data.servicos.rodando + ' em execução';

            const badgeFalhas = document.getElementById('badge-falhas');
            if (data.servicos.falharam > 0 && !badgeFalhas) {
                document.getElementById('badge-rodando').insertAdjacentHTML('afterend', '<span class="badge bg-danger" id="badge-falhas">' + data.servicos.falharam + ' com falha</span>');
            } else if (badgeFalhas) {
                if (data.servicos.falharam > 0) {
                    badgeFalhas.textContent = data.servicos.falharam + ' com falha';
                } else {
                    badgeFalhas.remove();
                }
            }
        } catch (e) {
            console.warn('Falha ao atualizar informações do servidor:', e);
        }
    }

    function scheduleNext() {
        if (!paused) {
            timer = setTimeout(async function () { await refresh(); scheduleNext(); }, parseInt(sel.value));
        }
    }

    btn.addEventListener('click', function () {
        paused = !paused;
        if (paused) {
            clearTimeout(timer);
            btn.innerHTML = '<i class="bi bi-play-fill"></i> Retomar';
            btn.classList.replace('btn-outline-secondary', 'btn-outline-success');
        } else {
            btn.innerHTML = '<i class="bi bi-pause-fill"></i> Pausar';
            btn.classList.replace('btn-outline-success', 'btn-outline-secondary');
            scheduleNext();
        }
    });

    sel.addEventListener('change', function () { clearTimeout(timer); if (!paused) scheduleNext(); });

    scheduleNext();
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - Servidor';

require __DIR__ . '/../layouts/main.php';
