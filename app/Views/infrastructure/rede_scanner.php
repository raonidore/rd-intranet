<?php
ob_start();

use App\Components\Alert;
?>

<style>
.ipscan-card { border: 0; border-radius: 14px; box-shadow: 0 4px 14px rgba(0,0,0,.06); }
.ipscan-hero { background: linear-gradient(135deg, #0d1b2a 0%, #0d3b66 60%, #0d6efd 130%); border-radius: 16px; color: #fff; padding: 28px; position: relative; overflow: hidden; }
.ipscan-hero::after { content:''; position:absolute; inset:0; background: radial-gradient(circle at 85% 20%, rgba(255,255,255,.12), transparent 55%); }
.ipscan-hero h4 { position: relative; z-index: 1; }
.ipscan-hero .text-hero-muted { color: rgba(255,255,255,.75); position: relative; z-index: 1; }

.ipscan-radar-wrap { display:flex; flex-direction:column; align-items:center; padding: 30px 0 10px; }
.ipscan-radar { position: relative; width: 240px; height: 240px; border-radius: 50%;
    background: radial-gradient(circle, rgba(13,110,253,.14) 0%, rgba(13,110,253,.04) 65%, transparent 100%);
    border: 1px solid rgba(13,110,253,.25); }
.ipscan-radar::before, .ipscan-radar::after { content:''; position:absolute; border-radius:50%; border:1px solid rgba(13,110,253,.18); }
.ipscan-radar::before { inset: 18%; }
.ipscan-radar::after { inset: 38%; }
.ipscan-radar-sweep { position:absolute; inset:0; border-radius:50%; overflow:hidden; }
.ipscan-radar-sweep span { position:absolute; inset:0; border-radius:50%;
    background: conic-gradient(from 0deg, rgba(13,110,253,.55), rgba(13,110,253,0) 70deg);
    animation: ipscan-spin 1.8s linear infinite; }
@keyframes ipscan-spin { to { transform: rotate(360deg); } }
.ipscan-radar-pct { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; }
.ipscan-radar-pct b { font-size: 2.1rem; color:#0d6efd; line-height:1; }
.ipscan-radar-pct small { color:#6c757d; margin-top:4px; }
@media (prefers-reduced-motion: reduce) { .ipscan-radar-sweep span { animation: none; } }

.ipscan-stat { text-align:center; }
.ipscan-stat b { font-size: 1.6rem; display:block; }
.ipscan-stat small { color:#6c757d; text-transform:uppercase; letter-spacing:.04em; font-size:11px; }

.ipscan-badge-novo { animation: ipscan-pulse 1.4s ease-in-out 2; }
@keyframes ipscan-pulse { 0%,100% { box-shadow: 0 0 0 0 rgba(25,135,84,.4); } 50% { box-shadow: 0 0 0 6px rgba(25,135,84,0); } }

.ipscan-map-node { cursor: default; }
.ipscan-map-node circle { transition: r .15s ease; }
.ipscan-map-node:hover circle { r: 9; }
.ipscan-map-label { font-size: 9px; fill: #495057; }

.ipscan-map-wrap { position: relative; min-height: 560px; }
.ipscan-map-wrap svg { cursor: grab; touch-action: none; }
.ipscan-map-wrap svg.dragging { cursor: grabbing; }
.ipscan-zoom-controls { position: absolute; top: 10px; right: 10px; display: flex; flex-direction: column; gap: 4px; z-index: 2; }
.ipscan-zoom-controls button { width: 30px; height: 30px; border-radius: 8px; border: 1px solid #e9ecef; background: #fff; box-shadow: 0 2px 6px rgba(0,0,0,.08); color: #495057; line-height: 1; }
.ipscan-zoom-controls button:hover { background: #f8fafc; }
.ipscan-map-legenda { position: absolute; bottom: 8px; left: 12px; display: flex; gap: 14px; font-size: 11px; color: #6c757d; z-index: 2; }
.ipscan-map-legenda span { display: inline-flex; align-items: center; gap: 4px; }
.ipscan-map-legenda i { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }

.ipscan-search { max-width: 320px; }

.ipscan-chip { display:flex; align-items:center; gap:8px; background:#f8fafc; border:1px solid #e9ecef; border-radius:10px; padding:6px 10px; font-size:12px; }
.ipscan-chip .chip-cidr { font-family: monospace; font-weight:600; color:#0d3b66; }
.ipscan-chip .chip-meta { color:#6c757d; }
.ipscan-chip button { border:0; background:transparent; color:#0d6efd; padding:0 2px; line-height:1; }
.ipscan-chip button:hover { color:#0a58ca; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div></div>
    <a href="<?= url('/infraestrutura/rede') ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Interfaces
    </a>
</div>

<?= Alert::flash() ?>

<div class="ipscan-hero mb-4">
    <h4 class="mb-1"><i class="bi bi-broadcast-pin me-2"></i>IP Scanner</h4>
    <div class="text-hero-muted">Descobre dispositivos ativos na rede local -- IP, hostname, MAC, fabricante -- sem precisar de nenhum programa externo.</div>
</div>

<div class="card ipscan-card mb-4">
    <div class="card-body">
        <form id="form-scanner" class="d-flex gap-2 align-items-end flex-wrap">
            <div class="flex-grow-1" style="min-width:220px">
                <label class="form-label small mb-1">Faixa(s) de IP (CIDR)</label>
                <input type="text" name="cidr" id="input-cidr" class="form-control font-monospace"
                       value="<?= htmlspecialchars($faixaSugerida ?? '') ?>" placeholder="ex: 192.168.1.0/24, 192.168.20.0/24, 192.168.30.0/24" required>
                <div class="field-help text-muted small mt-1">
                    <?= $faixaSugerida ? 'Sugerido a partir da rede deste servidor.' : 'Informe a faixa da sua rede local.' ?>
                    Pode informar mais de uma faixa separada por vírgula (até 8) -- sai tudo num resultado só.
                    Só faixas privadas (RFC1918), cada uma até /22 (1024 endereços).
                </div>
            </div>
            <button type="submit" class="btn btn-primary" id="btn-iniciar">
                <i class="bi bi-radar me-1"></i> Iniciar varredura
            </button>
        </form>
    </div>
</div>

<div class="card ipscan-card mb-4" id="painel-recentes" style="<?= empty($recentes) ? 'display:none' : '' ?>">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
        <span class="small text-muted text-uppercase" style="letter-spacing:.04em"><i class="bi bi-clock-history me-1"></i>Varreduras recentes</span>
    </div>
    <div class="card-body py-2">
        <div id="lista-recentes" class="d-flex gap-2 flex-wrap"></div>
    </div>
</div>

<div class="card ipscan-card mb-4 d-none" id="painel-radar">
    <div class="card-body">
        <div class="ipscan-radar-wrap">
            <div class="ipscan-radar">
                <div class="ipscan-radar-sweep"><span></span></div>
                <div class="ipscan-radar-pct">
                    <b id="radar-pct">0%</b>
                    <small id="radar-msg">Iniciando...</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="painel-resultado" class="d-none">
    <div id="banner-comparacao" class="mb-3"></div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md">
            <div class="card ipscan-card h-100"><div class="card-body ipscan-stat">
                <b id="stat-total">0</b><small>Dispositivos</small>
            </div></div>
        </div>
        <div class="col-6 col-md">
            <div class="card ipscan-card h-100"><div class="card-body ipscan-stat">
                <b id="stat-cadastrados" class="text-primary">0</b><small>Cadastrados como Ativo</small>
            </div></div>
        </div>
        <div class="col-6 col-md">
            <div class="card ipscan-card h-100"><div class="card-body ipscan-stat">
                <b id="stat-nao-cadastrados" class="text-warning">0</b><small>Não cadastrados</small>
            </div></div>
        </div>
        <div class="col-6 col-md">
            <div class="card ipscan-card h-100"><div class="card-body ipscan-stat">
                <b id="stat-com-mac">0</b><small>Com MAC identificado</small>
            </div></div>
        </div>
        <div class="col-6 col-md">
            <div class="card ipscan-card h-100"><div class="card-body ipscan-stat">
                <b id="stat-fabricantes">0</b><small>Fabricantes distintos</small>
            </div></div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-3">
            <div class="card ipscan-card h-100">
                <div class="card-header bg-white"><i class="bi bi-pie-chart me-1"></i> Por fabricante</div>
                <div class="card-body"><canvas id="grafico-fabricantes" height="220"></canvas></div>
            </div>
        </div>
        <div class="col-lg-9">
            <div class="card ipscan-card h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-diagram-3 me-1"></i> Mapa da rede</span>
                    <div class="d-flex align-items-center gap-2">
                        <a href="#" class="btn btn-sm btn-outline-primary" id="btn-exportar-mapa">
                            <i class="bi bi-box-arrow-up-right"></i> Mapa de Rede
                        </a>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-secondary active" id="btn-view-tabela">Tabela</button>
                            <button type="button" class="btn btn-outline-secondary" id="btn-view-mapa">Mapa</button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="view-mapa" class="d-none ipscan-map-wrap">
                        <div class="ipscan-zoom-controls">
                            <button type="button" id="btn-zoom-in" title="Aproximar"><i class="bi bi-plus-lg"></i></button>
                            <button type="button" id="btn-zoom-out" title="Afastar"><i class="bi bi-dash-lg"></i></button>
                            <button type="button" id="btn-zoom-reset" title="Restaurar"><i class="bi bi-aspect-ratio"></i></button>
                        </div>
                        <div class="ipscan-map-legenda">
                            <span><i style="background:#0d6efd"></i> Cadastrado como Ativo</span>
                            <span><i style="background:#fd7e14"></i> Não cadastrado</span>
                        </div>
                        <svg id="svg-mapa" width="100%" height="560" viewBox="0 0 400 320">
                            <g id="mapa-zoom-layer"></g>
                        </svg>
                    </div>
                    <div id="view-tabela">
                        <div class="p-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <input type="text" id="busca-hosts" class="form-control form-control-sm ipscan-search" placeholder="Buscar por IP, nome, MAC ou fabricante...">
                            <div class="dropdown" id="export-dropdown" data-rd-export-ignore
                                 data-rd-export-container="#painel-resultado"
                                 data-rd-export-titulo="IP Scanner"
                                 data-rd-export-ferramenta="ip-scanner"
                                 data-rd-export-alvo="">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-download"></i> Exportar
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="#" data-rd-export-formato="html">HTML</a></li>
                                    <li><a class="dropdown-item" href="#" data-rd-export-formato="pdf">PDF</a></li>
                                    <li><a class="dropdown-item" href="#" data-rd-export-formato="jpg">JPG</a></li>
                                    <li><a class="dropdown-item" href="#" id="link-export-csv">CSV</a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>IP</th><th>Hostname</th><th>MAC</th><th>Fabricante</th><th>Cadastro</th><th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="tabela-hosts"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de portas -->
<div class="modal fade" id="modalPortas" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-hdd-network me-1"></i> Portas abertas -- <span id="modalPortasIp"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalPortasCorpo">
                <div class="text-center text-muted py-3"><i class="bi bi-hourglass-split"></i> Escaneando...</div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de exportar pro Mapa de Rede -->
<div class="modal fade" id="modalExportarMapa" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-diagram-3 me-1"></i> Exportar pro Mapa de Rede</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="form-check mb-2">
                    <input type="radio" class="form-check-input" name="exp-mapa-destino" id="exp-mapa-novo" value="novo" checked>
                    <label class="form-check-label" for="exp-mapa-novo">Criar um mapa novo</label>
                </div>
                <input type="text" id="exp-mapa-nome-novo" class="form-control form-control-sm mb-3" placeholder="Nome do novo mapa">

                <div class="form-check mb-2">
                    <input type="radio" class="form-check-input" name="exp-mapa-destino" id="exp-mapa-existente" value="existente">
                    <label class="form-check-label" for="exp-mapa-existente">Adicionar a um mapa existente</label>
                </div>
                <select id="exp-mapa-select" class="form-select form-select-sm" disabled></select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-confirmar-exportar-mapa">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Exportar
                </button>
            </div>
        </div>
    </div>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1080">
    <div id="ipscan-toast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="ipscan-toast-msg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script src="<?= asset_url('/assets/js/rd-diagnostico.js') ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
    const URL_INICIAR = <?= json_encode(url('/infraestrutura/rede/scanner')) ?>;
    const URL_STATUS = <?= json_encode(url('/infraestrutura/rede/scanner/status')) ?>;
    const URL_FINALIZAR = <?= json_encode(url('/infraestrutura/rede/scanner/finalizar')) ?>;
    const URL_HISTORICO = <?= json_encode(url('/infraestrutura/rede/scanner/historico')) ?>;
    const URL_EXECUCAO = <?= json_encode(url('/infraestrutura/rede/scanner/execucao')) ?>;
    const URL_ATIVO_NOVO = <?= json_encode(url('/ativos/novo')) ?>;
    const URL_ATIVO_VER = <?= json_encode(url('/ativos/ver')) ?>;
    const URL_PORTAS = <?= json_encode(url('/infraestrutura/rede/scanner/portas')) ?>;
    const URL_WOL = <?= json_encode(url('/infraestrutura/rede/scanner/wol')) ?>;
    const URL_MAPA_LISTAR = <?= json_encode(url('/infraestrutura/rede/mapa/listar-nomes')) ?>;
    const URL_MAPA_IMPORTAR = <?= json_encode(url('/infraestrutura/rede/mapa/importar')) ?>;
    const URL_MAPA_VER = <?= json_encode(url('/infraestrutura/rede/mapa/ver')) ?>;

    let hostsAtuais = [];
    let poll = null;
    let chartFabricantes = null;

    function tempoRelativo(dataStr) {
        const diffMin = Math.max(0, Math.round((Date.now() - new Date(dataStr.replace(' ', 'T'))) / 60000));
        if (diffMin < 1) return 'agora';
        if (diffMin < 60) return diffMin + 'min atrás';
        const diffH = Math.round(diffMin / 60);
        if (diffH < 24) return diffH + 'h atrás';
        return Math.round(diffH / 24) + 'd atrás';
    }

    function renderizarRecentes(lista) {
        const painel = document.getElementById('painel-recentes');
        const container = document.getElementById('lista-recentes');
        container.innerHTML = '';

        if (!lista || lista.length === 0) { painel.style.display = 'none'; return; }
        painel.style.display = '';

        lista.forEach(function (item) {
            const chip = document.createElement('div');
            chip.className = 'ipscan-chip';
            chip.innerHTML =
                '<span class="chip-cidr">' + item.cidr + '</span>' +
                '<span class="chip-meta">' + item.total_hosts + ' host(s) &middot; ' + tempoRelativo(item.executado_em) + '</span>' +
                '<button type="button" data-acao="ver" title="Ver esta varredura"><i class="bi bi-eye"></i></button>' +
                '<button type="button" data-acao="rescan" title="Varrer de novo"><i class="bi bi-arrow-repeat"></i></button>';
            chip.querySelector('[data-acao="ver"]').addEventListener('click', function () {
                carregarExecucaoSalva(item.id);
            });
            chip.querySelector('[data-acao="rescan"]').addEventListener('click', function () {
                document.getElementById('input-cidr').value = item.cidr;
                document.getElementById('form-scanner').requestSubmit();
            });
            container.appendChild(chip);
        });
    }

    async function atualizarRecentes() {
        try {
            const res = await fetch(URL_HISTORICO);
            renderizarRecentes(await res.json());
        } catch (err) { /* silencioso -- não é crítico */ }
    }

    renderizarRecentes(<?= json_encode($recentes ?? []) ?>);

    function toast(msg, ok) {
        var el = document.getElementById('ipscan-toast');
        el.className = 'toast align-items-center text-white border-0 bg-' + (ok ? 'success' : 'danger');
        document.getElementById('ipscan-toast-msg').textContent = msg;
        bootstrap.Toast.getOrCreateInstance(el, { delay: 4500 }).show();
    }

    function pararPoll() { if (poll) { clearInterval(poll); poll = null; } }

    document.getElementById('form-scanner').addEventListener('submit', async function (e) {
        e.preventDefault();
        const cidr = document.getElementById('input-cidr').value.trim();
        document.getElementById('btn-iniciar').disabled = true;
        document.getElementById('painel-resultado').classList.add('d-none');

        try {
            const res = await fetch(URL_INICIAR, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'cidr=' + encodeURIComponent(cidr)
            });
            const dados = await res.json();

            if (!dados.success) {
                toast(dados.message || 'Não foi possível iniciar a varredura.', false);
                document.getElementById('btn-iniciar').disabled = false;
                return;
            }

            document.getElementById('painel-radar').classList.remove('d-none');
            document.getElementById('painel-radar').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            acompanhar(dados.execucao_id, cidr);
        } catch (err) {
            toast('Erro ao comunicar com o servidor.', false);
            document.getElementById('btn-iniciar').disabled = false;
        }
    });

    function acompanhar(execucaoId, cidr) {
        pararPoll();
        poll = setInterval(async function () {
            try {
                const res = await fetch(URL_STATUS + '?id=' + encodeURIComponent(execucaoId));
                const dados = await res.json();

                if (dados.status === 'rodando') {
                    const pct = dados.percentual || 0;
                    document.getElementById('radar-pct').textContent = pct + '%';
                    document.getElementById('radar-msg').textContent = dados.mensagem || 'Varrendo...';
                    return;
                }

                if (dados.status === 'concluido') {
                    pararPoll();
                    document.getElementById('radar-pct').textContent = '100%';
                    document.getElementById('radar-msg').textContent = 'Concluído';
                    await finalizar(execucaoId, cidr, dados.resultados || []);
                    return;
                }

                if (dados.status === 'erro') {
                    pararPoll();
                    document.getElementById('painel-radar').classList.add('d-none');
                    document.getElementById('btn-iniciar').disabled = false;
                    toast(dados.mensagem || 'Falha na varredura.', false);
                    return;
                }
                // "desconhecido" -- job ainda nao escreveu o primeiro status
            } catch (err) {
                // falha de rede pontual -- tenta de novo no proximo tick
            }
        }, 1200);
    }

    async function finalizar(execucaoId, cidr, resultados) {
        hostsAtuais = resultados;
        document.getElementById('btn-iniciar').disabled = false;

        try {
            const res = await fetch(URL_FINALIZAR, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + encodeURIComponent(execucaoId) + '&cidr=' + encodeURIComponent(cidr)
            });
            const dados = await res.json();
            if (dados.success) {
                hostsAtuais = dados.resultados || resultados;
                renderizarComparacao(dados.comparacao);
            } else {
                renderizarComparacao(null);
            }
        } catch (err) {
            renderizarComparacao(null);
        }

        setTimeout(function () { document.getElementById('painel-radar').classList.add('d-none'); }, 600);
        renderizarResultado(cidr);
        atualizarRecentes();
    }

    async function carregarExecucaoSalva(id) {
        try {
            const res = await fetch(URL_EXECUCAO + '?id=' + encodeURIComponent(id));
            const dados = await res.json();

            if (!dados.success) {
                toast(dados.message || 'Não foi possível carregar essa varredura.', false);
                return;
            }

            pararPoll();
            document.getElementById('painel-radar').classList.add('d-none');
            hostsAtuais = dados.resultados || [];

            const banner = document.getElementById('banner-comparacao');
            banner.innerHTML = '<div class="alert alert-secondary mb-0"><i class="bi bi-clock-history me-1"></i> Mostrando varredura salva de <strong>' + dados.executado_em + '</strong> -- não é uma varredura nova.</div>';

            document.getElementById('input-cidr').value = dados.cidr;
            renderizarResultado(dados.cidr);
            document.getElementById('painel-resultado').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } catch (err) {
            toast('Erro ao comunicar com o servidor.', false);
        }
    }

    function renderizarComparacao(comparacao) {
        const banner = document.getElementById('banner-comparacao');
        if (!comparacao || comparacao.primeira_execucao) {
            banner.innerHTML = '<div class="alert alert-info mb-0"><i class="bi bi-info-circle me-1"></i> Primeira varredura desta faixa -- nada para comparar ainda.</div>';
            return;
        }
        const novos = comparacao.novos || [];
        const sumiram = comparacao.sumiram || [];
        if (novos.length === 0 && sumiram.length === 0) {
            banner.innerHTML = '<div class="alert alert-secondary mb-0"><i class="bi bi-check2-circle me-1"></i> Nenhuma mudança desde a última varredura desta faixa.</div>';
            return;
        }
        let html = '<div class="alert alert-success ipscan-badge-novo mb-0"><i class="bi bi-stars me-1"></i>';
        const partes = [];
        if (novos.length) partes.push('<strong>' + novos.length + '</strong> novo(s) dispositivo(s)');
        if (sumiram.length) partes.push('<strong>' + sumiram.length + '</strong> não respondeu(ram) mais');
        html += partes.join(' &middot; ') + ' desde a última varredura desta faixa.</div>';
        banner.innerHTML = html;
    }

    function renderizarResultado(cidr) {
        document.getElementById('painel-resultado').classList.remove('d-none');
        document.getElementById('export-dropdown').setAttribute('data-rd-export-alvo', cidr);

        const comMac = hostsAtuais.filter(h => h.mac).length;
        const cadastrados = hostsAtuais.filter(h => h.ativo).length;
        const fabricantes = {};
        hostsAtuais.forEach(h => { const f = h.vendor || 'Desconhecido'; fabricantes[f] = (fabricantes[f] || 0) + 1; });

        document.getElementById('stat-total').textContent = hostsAtuais.length;
        document.getElementById('stat-cadastrados').textContent = cadastrados;
        document.getElementById('stat-nao-cadastrados').textContent = hostsAtuais.length - cadastrados;
        document.getElementById('stat-com-mac').textContent = comMac;
        document.getElementById('stat-fabricantes').textContent = Object.keys(fabricantes).length;

        renderizarGrafico(fabricantes);
        renderizarTabela(hostsAtuais);
        renderizarMapa(hostsAtuais);
    }

    function renderizarGrafico(fabricantes) {
        const ctx = document.getElementById('grafico-fabricantes');
        const labels = Object.keys(fabricantes);
        const valores = Object.values(fabricantes);
        const cores = ['#0d6efd', '#20c997', '#fd7e14', '#6f42c1', '#d63384', '#0dcaf0', '#ffc107', '#198754', '#6c757d', '#dc3545'];

        if (chartFabricantes) { chartFabricantes.destroy(); }
        chartFabricantes = new Chart(ctx, {
            type: 'pie',
            data: { labels: labels, datasets: [{ data: valores, backgroundColor: labels.map((_, i) => cores[i % cores.length]) }] },
            options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } } }
        });
    }

    function renderizarTabela(hosts) {
        const corpo = document.getElementById('tabela-hosts');
        corpo.innerHTML = '';

        if (hosts.length === 0) {
            corpo.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Nenhum dispositivo respondeu nessa faixa.</td></tr>';
            return;
        }

        hosts.forEach(function (h) {
            const busca = (h.ip + ' ' + (h.hostname || '') + ' ' + (h.mac || '') + ' ' + (h.vendor || '')).toLowerCase();
            const tr = document.createElement('tr');
            tr.setAttribute('data-busca', busca);

            let colCadastro;
            if (h.ativo) {
                colCadastro = '<a href="' + URL_ATIVO_VER + '?id=' + h.ativo.id + '" class="badge text-bg-primary text-decoration-none">' +
                    '<i class="bi bi-check-circle me-1"></i>' + h.ativo.codigo_patrimonio + '</a>';
            } else {
                colCadastro = '<a href="' + URL_ATIVO_NOVO + '?nome=' + encodeURIComponent(h.hostname || h.ip) + '&ip=' + encodeURIComponent(h.ip) + '" class="badge text-bg-warning text-decoration-none" title="Cadastrar como ativo">' +
                    '<i class="bi bi-plus-circle me-1"></i>Não cadastrado</a>';
            }

            tr.innerHTML =
                '<td class="font-monospace">' + h.ip + '</td>' +
                '<td>' + (h.hostname || '<span class="text-muted">-</span>') + '</td>' +
                '<td class="font-monospace small">' + (h.mac || '<span class="text-muted">-</span>') + '</td>' +
                '<td>' + (h.vendor || '<span class="text-muted">-</span>') + '</td>' +
                '<td>' + colCadastro + '</td>' +
                '<td class="text-end"></td>';

            const acoes = tr.querySelector('td:last-child');

            const btnPortas = document.createElement('button');
            btnPortas.className = 'btn btn-sm btn-outline-secondary me-1';
            btnPortas.title = 'Escanear portas';
            btnPortas.innerHTML = '<i class="bi bi-hdd-network"></i>';
            btnPortas.addEventListener('click', () => escanearPortas(h.ip));
            acoes.appendChild(btnPortas);

            if (h.mac) {
                const btnWol = document.createElement('button');
                btnWol.className = 'btn btn-sm btn-outline-primary';
                btnWol.title = 'Wake-on-LAN';
                btnWol.innerHTML = '<i class="bi bi-power"></i>';
                btnWol.addEventListener('click', () => enviarWol(h.mac, btnWol));
                acoes.appendChild(btnWol);
            }

            corpo.appendChild(tr);
        });
    }

    function renderizarMapa(hosts) {
        const svg = document.getElementById('svg-mapa');
        const camada = document.getElementById('mapa-zoom-layer');
        camada.innerHTML = '';
        const ns = 'http://www.w3.org/2000/svg';

        // Usa o tamanho real do card em vez de um viewBox fixo -- antes
        // sobrava bastante area vazia em cards largos porque o viewBox
        // (400x320) nao acompanhava a largura de verdade do container.
        const retangulo = svg.getBoundingClientRect();
        const largura = Math.max(400, retangulo.width || 800);
        const altura = Math.max(320, retangulo.height || 560);
        svg.setAttribute('viewBox', '0 0 ' + largura + ' ' + altura);

        const cx = largura / 2, cy = altura / 2, raio = Math.min(largura, altura) / 2 - 60;

        function el(tag, attrs) {
            const e = document.createElementNS(ns, tag);
            Object.keys(attrs).forEach(k => e.setAttribute(k, attrs[k]));
            return e;
        }

        const centro = el('circle', { cx: cx, cy: cy, r: 16, fill: '#0d1b2a' });
        camada.appendChild(centro);
        const centroLabel = el('text', { x: cx, y: cy + 32, 'text-anchor': 'middle', class: 'ipscan-map-label' });
        centroLabel.textContent = 'Este servidor';
        camada.appendChild(centroLabel);

        const n = hosts.length || 1;
        hosts.forEach(function (h, i) {
            const ang = (2 * Math.PI * i) / n - Math.PI / 2;
            const x = cx + raio * Math.cos(ang);
            const y = cy + raio * Math.sin(ang);
            const cor = h.ativo ? '#0d6efd' : '#fd7e14';

            camada.appendChild(el('line', { x1: cx, y1: cy, x2: x, y2: y, stroke: '#dee2e6', 'stroke-width': 1 }));

            const g = el('g', { class: 'ipscan-map-node' });
            g.appendChild(el('circle', { cx: x, cy: y, r: 6, fill: cor }));
            const label = el('text', { x: x, y: y - 10, 'text-anchor': 'middle', class: 'ipscan-map-label' });
            label.textContent = h.hostname || h.ip;
            g.appendChild(label);

            const titulo = el('title', {});
            const statusAtivo = h.ativo ? ('Ativo cadastrado: ' + h.ativo.codigo_patrimonio) : 'Não cadastrado como Ativo';
            titulo.textContent = h.ip + (h.vendor ? ' -- ' + h.vendor : '') + ' -- ' + statusAtivo;
            g.appendChild(titulo);

            g.style.cursor = 'pointer';
            g.addEventListener('click', function () {
                if (h.ativo) {
                    window.open(URL_ATIVO_VER + '?id=' + h.ativo.id, '_blank');
                } else {
                    window.open(URL_ATIVO_NOVO + '?nome=' + encodeURIComponent(h.hostname || h.ip) + '&ip=' + encodeURIComponent(h.ip), '_blank');
                }
            });

            camada.appendChild(g);
        });

        resetarZoomMapa();
    }

    // ── Zoom/pan do mapa: transform num <g> só, roda do mouse + arrastar +
    // botões, sem nenhuma lib nova (o SVG já é gerado na mão acima) ──────
    let mapaEscala = 1, mapaTx = 0, mapaTy = 0;
    let arrastando = false, arrastoX = 0, arrastoY = 0;

    function aplicarTransformMapa() {
        document.getElementById('mapa-zoom-layer').setAttribute('transform', 'translate(' + mapaTx + ',' + mapaTy + ') scale(' + mapaEscala + ')');
    }

    function resetarZoomMapa() {
        mapaEscala = 1; mapaTx = 0; mapaTy = 0;
        aplicarTransformMapa();
    }

    (function initZoomMapa() {
        const svg = document.getElementById('svg-mapa');

        svg.addEventListener('wheel', function (e) {
            e.preventDefault();
            const fator = e.deltaY < 0 ? 1.15 : 1 / 1.15;
            mapaEscala = Math.min(4, Math.max(0.4, mapaEscala * fator));
            aplicarTransformMapa();
        }, { passive: false });

        svg.addEventListener('pointerdown', function (e) {
            arrastando = true;
            arrastoX = e.clientX - mapaTx;
            arrastoY = e.clientY - mapaTy;
            svg.classList.add('dragging');
            svg.setPointerCapture(e.pointerId);
        });
        svg.addEventListener('pointermove', function (e) {
            if (!arrastando) return;
            mapaTx = e.clientX - arrastoX;
            mapaTy = e.clientY - arrastoY;
            aplicarTransformMapa();
        });
        ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (evt) {
            svg.addEventListener(evt, function () { arrastando = false; svg.classList.remove('dragging'); });
        });

        document.getElementById('btn-zoom-in').addEventListener('click', function () {
            mapaEscala = Math.min(4, mapaEscala * 1.25);
            aplicarTransformMapa();
        });
        document.getElementById('btn-zoom-out').addEventListener('click', function () {
            mapaEscala = Math.max(0.4, mapaEscala / 1.25);
            aplicarTransformMapa();
        });
        document.getElementById('btn-zoom-reset').addEventListener('click', resetarZoomMapa);
    })();

    async function escanearPortas(ip) {
        document.getElementById('modalPortasIp').textContent = ip;
        document.getElementById('modalPortasCorpo').innerHTML = '<div class="text-center text-muted py-3"><i class="bi bi-hourglass-split"></i> Escaneando (pode levar até 30s)...</div>';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPortas')).show();

        try {
            const res = await fetch(URL_PORTAS, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ip=' + encodeURIComponent(ip)
            });
            const dados = await res.json();

            if (!dados.success) {
                document.getElementById('modalPortasCorpo').innerHTML = '<div class="alert alert-danger mb-0">' + (dados.message || 'Falha ao escanear.') + '</div>';
                return;
            }

            let html = '';

            if (dados.snmp) {
                html += dados.snmp.disponivel
                    ? '<div class="alert alert-success py-2 mb-2"><i class="bi bi-check-circle"></i> Responde SNMP (community padrão) -- elegível pra coleta automática em Ativos.'
                        + (dados.snmp.sys_descr ? '<div class="small font-monospace mt-1">' + dados.snmp.sys_descr + '</div>' : '') + '</div>'
                    : '<div class="alert alert-secondary py-2 mb-2"><i class="bi bi-slash-circle"></i> Não respondeu SNMP com a community padrão -- pode não suportar SNMP, estar com SNMP desligado, ou usar uma community diferente.</div>';
            }

            const portas = dados.portas || [];
            if (portas.length === 0) {
                html += '<div class="text-muted text-center py-3">Nenhuma porta aberta encontrada (entre as 100 mais comuns).</div>';
                document.getElementById('modalPortasCorpo').innerHTML = html;
                return;
            }

            html += '<table class="table table-sm mb-0"><thead><tr><th>Porta</th><th>Protocolo</th><th>Serviço</th><th>Versão</th></tr></thead><tbody>';
            portas.forEach(p => {
                html += '<tr><td>' + p.porta + '</td><td>' + p.protocolo + '</td><td>' + (p.servico || '-') + '</td><td class="small text-muted">' + (p.versao || '-') + '</td></tr>';
            });
            html += '</tbody></table>';
            document.getElementById('modalPortasCorpo').innerHTML = html;
        } catch (err) {
            document.getElementById('modalPortasCorpo').innerHTML = '<div class="alert alert-danger mb-0">Erro ao comunicar com o servidor.</div>';
        }
    }

    async function enviarWol(mac, botao) {
        botao.disabled = true;
        try {
            const res = await fetch(URL_WOL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'mac=' + encodeURIComponent(mac)
            });
            const dados = await res.json();
            toast(dados.message || (dados.success ? 'Pacote enviado.' : 'Falha ao enviar.'), !!dados.success);
        } catch (err) {
            toast('Erro ao comunicar com o servidor.', false);
        } finally {
            botao.disabled = false;
        }
    }

    document.getElementById('busca-hosts').addEventListener('input', function () {
        const termo = this.value.toLowerCase();
        document.querySelectorAll('#tabela-hosts tr[data-busca]').forEach(function (tr) {
            tr.style.display = tr.getAttribute('data-busca').includes(termo) ? '' : 'none';
        });
    });

    document.getElementById('btn-view-tabela').addEventListener('click', function () {
        this.classList.add('active');
        document.getElementById('btn-view-mapa').classList.remove('active');
        document.getElementById('view-tabela').classList.remove('d-none');
        document.getElementById('view-mapa').classList.add('d-none');
    });
    document.getElementById('btn-view-mapa').addEventListener('click', function () {
        this.classList.add('active');
        document.getElementById('btn-view-tabela').classList.remove('active');
        document.getElementById('view-mapa').classList.remove('d-none');
        document.getElementById('view-tabela').classList.add('d-none');
        // Só agora o card fica visível de verdade -- recalcula o viewBox
        // com o tamanho real (medir enquanto "d-none" sempre dá 0x0).
        renderizarMapa(hostsAtuais);
    });

    window.addEventListener('resize', function () {
        if (!document.getElementById('view-mapa').classList.contains('d-none')) {
            renderizarMapa(hostsAtuais);
        }
    });

    document.getElementById('btn-exportar-mapa').addEventListener('click', async function (e) {
        e.preventDefault();

        if (hostsAtuais.length === 0) {
            toast('Rode uma varredura primeiro.', false);
            return;
        }

        document.getElementById('exp-mapa-nome-novo').value = document.getElementById('input-cidr').value + ' -- ' + new Date().toLocaleDateString('pt-BR');
        document.getElementById('exp-mapa-select').innerHTML = '<option>Carregando...</option>';

        try {
            const res = await fetch(URL_MAPA_LISTAR);
            const mapas = await res.json();
            const select = document.getElementById('exp-mapa-select');
            select.innerHTML = mapas.length === 0
                ? '<option value="">Nenhum mapa existente</option>'
                : mapas.map(m => '<option value="' + m.id + '">' + m.nome + '</option>').join('');
        } catch (err) { /* segue com a lista vazia, "criar novo" ja fica marcado */ }

        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalExportarMapa')).show();
    });

    document.querySelectorAll('input[name="exp-mapa-destino"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            document.getElementById('exp-mapa-nome-novo').disabled = this.value !== 'novo';
            document.getElementById('exp-mapa-select').disabled = this.value !== 'existente';
        });
    });

    document.getElementById('btn-confirmar-exportar-mapa').addEventListener('click', async function () {
        const destino = document.querySelector('input[name="exp-mapa-destino"]:checked').value;
        const btn = this;
        btn.disabled = true;

        try {
            let corpo = 'hosts=' + encodeURIComponent(JSON.stringify(hostsAtuais));
            if (destino === 'novo') {
                const nome = document.getElementById('exp-mapa-nome-novo').value.trim();
                if (!nome) { toast('Informe um nome pro mapa.', false); btn.disabled = false; return; }
                corpo += '&nome_novo=' + encodeURIComponent(nome);
            } else {
                const mapaId = document.getElementById('exp-mapa-select').value;
                if (!mapaId) { toast('Escolha um mapa.', false); btn.disabled = false; return; }
                corpo += '&mapa_id=' + encodeURIComponent(mapaId);
            }

            const res = await fetch(URL_MAPA_IMPORTAR, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: corpo
            });
            const dados = await res.json();

            if (!dados.success) {
                toast(dados.message || 'Falha ao exportar.', false);
                btn.disabled = false;
                return;
            }

            window.location.href = URL_MAPA_VER + '?id=' + dados.mapa_id;
        } catch (err) {
            toast('Erro ao comunicar com o servidor.', false);
            btn.disabled = false;
        }
    });

    document.getElementById('link-export-csv').addEventListener('click', function (e) {
        e.preventDefault();
        let csv = 'IP,Hostname,MAC,Fabricante,Ativo Cadastrado\n';
        hostsAtuais.forEach(h => {
            const cadastro = h.ativo ? h.ativo.codigo_patrimonio : 'Não cadastrado';
            csv += [h.ip, h.hostname || '', h.mac || '', (h.vendor || '').replace(/,/g, ' '), cadastro].map(v => '"' + v + '"').join(',') + '\n';
        });
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'ip-scanner.csv';
        link.click();
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - IP Scanner';
require __DIR__ . '/../layouts/main.php';
