<?php
ob_start();

$sessions     = $monitor['sessions'];
$sharesAtivos = $monitor['shares_ativos'];
$openFiles    = $monitor['open_files'];
$locks        = $monitor['locks'];
$perf         = $monitor['performance'];

$diskPct   = (int)($perf['disk_percent'] ?? 0);
$diskColor = $diskPct >= 90 ? 'danger' : ($diskPct >= 70 ? 'warning' : 'success');
$cpuColor  = ($perf['cpu_percent'] ?? 0) >= 80 ? 'danger' : (($perf['cpu_percent'] ?? 0) >= 50 ? 'warning' : 'success');

$legendaAcesso = 'R = Leitura | W = Escrita | RW = Leitura e Escrita | (vazio) = Somente metadados/atributos';
$legendaModo   = 'Compartilhamento com outros processos enquanto o arquivo esta aberto: R = Permite leitura | W = Permite escrita | D = Permite exclusao | RWD = Acesso total compartilhado';
?>

<style>
.monitor-card { border:0; border-radius:14px; box-shadow:0 4px 14px rgba(0,0,0,.06); }
.live-badge { display:inline-flex; align-items:center; gap:6px; background:#dcfce7; color:#15803d; border-radius:20px; padding:3px 10px; font-size:12px; font-weight:600; }
.live-dot { width:8px; height:8px; background:#22c55e; border-radius:50%; animation:pulse-green 1.4s infinite; }
@keyframes pulse-green { 0%,100%{opacity:1} 50%{opacity:.3} }
.table th { font-size:11px; text-transform:uppercase; color:#6b7280; font-weight:600; }
.badge-protocol { background:#e0e7ff; color:#3730a3; font-size:11px; }
.badge-access   { background:#fef9c3; color:#854d0e; font-size:11px; }
.badge-mode     { background:#e0f2fe; color:#0369a1; font-size:11px; }
.legend-box     { background:#f8fafc; border-radius:8px; padding:10px 14px; font-size:12px; color:#6b7280; margin-bottom:12px; }
.toast-container { position:fixed; top:20px; right:20px; z-index:9999; }
</style>

<div class="toast-container">
    <div id="action-toast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="toast-msg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Monitor em Tempo Real</h4>
        <span class="text-muted" style="font-size:13px">
            Atualizado em: <span id="last-update"><?= htmlspecialchars($monitor['generated_at']) ?></span>
        </span>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <span class="live-badge"><span class="live-dot"></span> Ao vivo</span>
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

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="monitor-card card">
            <div class="card-body">
                <div class="text-muted mb-1" style="font-size:12px">SESSOES ATIVAS</div>
                <h2 class="mb-0" id="p-sessions"><?= count($sessions) ?></h2>
                <small class="text-muted">usuarios conectados</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="monitor-card card">
            <div class="card-body">
                <div class="text-muted mb-1" style="font-size:12px">ARQUIVOS ABERTOS</div>
                <h2 class="mb-0" id="p-files"><?= count($openFiles) ?></h2>
                <small class="text-muted">handles ativos</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="monitor-card card">
            <div class="card-body">
                <div class="text-muted mb-1" style="font-size:12px">
                    DISCO <span id="p-disk-pct" class="badge bg-<?= $diskColor ?> ms-1"><?= $diskPct ?>%</span>
                </div>
                <div class="d-flex gap-3">
                    <div><strong id="p-disk-used"><?= htmlspecialchars($perf['disk_used']) ?></strong> <small class="text-muted">usado</small></div>
                    <div><strong id="p-disk-avail"><?= htmlspecialchars($perf['disk_avail']) ?></strong> <small class="text-muted">livre</small></div>
                </div>
                <div class="progress mt-2" style="height:6px">
                    <div class="progress-bar bg-<?= $diskColor ?>" id="p-disk-bar" style="width:<?= $diskPct ?>%"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="monitor-card card">
            <div class="card-body">
                <div class="text-muted mb-1" style="font-size:12px">
                    CPU SMBD <span id="p-cpu-badge" class="badge bg-<?= $cpuColor ?> ms-1"><?= $perf['cpu_percent'] ?>%</span>
                </div>
                <h2 class="mb-0 d-flex align-items-end gap-2">
                    <span id="p-mem"><?= $perf['mem_percent'] ?>%</span>
                    <small class="text-muted" style="font-size:13px">mem</small>
                </h2>
                <small class="text-muted" id="p-procs"><?= $perf['num_procs'] ?> processo(s) smbd</small>
            </div>
        </div>
    </div>
</div>

<div class="monitor-card card">
    <div class="card-header bg-white">
        <ul class="nav nav-tabs card-header-tabs" id="monitorTabs">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#tab-sessions">
                    <i class="bi bi-people me-1"></i> Usuarios
                    <span class="badge bg-primary ms-1" id="badge-sessions"><?= count($sessions) ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tab-shares">
                    <i class="bi bi-folder2-open me-1"></i> Compartilhamentos
                    <span class="badge bg-secondary ms-1" id="badge-shares"><?= count($sharesAtivos) ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tab-files">
                    <i class="bi bi-file-earmark me-1"></i> Arquivos Abertos
                    <span class="badge bg-secondary ms-1" id="badge-files"><?= count($openFiles) ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tab-locks">
                    <i class="bi bi-lock me-1"></i> Locks
                    <span class="badge bg-secondary ms-1" id="badge-locks"><?= count($locks) ?></span>
                </a>
            </li>
        </ul>
    </div>

    <div class="card-body">
        <div class="tab-content">

            <div class="tab-pane fade show active" id="tab-sessions">
                <div id="sessions-container">
                    <?php if (empty($sessions)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-people display-6"></i><p class="mt-2">Nenhum usuario conectado</p>
                        </div>
                    <?php else: ?>
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Usuario</th><th>IP / Maquina</th><th>PID</th><th>Protocolo</th><th>Criptografia</th><th>Assinatura</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($sessions as $s): ?>
                            <tr>
                                <td><i class="bi bi-person-circle me-1 text-primary"></i><?= htmlspecialchars($s['username']) ?></td>
                                <td><?= htmlspecialchars($s['machine']) ?></td>
                                <td><code><?= htmlspecialchars($s['pid']) ?></code></td>
                                <td><span class="badge badge-protocol"><?= htmlspecialchars($s['protocol']) ?></span></td>
                                <td><?= htmlspecialchars($s['encryption']) ?></td>
                                <td><?= htmlspecialchars($s['signing']) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-danger btn-kick"
                                        data-pid="<?= htmlspecialchars($s['pid']) ?>"
                                        data-user="<?= htmlspecialchars($s['username']) ?>">
                                        <i class="bi bi-x-circle me-1"></i>Derrubar
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-shares">
                <div id="shares-container">
                    <?php if (empty($sharesAtivos)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-folder2 display-6"></i><p class="mt-2">Nenhum compartilhamento ativo</p>
                        </div>
                    <?php else: ?>
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Compartilhamento</th><th>Usuario</th><th>IP / Maquina</th><th>Conectado em</th><th>Criptografia</th></tr></thead>
                        <tbody>
                            <?php foreach ($sharesAtivos as $sh): ?>
                            <tr>
                                <td><i class="bi bi-folder-fill me-1 text-warning"></i><?= htmlspecialchars($sh['service']) ?></td>
                                <td><?= htmlspecialchars($sh['username']) ?></td>
                                <td><?= htmlspecialchars($sh['machine']) ?></td>
                                <td><?= htmlspecialchars($sh['connected_at']) ?></td>
                                <td><?= htmlspecialchars($sh['encryption']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-files">
                <div class="legend-box">
                    <strong>Acesso:</strong> <?= $legendaAcesso ?><br>
                    <strong>Modo:</strong> <?= $legendaModo ?>
                </div>
                <div id="files-container">
                    <?php if (empty($openFiles)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-file-earmark display-6"></i><p class="mt-2">Nenhum arquivo aberto</p>
                        </div>
                    <?php else: ?>
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Arquivo</th><th>Compartilhamento</th><th>Usuario</th><th>IP</th><th>PID</th><th>Acesso</th><th>Modo</th><th>Oplock</th><th>Aberto em</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($openFiles as $f): ?>
                            <tr>
                                <td><i class="bi bi-file-earmark me-1 text-secondary"></i><?= htmlspecialchars($f['filename']) ?></td>
                                <td><small class="text-muted"><?= htmlspecialchars(basename($f['share_path'])) ?></small></td>
                                <td><?= htmlspecialchars($f['username']) ?></td>
                                <td><?= htmlspecialchars($f['machine']) ?></td>
                                <td><code><?= htmlspecialchars($f['pid']) ?></code></td>
                                <td>
                                    <?php if ($f['access'] !== ''): ?>
                                        <span class="badge badge-access"><?= htmlspecialchars($f['access']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size:11px">metadados</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge badge-mode"><?= htmlspecialchars($f['sharemode']) ?></span></td>
                                <td><?= $f['oplock'] === 'Sim' ? '<span class="badge bg-warning text-dark">Sim</span>' : '<span class="text-muted">Nao</span>' ?></td>
                                <td style="font-size:11px;white-space:nowrap"><?= htmlspecialchars($f['opened_at']) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-danger btn-close-file"
                                        data-pid="<?= htmlspecialchars($f['pid']) ?>"
                                        data-file="<?= htmlspecialchars($f['filename']) ?>">
                                        <i class="bi bi-x-circle me-1"></i>Fechar
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-locks">
                <div class="legend-box">
                    <strong>Acesso:</strong> <?= $legendaAcesso ?><br>
                    <strong>Modo:</strong> <?= $legendaModo ?>
                </div>
                <div id="locks-container">
                    <?php if (empty($locks)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-unlock display-6"></i><p class="mt-2">Nenhum lock ativo</p>
                        </div>
                    <?php else: ?>
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Arquivo</th><th>Compartilhamento</th><th>Usuario</th><th>IP</th><th>PID</th><th>Modo</th><th>Acesso</th><th>Oplock</th><th>Desde</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($locks as $l): ?>
                            <tr>
                                <td>
                                    <?= $l['locked'] ? '<i class="bi bi-lock-fill me-1 text-danger"></i>' : '<i class="bi bi-unlock me-1 text-muted"></i>' ?>
                                    <?= htmlspecialchars($l['filename'] === '.' ? '(raiz)' : $l['filename']) ?>
                                </td>
                                <td><small class="text-muted"><?= htmlspecialchars(basename($l['path'])) ?></small></td>
                                <td><?= htmlspecialchars($l['username']) ?></td>
                                <td><?= htmlspecialchars($l['machine']) ?></td>
                                <td><code><?= htmlspecialchars($l['pid']) ?></code></td>
                                <td><span class="badge badge-mode"><?= htmlspecialchars($l['denymode']) ?></span></td>
                                <td>
                                    <?php if ($l['rw'] !== ''): ?>
                                        <span class="badge badge-access"><?= htmlspecialchars($l['rw']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size:11px">metadados</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $l['oplock'] === 'Sim' ? '<span class="badge bg-warning text-dark">Sim</span>' : '<span class="text-muted">Nao</span>' ?></td>
                                <td style="font-size:11px;white-space:nowrap"><?= htmlspecialchars($l['opened_at']) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-warning btn-close-file"
                                        data-pid="<?= htmlspecialchars($l['pid']) ?>"
                                        data-file="<?= htmlspecialchars($l['filename'] === '.' ? '(raiz)' : $l['filename']) ?>">
                                        <i class="bi bi-unlock me-1"></i>Desbloquear
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
(function () {
    const KICK_URL = '<?= url('/samba/monitor/encerrar') ?>';
    const API_URL  = '<?= url('/samba/monitor/api') ?>';

    let paused = false;
    let timer  = null;
    const btn = document.getElementById('btn-pause');
    const sel = document.getElementById('refresh-interval');

    function esc(str) {
        const d = document.createElement('div');
        d.textContent = (str == null || str === '') ? '-' : str;
        return d.innerHTML;
    }
    function badge(cls, text) { return '<span class="badge ' + cls + '">' + esc(text) + '</span>'; }
    function renderEmpty(icon, msg) {
        return '<div class="text-center text-muted py-4"><i class="bi bi-' + icon + ' display-6"></i><p class="mt-2">' + msg + '</p></div>';
    }
    function renderAccess(val) {
        if (!val || val === '-') return '<span class="text-muted" style="font-size:11px">metadados</span>';
        return badge('badge-access', val);
    }
    const legendaHtml =
        '<div class="legend-box"><strong>Acesso:</strong> R = Leitura | W = Escrita | RW = Leitura e Escrita | (vazio) = Somente metadados/atributos<br>' +
        '<strong>Modo:</strong> Compartilhamento com outros processos: R = Permite leitura | W = Permite escrita | D = Permite exclusao | RWD = Acesso total</div>';

    function renderSessions(data) {
        if (!data.length) return renderEmpty('people', 'Nenhum usuario conectado');
        const rows = data.map(function(s) {
            return '<tr><td><i class="bi bi-person-circle me-1 text-primary"></i>' + esc(s.username) + '</td>' +
                '<td>' + esc(s.machine) + '</td>' +
                '<td><code>' + esc(s.pid) + '</code></td>' +
                '<td>' + badge('badge-protocol', s.protocol) + '</td>' +
                '<td>' + esc(s.encryption) + '</td>' +
                '<td>' + esc(s.signing) + '</td>' +
                '<td><button class="btn btn-sm btn-outline-danger btn-kick" data-pid="' + esc(s.pid) + '" data-user="' + esc(s.username) + '"><i class="bi bi-x-circle me-1"></i>Derrubar</button></td></tr>';
        }).join('');
        return '<table class="table table-hover align-middle mb-0"><thead><tr><th>Usuario</th><th>IP / Maquina</th><th>PID</th><th>Protocolo</th><th>Criptografia</th><th>Assinatura</th><th></th></tr></thead><tbody>' + rows + '</tbody></table>';
    }

    function renderShares(data) {
        if (!data.length) return renderEmpty('folder2', 'Nenhum compartilhamento ativo');
        const rows = data.map(function(s) {
            return '<tr><td><i class="bi bi-folder-fill me-1 text-warning"></i>' + esc(s.service) + '</td>' +
                '<td>' + esc(s.username) + '</td><td>' + esc(s.machine) + '</td>' +
                '<td style="font-size:12px">' + esc(s.connected_at) + '</td>' +
                '<td>' + esc(s.encryption) + '</td></tr>';
        }).join('');
        return '<table class="table table-hover align-middle mb-0"><thead><tr><th>Compartilhamento</th><th>Usuario</th><th>IP / Maquina</th><th>Conectado em</th><th>Criptografia</th></tr></thead><tbody>' + rows + '</tbody></table>';
    }

    function renderFiles(data) {
        if (!data.length) return legendaHtml + renderEmpty('file-earmark', 'Nenhum arquivo aberto');
        const rows = data.map(function(f) {
            var fn = (f.filename === '.' || f.filename === '') ? '(raiz do compartilhamento)' : f.filename;
            var share = f.share_path ? f.share_path.split('/').pop() : '-';
            return '<tr><td><i class="bi bi-file-earmark me-1 text-secondary"></i>' + esc(fn) + '</td>' +
                '<td><small class="text-muted">' + esc(share) + '</small></td>' +
                '<td>' + esc(f.username) + '</td><td>' + esc(f.machine) + '</td>' +
                '<td><code>' + esc(f.pid) + '</code></td>' +
                '<td>' + renderAccess(f.access) + '</td>' +
                '<td>' + badge('badge-mode', f.sharemode) + '</td>' +
                '<td>' + (f.oplock === 'Sim' ? badge('bg-warning text-dark', 'Sim') : '<span class="text-muted">Nao</span>') + '</td>' +
                '<td style="font-size:11px;white-space:nowrap">' + esc(f.opened_at) + '</td>' +
                '<td><button class="btn btn-sm btn-outline-danger btn-close-file" data-pid="' + esc(f.pid) + '" data-file="' + esc(fn) + '"><i class="bi bi-x-circle me-1"></i>Fechar</button></td></tr>';
        }).join('');
        return legendaHtml + '<table class="table table-hover align-middle mb-0"><thead><tr><th>Arquivo</th><th>Compartilhamento</th><th>Usuario</th><th>IP</th><th>PID</th><th>Acesso</th><th>Modo</th><th>Oplock</th><th>Aberto em</th><th></th></tr></thead><tbody>' + rows + '</tbody></table>';
    }

    function renderLocks(data) {
        if (!data.length) return legendaHtml + renderEmpty('unlock', 'Nenhum lock ativo');
        const rows = data.map(function(l) {
            var fn = (l.filename === '.' || l.filename === '') ? '(raiz)' : l.filename;
            var share = l.path ? l.path.split('/').pop() : '-';
            var lockIcon = l.locked ? '<i class="bi bi-lock-fill me-1 text-danger"></i>' : '<i class="bi bi-unlock me-1 text-muted"></i>';
            return '<tr><td>' + lockIcon + esc(fn) + '</td>' +
                '<td><small class="text-muted">' + esc(share) + '</small></td>' +
                '<td>' + esc(l.username) + '</td><td>' + esc(l.machine) + '</td>' +
                '<td><code>' + esc(l.pid) + '</code></td>' +
                '<td>' + badge('badge-mode', l.denymode) + '</td>' +
                '<td>' + renderAccess(l.rw) + '</td>' +
                '<td>' + (l.oplock === 'Sim' ? badge('bg-warning text-dark', 'Sim') : '<span class="text-muted">Nao</span>') + '</td>' +
                '<td style="font-size:11px;white-space:nowrap">' + esc(l.opened_at) + '</td>' +
                '<td><button class="btn btn-sm btn-outline-warning btn-close-file" data-pid="' + esc(l.pid) + '" data-file="' + esc(fn) + '"><i class="bi bi-unlock me-1"></i>Desbloquear</button></td></tr>';
        }).join('');
        return legendaHtml + '<table class="table table-hover align-middle mb-0"><thead><tr><th>Arquivo</th><th>Compartilhamento</th><th>Usuario</th><th>IP</th><th>PID</th><th>Modo</th><th>Acesso</th><th>Oplock</th><th>Desde</th><th></th></tr></thead><tbody>' + rows + '</tbody></table>';
    }

    function updatePerf(p) {
        var diskPct  = parseInt(p.disk_percent) || 0;
        var diskColor = diskPct >= 90 ? 'danger' : (diskPct >= 70 ? 'warning' : 'success');
        var cpuColor  = p.cpu_percent >= 80 ? 'danger' : (p.cpu_percent >= 50 ? 'warning' : 'success');
        document.getElementById('p-disk-pct').className   = 'badge bg-' + diskColor + ' ms-1';
        document.getElementById('p-disk-pct').textContent = diskPct + '%';
        document.getElementById('p-disk-bar').className   = 'progress-bar bg-' + diskColor;
        document.getElementById('p-disk-bar').style.width = diskPct + '%';
        document.getElementById('p-disk-used').textContent  = p.disk_used;
        document.getElementById('p-disk-avail').textContent = p.disk_avail;
        document.getElementById('p-cpu-badge').className    = 'badge bg-' + cpuColor + ' ms-1';
        document.getElementById('p-cpu-badge').textContent  = p.cpu_percent + '%';
        document.getElementById('p-mem').textContent        = p.mem_percent + '%';
        document.getElementById('p-procs').textContent      = p.num_procs + ' processo(s) smbd';
    }

    function showToast(msg, ok) {
        var el = document.getElementById('action-toast');
        el.className = 'toast align-items-center text-white border-0 bg-' + (ok ? 'success' : 'danger');
        document.getElementById('toast-msg').textContent = msg;
        bootstrap.Toast.getOrCreateInstance(el, { delay: 4000 }).show();
    }

    async function encerrar(pid, descricao) {
        var msg = 'Confirma encerrar a sessao de "' + descricao + '" (PID ' + pid + ')?\n\nIsso desconectara o usuario e fechara todos os arquivos abertos desta sessao.';
        if (!confirm(msg)) return;
        try {
            var fd = new FormData();
            fd.append('pid', pid);
            var res  = await fetch(KICK_URL, { method: 'POST', body: fd });
            var data = await res.json();
            showToast(data.message, data.success);
            if (data.success) await refresh();
        } catch(e) {
            showToast('Erro ao comunicar com o servidor.', false);
        }
    }

    document.addEventListener('click', function(e) {
        var kick = e.target.closest('.btn-kick');
        if (kick) { encerrar(kick.dataset.pid, kick.dataset.user); return; }
        var cf = e.target.closest('.btn-close-file');
        if (cf) { encerrar(cf.dataset.pid, cf.dataset.file); }
    });

    async function refresh() {
        try {
            var res  = await fetch(API_URL);
            var data = await res.json();
            document.getElementById('last-update').textContent = data.generated_at;
            document.getElementById('p-sessions').textContent  = data.sessions.length;
            document.getElementById('p-files').textContent     = data.open_files.length;
            document.getElementById('badge-sessions').textContent = data.sessions.length;
            document.getElementById('badge-shares').textContent   = data.shares_ativos.length;
            document.getElementById('badge-files').textContent    = data.open_files.length;
            document.getElementById('badge-locks').textContent    = data.locks.length;
            document.getElementById('sessions-container').innerHTML = renderSessions(data.sessions);
            document.getElementById('shares-container').innerHTML   = renderShares(data.shares_ativos);
            document.getElementById('files-container').innerHTML    = renderFiles(data.open_files);
            document.getElementById('locks-container').innerHTML    = renderLocks(data.locks);
            updatePerf(data.performance);
        } catch(e) {
            console.warn('Falha ao atualizar monitor:', e);
        }
    }

    function scheduleNext() {
        if (!paused) {
            timer = setTimeout(async function() { await refresh(); scheduleNext(); }, parseInt(sel.value));
        }
    }

    btn.addEventListener('click', function() {
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

    sel.addEventListener('change', function() { clearTimeout(timer); if (!paused) scheduleNext(); });

    scheduleNext();
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Monitor Samba';
require __DIR__ . '/../layouts/main.php';
