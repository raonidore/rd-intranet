<?php
/**
 * Aba "Desempenho" da ficha da máquina (agente 1.0.61+): coleta contínua
 * (liga/desliga e intervalo por máquina), gráficos de 24 h e 7 dias, picos
 * com o processo/software responsável, maiores consumidores e tráfego por IP.
 * Dados: retrato "desempenho" via /ativos/seguranca/monitor. Precisa de:
 * $ativo, $podeEditarAtivo.
 */
?>
<div class="tab-pane fade" id="abaDesempenho">
    <div id="dsp" data-ativo="<?= (int)$ativo['id'] ?>" data-pode-editar="<?= $podeEditarAtivo ? '1' : '0' ?>">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <div class="fw-semibold"><i class="bi bi-activity"></i> Coleta contínua de desempenho</div>
                    <div class="small text-muted" id="dspStatus">Carregando...</div>
                </div>
                <?php if ($podeEditarAtivo): ?>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <select class="form-select form-select-sm" id="dspLigado" style="width:auto">
                        <option value="padrao">Seguir padrão</option>
                        <option value="1">Ligada nesta máquina</option>
                        <option value="0">Desligada nesta máquina</option>
                    </select>
                    <div class="input-group input-group-sm" style="width:150px">
                        <span class="input-group-text">a cada</span>
                        <input type="number" class="form-control" id="dspIntervalo" min="2" max="60" placeholder="5">
                        <span class="input-group-text">s</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary" id="dspSalvar">Aplicar</button>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="dspVazio" class="alert alert-light border small d-none"></div>

        <div id="dspConteudo" class="d-none">
            <div class="dsp-kpis mb-3" id="dspKpis"></div>

            <div class="card border-0 shadow-sm mb-3 dsp-grafico">
                <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <strong>Uso no tempo</strong>
                    <div class="btn-group btn-group-sm" role="group">
                        <input type="radio" class="btn-check" name="dspPeriodo" id="dspP24" value="24h" checked>
                        <label class="btn btn-outline-secondary" for="dspP24">24 horas</label>
                        <input type="radio" class="btn-check" name="dspPeriodo" id="dspP7" value="7d">
                        <label class="btn btn-outline-secondary" for="dspP7">7 dias</label>
                    </div>
                </div>
                <div class="card-body">
                    <div style="height:260px"><canvas id="dspGraficoUso"></canvas></div>
                    <div style="height:150px" class="mt-3"><canvas id="dspGraficoRede"></canvas></div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white"><strong>Picos</strong> <small class="text-muted" id="dspPicosInfo"></small></div>
                <div class="card-body p-0" id="dspPicos"></div>
            </div>

            <div class="row g-3">
                <div class="col-xl-7">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <strong>Quem mais consumiu (24 h)</strong>
                            <div class="btn-group btn-group-sm" role="group" id="dspTopSel">
                                <button type="button" class="btn btn-outline-secondary active" data-top="top_cpu">CPU</button>
                                <button type="button" class="btn btn-outline-secondary" data-top="top_memoria">Memória</button>
                                <button type="button" class="btn btn-outline-secondary" data-top="top_disco">Disco</button>
                                <button type="button" class="btn btn-outline-secondary" data-top="top_rede">Rede</button>
                            </div>
                        </div>
                        <div class="card-body p-0" id="dspTop"></div>
                    </div>
                </div>
                <div class="col-xl-5">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <strong>Rede por IP (24 h)</strong>
                            <div class="btn-group btn-group-sm" role="group" id="dspIpSel">
                                <button type="button" class="btn btn-outline-secondary active" data-ip="top_ips">Tráfego</button>
                                <button type="button" class="btn btn-outline-secondary" data-ip="top_ips_conexoes">Conexões</button>
                            </div>
                        </div>
                        <div class="card-body p-0" id="dspIps"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.dsp-kpis { display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:.75rem; }
.dsp-kpi { position:relative; overflow:hidden; border-radius:.75rem; padding:.85rem 1rem; color:#e6edf3;
    background:linear-gradient(160deg, #1c2330 0%, #11151c 100%); border:1px solid rgba(88,166,255,.18); }
.dsp-kpi::before { content:""; position:absolute; left:14px; right:14px; top:0; height:2px; background:linear-gradient(90deg, transparent, var(--cor), transparent); }
.dsp-kpi .rotulo { font-size:.7rem; letter-spacing:.06em; text-transform:uppercase; color:#8b949e; display:flex; align-items:center; gap:.4rem; }
.dsp-kpi .rotulo::before { content:""; width:7px; height:7px; border-radius:50%; background:var(--cor); box-shadow:0 0 8px var(--cor); }
.dsp-kpi .valor { font-size:1.65rem; font-weight:300; line-height:1.15; margin-top:.2rem; }
.dsp-kpi .sub { font-size:.75rem; color:#8b949e; }
.dsp-tabela td, .dsp-tabela th { font-size:.85rem; }
.dsp-tabela th { font-size:.72rem; text-transform:uppercase; letter-spacing:.03em; color:#6c757d; font-weight:600; }
.dsp-tabela th[data-ordem] { cursor:pointer; user-select:none; }
.dsp-tabela th[data-ordem]:hover { color:#0d6efd; }
.dsp-caminho { font-family:var(--bs-font-monospace); font-size:.72rem; color:#7a838c; word-break:break-all; }
.dsp-barra { height:4px; border-radius:2px; background:#eef0f2; margin-top:3px; }
.dsp-barra span { display:block; height:100%; border-radius:2px; background:linear-gradient(90deg, #58a6ff, #8957e5); }
.dsp-metrica { display:inline-block; font-size:.7rem; font-weight:600; padding:.1rem .45rem; border-radius:999px; color:#fff; }
</style>

<script>
(function () {
    const raiz = document.getElementById('dsp');
    if (!raiz) return;
    const ativoId = raiz.dataset.ativo;
    const podeEditar = raiz.dataset.podeEditar === '1';
    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const tam = (mb) => mb >= 1024 ? (mb / 1024).toFixed(2) + ' GB' : mb >= 1 ? mb.toFixed(1) + ' MB' : Math.round(mb * 1024) + ' KB';
    const tempo = (s) => s >= 3600 ? (s / 3600).toFixed(1) + ' h' : s >= 60 ? (s / 60).toFixed(1) + ' min' : Math.round(s) + ' s';
    const duracao = (s) => s >= 3600 ? Math.floor(s / 3600) + 'h' + String(Math.floor(s % 3600 / 60)).padStart(2, '0') + 'min' : s >= 60 ? Math.floor(s / 60) + ' min ' + (s % 60) + ' s' : s + ' s';
    const cores = { cpu: '#3b82f6', memoria: '#8957e5', disco: '#2ea043', rede: '#0ea5b7' };
    const rotulos = { cpu: 'CPU', memoria: 'Memória', disco: 'Disco', rede: 'Rede' };
    let dados = null, retrato = null, graficos = [], periodo = '24h', topAtual = 'top_cpu', ipAtual = 'top_ips', carregado = false;

    async function carregar() {
        const r = await (await fetch(<?= json_encode(url('/ativos/seguranca/monitor')) ?> + '?id=' + ativoId)).json();
        if (!r.success) return;
        dados = r;
        retrato = (r.retratos.desempenho || {}).dados || null;
        const recebido = (r.retratos.desempenho || {}).recebido_em;
        const ov = r.desempenho_override || {}, pd = r.desempenho_padrao || {};
        document.getElementById('dspStatus').innerHTML = (r.desempenho.ativo
            ? '<span class="text-success">● ligada</span> · a cada ' + r.desempenho.intervalo + ' s'
            : '<span class="text-secondary">○ desligada</span>')
            + ' · ' + (ov.desempenho === null || ov.desempenho === undefined ? 'segue o padrão (' + (pd.desempenho ? 'ligada' : 'desligada') + ')' : 'definida nesta máquina')
            + (recebido ? ' · último retrato ' + esc(recebido.substr(8, 2) + '/' + recebido.substr(5, 2) + ' ' + recebido.substr(11, 5)) : '');
        if (podeEditar) {
            document.getElementById('dspLigado').value = ov.desempenho === true ? '1' : ov.desempenho === false ? '0' : 'padrao';
            document.getElementById('dspIntervalo').value = ov.desempenho_intervalo || '';
            document.getElementById('dspIntervalo').placeholder = pd.desempenho_intervalo || 5;
        }
        const vazio = document.getElementById('dspVazio');
        if (!retrato) {
            vazio.classList.remove('d-none');
            vazio.innerHTML = r.desempenho.ativo
                ? 'Coleta ligada. O primeiro retrato chega em alguns minutos (agente 1.0.61 ou mais novo).'
                : 'A coleta contínua está desligada nesta máquina. Ligue para registrar histórico de 7 dias, picos de CPU/memória/disco/rede com o processo e o software responsáveis, e o tráfego por IP. Indicado para servidores.';
            document.getElementById('dspConteudo').classList.add('d-none');
            return;
        }
        vazio.classList.add('d-none');
        document.getElementById('dspConteudo').classList.remove('d-none');
        desenhar();
    }

    function desenhar() {
        const serie = (periodo === '7d' ? retrato.serie_7d : retrato.serie_24h) || [];
        const media = (k) => serie.length ? serie.reduce((s, p) => s + (p[k] || 0), 0) / serie.length : 0;
        const max = (k) => serie.reduce((m, p) => Math.max(m, p[k] || 0), 0);
        const picos = retrato.picos || [];
        const picosPeriodo = picos.filter((p) => new Date(p.inicio) >= new Date(Date.now() - (periodo === '7d' ? 7 : 1) * 86400000));
        document.getElementById('dspKpis').innerHTML = [
            ['CPU média', media('cpu').toFixed(0) + '%', 'máxima ' + max('cpu_max').toFixed(0) + '%', cores.cpu],
            ['Memória média', media('mem').toFixed(0) + '%', 'máxima ' + max('mem_max').toFixed(0) + '% de ' + (retrato.maquina.memoria_gb || '?') + ' GB', cores.memoria],
            ['Disco ativo', media('disco').toFixed(0) + '%', 'máximo ' + max('disco_max').toFixed(0) + '%', cores.disco],
            ['Rede (pico)', max('rede_max').toFixed(1) + ' Mbps', 'link ' + Math.round(retrato.maquina.rede_mbps || 0) + ' Mbps', cores.rede],
            ['Picos', String(picosPeriodo.length), periodo === '7d' ? 'nos últimos 7 dias' : 'nas últimas 24 h', picosPeriodo.length ? '#f85149' : '#3fb950']
        ].map(([r, v, s, c]) => '<div class="dsp-kpi" style="--cor:' + c + '"><div class="rotulo">' + r + '</div><div class="valor">' + v + '</div><div class="sub">' + esc(s) + '</div></div>').join('');

        graficos.forEach((g) => g.destroy());
        graficos = [];
        const rotulosX = serie.map((p) => {
            const d = new Date(p.t * 1000);
            return periodo === '7d' ? d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }) + ' ' + String(d.getHours()).padStart(2, '0') + 'h' : String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
        });
        const degrade = (ctx, cor) => {
            const g = ctx.chart.ctx.createLinearGradient(0, 0, 0, ctx.chart.height);
            g.addColorStop(0, cor + '55');
            g.addColorStop(1, cor + '00');
            return g;
        };
        const opcoes = (maxY, unidade) => ({
            responsive: true, maintainAspectRatio: false, animation: false, interaction: { mode: 'index', intersect: false },
            plugins: { legend: { labels: { usePointStyle: true, boxWidth: 8 } }, tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + c.parsed.y.toFixed(1) + unidade } } },
            elements: { point: { radius: 0 }, line: { tension: .35, borderWidth: 2 } },
            scales: { y: { min: 0, max: maxY, ticks: { callback: (v) => v + unidade }, grid: { color: 'rgba(0,0,0,.05)' } }, x: { ticks: { maxTicksLimit: 12, autoSkip: true }, grid: { display: false } } }
        });
        if (typeof Chart === 'undefined') return;
        graficos.push(new Chart(document.getElementById('dspGraficoUso'), {
            type: 'line',
            data: {
                labels: rotulosX, datasets: [
                    { label: 'CPU média', data: serie.map((p) => p.cpu), borderColor: cores.cpu, fill: true, backgroundColor: (c) => degrade(c, cores.cpu) },
                    { label: 'CPU máxima', data: serie.map((p) => p.cpu_max), borderColor: cores.cpu + '66', borderDash: [4, 4], fill: false },
                    { label: 'Memória', data: serie.map((p) => p.mem), borderColor: cores.memoria, fill: false },
                    { label: 'Disco ativo', data: serie.map((p) => p.disco), borderColor: cores.disco, fill: false }
                ]
            },
            options: opcoes(100, '%')
        }));
        graficos.push(new Chart(document.getElementById('dspGraficoRede'), {
            type: 'line',
            data: {
                labels: rotulosX, datasets: [
                    { label: 'Rede recebe', data: serie.map((p) => p.rede_rx), borderColor: cores.rede, fill: true, backgroundColor: (c) => degrade(c, cores.rede) },
                    { label: 'Rede envia', data: serie.map((p) => p.rede_tx), borderColor: '#f0883e', fill: false }
                ]
            },
            options: opcoes(undefined, ' Mbps')
        }));

        // Picos
        const lim = retrato.limites || {};
        document.getElementById('dspPicosInfo').textContent = '· CPU ≥ ' + (lim.CpuPico || 90) + '%, memória ≥ ' + (lim.MemoriaPico || 90) + '%, disco ≥ ' + (lim.DiscoPico || 90) + '%, rede ≥ ' + (lim.RedePicoPercentual || 80) + '% do link, por ' + (lim.DuracaoMinimaSegundos || 60) + ' s ou mais';
        document.getElementById('dspPicos').innerHTML = picos.length ? '<div class="table-responsive"><table class="table table-sm align-middle mb-0 dsp-tabela"><thead><tr><th class="ps-3">Quando</th><th>Métrica</th><th>Duração</th><th>Máximo</th><th>Quem causou</th><th>IPs</th></tr></thead><tbody>'
            + picos.slice(0, 40).map((p) => '<tr><td class="ps-3 text-nowrap">' + esc(new Date(p.inicio).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }))
                + (p.fim ? '' : ' <span class="badge text-bg-danger">agora</span>') + '</td>'
                + '<td><span class="dsp-metrica" style="background:' + (cores[p.metrica] || '#666') + '">' + esc(rotulos[p.metrica] || p.metrica) + '</span></td>'
                + '<td class="text-nowrap">' + duracao(p.duracao_s) + '</td><td class="text-nowrap">' + Number(p.maximo).toFixed(1) + ' ' + esc(p.unidade) + '</td>'
                + '<td>' + (p.responsaveis || []).map((r) => '<div><b>' + esc(r.nome) + '</b> <span class="text-muted small">' + Number(r.valor).toFixed(1) + ' ' + esc(r.unidade) + (r.software ? ' · ' + esc(r.software) : '') + '</span></div>').join('') + '</td>'
                + '<td class="small">' + (p.ips || []).map((i) => '<a target="_blank" rel="noopener" href="https://www.abuseipdb.com/check/' + encodeURIComponent(i.ip) + '">' + esc(i.ip) + '</a> ' + tam(i.recebido_mb + i.enviado_mb)).join('<br>') + '</td></tr>').join('')
            + '</tbody></table></div>' : '<div class="p-3 small text-muted"><i class="bi bi-check-circle text-success"></i> Nenhum pico registrado.</div>';

        desenharTop();
        desenharIps();
    }

    function tabelaOrdenavel(id, colunas, linhas) {
        const el = document.getElementById(id);
        let ordem = null, crescente = false;
        function render() {
            const ls = linhas.slice();
            if (ordem !== null) ls.sort((a, b) => { const x = colunas[ordem].valor(a), y = colunas[ordem].valor(b); return (typeof x === 'number' ? x - y : String(x).localeCompare(String(y), 'pt-BR')) * (crescente ? 1 : -1); });
            el.innerHTML = ls.length ? '<div class="table-responsive"><table class="table table-sm align-middle mb-0 dsp-tabela"><thead><tr>' + colunas.map((c, i) => '<th data-ordem="' + i + '" class="' + (i === 0 ? 'ps-3' : '') + '">' + c.titulo + (ordem === i ? (crescente ? ' ▲' : ' ▼') : '') + '</th>').join('') + '</tr></thead><tbody>'
                + ls.map((l) => '<tr>' + colunas.map((c, i) => '<td class="' + (i === 0 ? 'ps-3' : '') + '">' + c.html(l) + '</td>').join('') + '</tr>').join('') + '</tbody></table></div>'
                : '<div class="p-3 small text-muted">Sem dados ainda.</div>';
            el.querySelectorAll('th[data-ordem]').forEach((th) => th.addEventListener('click', () => { const i = +th.dataset.ordem; crescente = ordem === i ? !crescente : false; ordem = i; render(); }));
        }
        render();
    }

    function desenharTop() {
        const lista = retrato[topAtual] || [];
        const chave = { top_cpu: 'cpu_s', top_memoria: 'mem_max_mb', top_disco: 'disco_mb', top_rede: 'rede_mb' }[topAtual];
        const maior = Math.max(1, ...lista.map((p) => p[chave] || 0));
        tabelaOrdenavel('dspTop', [
            { titulo: 'Programa', valor: (p) => p.nome, html: (p) => '<b>' + esc(p.nome) + '</b>' + (p.software ? ' <span class="text-muted small">' + esc(p.software) + '</span>' : '') + '<div class="dsp-caminho">' + esc(p.caminho || '') + '</div><div class="dsp-barra"><span style="width:' + ((p[chave] || 0) / maior * 100).toFixed(0) + '%"></span></div>' },
            { titulo: 'CPU', valor: (p) => p.cpu_s, html: (p) => tempo(p.cpu_s) },
            { titulo: 'Memória máx.', valor: (p) => p.mem_max_mb, html: (p) => tam(p.mem_max_mb) },
            { titulo: 'Disco', valor: (p) => p.disco_mb, html: (p) => tam(p.disco_mb) },
            { titulo: 'Rede', valor: (p) => p.rede_mb, html: (p) => tam(p.rede_mb) }
        ], lista);
    }

    function desenharIps() {
        tabelaOrdenavel('dspIps', [
            { titulo: 'IP', valor: (i) => i.ip, html: (i) => '<a target="_blank" rel="noopener" href="https://www.abuseipdb.com/check/' + encodeURIComponent(i.ip) + '" title="Reputação no AbuseIPDB">' + esc(i.ip) + '</a><div class="small text-muted">' + esc((i.programas || []).join(', ')) + (i.portas && i.portas.length ? ' · portas ' + esc(i.portas.join(', ')) : '') + '</div>' },
            { titulo: 'Recebido', valor: (i) => i.recebido_mb, html: (i) => tam(i.recebido_mb) },
            { titulo: 'Enviado', valor: (i) => i.enviado_mb, html: (i) => tam(i.enviado_mb) },
            { titulo: 'Conexões', valor: (i) => i.conexoes_max, html: (i) => i.conexoes_max }
        ], retrato[ipAtual] || []);
    }

    document.querySelectorAll('input[name="dspPeriodo"]').forEach((r) => r.addEventListener('change', () => { periodo = r.value; desenhar(); }));
    document.getElementById('dspTopSel').addEventListener('click', (e) => { const b = e.target.closest('[data-top]'); if (!b) return; topAtual = b.dataset.top; document.querySelectorAll('#dspTopSel .btn').forEach((x) => x.classList.toggle('active', x === b)); desenharTop(); });
    document.getElementById('dspIpSel').addEventListener('click', (e) => { const b = e.target.closest('[data-ip]'); if (!b) return; ipAtual = b.dataset.ip; document.querySelectorAll('#dspIpSel .btn').forEach((x) => x.classList.toggle('active', x === b)); desenharIps(); });
    const salvar = document.getElementById('dspSalvar');
    if (salvar) salvar.addEventListener('click', async () => {
        const corpo = new FormData();
        corpo.set('id', ativoId);
        corpo.set('ligado', document.getElementById('dspLigado').value);
        corpo.set('intervalo', document.getElementById('dspIntervalo').value);
        salvar.disabled = true;
        const r = await (await fetch(<?= json_encode(url('/ativos/desempenho/config')) ?>, { method: 'POST', body: corpo })).json();
        salvar.disabled = false;
        alert(r.message || 'Salvo.');
        carregar();
    });

    function garantirChart(depois) {
        if (typeof Chart !== 'undefined') return depois();
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js';
        s.onload = depois;
        document.head.appendChild(s);
    }
    const nav = document.querySelector('.nav-link[data-bs-target="#abaDesempenho"]');
    const abrir = () => { if (!carregado) { carregado = true; garantirChart(carregar); } };
    if (nav) nav.addEventListener('shown.bs.tab', abrir);
    if (location.hash === '#abaDesempenho') abrir();
})();
</script>
