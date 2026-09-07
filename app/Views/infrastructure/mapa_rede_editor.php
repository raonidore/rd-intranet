<?php

use App\Components\Alert;

ob_start();

$dados = $mapa['dados'];
?>

<style>
.mre-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; background: #fff; border-radius: 14px; box-shadow: 0 4px 14px rgba(0,0,0,.06); padding: 12px 18px; margin-bottom: 14px; }
.mre-titulo { font-size: 18px; font-weight: 600; cursor: pointer; }
.mre-titulo:hover { color: #0d6efd; }
.mre-status { font-size: 12px; color: #6c757d; }
.mre-status.salvando { color: #fd7e14; }
.mre-status.salvo { color: #198754; }

.mre-canvas-wrap { position: relative; background: #fff; border-radius: 14px; box-shadow: 0 4px 14px rgba(0,0,0,.06); overflow: hidden; min-height: 72vh; }
.mre-canvas-wrap svg { width: 100%; height: 72vh; cursor: grab; touch-action: none; }
.mre-canvas-wrap svg.dragging { cursor: grabbing; }
.mre-zoom-controls { position: absolute; top: 12px; right: 12px; display: flex; flex-direction: column; gap: 4px; z-index: 3; }
.mre-zoom-controls button { width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e9ecef; background: #fff; box-shadow: 0 2px 6px rgba(0,0,0,.08); }

.mre-node circle { stroke: #fff; stroke-width: 2; cursor: pointer; }
.mre-node text { font-size: 10px; fill: #343a40; pointer-events: none; }
.mre-node.selecionado circle { stroke: #212529; stroke-width: 3; }
.mre-edge { stroke: #adb5bd; stroke-width: 2; }
.mre-edge-label { font-size: 9px; fill: #6c757d; }

.mre-legenda { position: absolute; bottom: 10px; left: 14px; display: flex; gap: 12px; flex-wrap: wrap; font-size: 11px; color: #6c757d; z-index: 2; max-width: 60%; }
.mre-legenda span { display: inline-flex; align-items: center; gap: 4px; }
.mre-legenda i { width: 9px; height: 9px; border-radius: 50%; display: inline-block; }

.mre-painel { position: absolute; top: 12px; left: 12px; width: 280px; background: #fff; border: 1px solid #e9ecef; border-radius: 12px; box-shadow: 0 6px 18px rgba(0,0,0,.12); padding: 16px; z-index: 4; }
</style>

<div class="mre-toolbar">
    <div>
        <a href="<?= url('/infraestrutura/rede/mapa') ?>" class="text-decoration-none small text-muted d-block mb-1">
            <i class="bi bi-arrow-left"></i> Mapa de Rede
        </a>
        <span class="mre-titulo" id="mre-titulo" title="Clique para renomear"><?= htmlspecialchars($mapa['nome']) ?></span>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="mre-status" id="mre-status">Salvo</span>
        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-modo-conectar">
            <i class="bi bi-diagram-2 me-1"></i> Modo Conectar
        </button>
        <button type="button" class="btn btn-sm btn-primary" id="btn-novo-no">
            <i class="bi bi-plus-lg me-1"></i> Novo nó
        </button>
    </div>
</div>

<?= Alert::flash() ?>

<div class="mre-canvas-wrap" id="mre-canvas-wrap">
    <div class="mre-zoom-controls">
        <button type="button" id="mre-zoom-in" title="Aproximar"><i class="bi bi-plus-lg"></i></button>
        <button type="button" id="mre-zoom-out" title="Afastar"><i class="bi bi-dash-lg"></i></button>
        <button type="button" id="mre-zoom-reset" title="Restaurar"><i class="bi bi-aspect-ratio"></i></button>
    </div>
    <div class="mre-legenda">
        <span><i style="background:#0d6efd"></i> Roteador</span>
        <span><i style="background:#6f42c1"></i> Switch</span>
        <span><i style="background:#198754"></i> Servidor</span>
        <span><i style="background:#fd7e14"></i> Computador</span>
        <span><i style="background:#20c997"></i> Impressora</span>
        <span><i style="background:#6c757d"></i> Nuvem/Internet</span>
        <span><i style="background:#adb5bd"></i> Outro</span>
    </div>
    <svg id="mre-svg">
        <g id="mre-zoom-layer"></g>
    </svg>
</div>

<!-- Modal: novo nó manual -->
<div class="modal fade" id="modalNovoNo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-1"></i> Novo nó</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small">Nome</label>
                    <input type="text" id="novo-no-nome" class="form-control" placeholder="ex: Roteador ISP, Internet, Filial SP">
                </div>
                <div class="mb-3">
                    <label class="form-label small">Tipo</label>
                    <select id="novo-no-tipo" class="form-select">
                        <option value="roteador">Roteador</option>
                        <option value="switch">Switch</option>
                        <option value="servidor">Servidor</option>
                        <option value="computador" selected>Computador</option>
                        <option value="impressora">Impressora</option>
                        <option value="nuvem">Nuvem/Internet</option>
                        <option value="outro">Outro</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small">IP (opcional)</label>
                    <input type="text" id="novo-no-ip" class="form-control font-monospace" placeholder="192.168.0.1">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-confirmar-novo-no">Adicionar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: renomear mapa -->
<div class="modal fade" id="modalRenomear" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Renomear mapa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small">Nome</label>
                    <input type="text" id="renomear-nome" class="form-control" value="<?= htmlspecialchars($mapa['nome']) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label small">Descrição</label>
                    <input type="text" id="renomear-descricao" class="form-control" value="<?= htmlspecialchars($mapa['descricao'] ?? '') ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-confirmar-renomear">Salvar</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const MAPA_ID = <?= (int)$mapa['id'] ?>;
    const URL_SALVAR = <?= json_encode(url('/infraestrutura/rede/mapa/salvar')) ?>;
    const URL_RENOMEAR = <?= json_encode(url('/infraestrutura/rede/mapa/renomear')) ?>;
    const URL_ATIVO_VER = <?= json_encode(url('/ativos/ver')) ?>;

    const CORES = { roteador: '#0d6efd', switch: '#6f42c1', servidor: '#198754', computador: '#fd7e14', impressora: '#20c997', nuvem: '#6c757d', outro: '#adb5bd' };

    let dados = <?= json_encode($dados) ?>;
    let noSelecionadoId = null;
    let modoConectar = false;
    let conectarOrigemId = null;

    const svg = document.getElementById('mre-svg');
    const camada = document.getElementById('mre-zoom-layer');
    const ns = 'http://www.w3.org/2000/svg';

    function el(tag, attrs) {
        const e = document.createElementNS(ns, tag);
        Object.keys(attrs || {}).forEach(k => e.setAttribute(k, attrs[k]));
        return e;
    }

    // ── Autosave (debounce) ──────────────────────────────────────────────
    let salvarTimeout = null;
    function agendarSalvar() {
        document.getElementById('mre-status').textContent = 'Salvando...';
        document.getElementById('mre-status').className = 'mre-status salvando';
        clearTimeout(salvarTimeout);
        salvarTimeout = setTimeout(salvarAgora, 800);
    }

    async function salvarAgora() {
        try {
            const res = await fetch(URL_SALVAR, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + MAPA_ID + '&dados=' + encodeURIComponent(JSON.stringify(dados))
            });
            const resposta = await res.json();
            document.getElementById('mre-status').textContent = resposta.success ? 'Salvo' : 'Falha ao salvar';
            document.getElementById('mre-status').className = 'mre-status ' + (resposta.success ? 'salvo' : '');
        } catch (err) {
            document.getElementById('mre-status').textContent = 'Falha ao salvar';
        }
    }

    // ── Zoom/pan do fundo (mesma técnica do IP Scanner) ──────────────────
    let escala = 1, tx = 0, ty = 0;
    let arrastandoFundo = false, arrastoX = 0, arrastoY = 0;

    function aplicarTransform() {
        camada.setAttribute('transform', 'translate(' + tx + ',' + ty + ') scale(' + escala + ')');
    }

    function resetarZoom() { escala = 1; tx = 0; ty = 0; aplicarTransform(); }

    svg.addEventListener('wheel', function (e) {
        e.preventDefault();
        const fator = e.deltaY < 0 ? 1.15 : 1 / 1.15;
        escala = Math.min(4, Math.max(0.3, escala * fator));
        aplicarTransform();
    }, { passive: false });

    svg.addEventListener('pointerdown', function (e) {
        if (e.target.closest('.mre-node')) return;
        arrastandoFundo = true;
        arrastoX = e.clientX - tx;
        arrastoY = e.clientY - ty;
        svg.classList.add('dragging');
        svg.setPointerCapture(e.pointerId);
    });
    svg.addEventListener('pointermove', function (e) {
        if (!arrastandoFundo) return;
        tx = e.clientX - arrastoX;
        ty = e.clientY - arrastoY;
        aplicarTransform();
    });
    ['pointerup', 'pointercancel'].forEach(evt => svg.addEventListener(evt, function () {
        arrastandoFundo = false;
        svg.classList.remove('dragging');
    }));

    document.getElementById('mre-zoom-in').addEventListener('click', () => { escala = Math.min(4, escala * 1.25); aplicarTransform(); });
    document.getElementById('mre-zoom-out').addEventListener('click', () => { escala = Math.max(0.3, escala / 1.25); aplicarTransform(); });
    document.getElementById('mre-zoom-reset').addEventListener('click', resetarZoom);

    // ── Desenho ────────────────────────────────────────────────────────
    function noPorId(id) { return dados.nos.find(n => n.id === id); }

    function desenhar() {
        camada.innerHTML = '';

        (dados.conexoes || []).forEach(function (c) {
            const a = noPorId(c.origem), b = noPorId(c.destino);
            if (!a || !b) return;
            camada.appendChild(el('line', { class: 'mre-edge', x1: a.x, y1: a.y, x2: b.x, y2: b.y }));
            if (c.rotulo) {
                const label = el('text', { class: 'mre-edge-label', x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 - 4, 'text-anchor': 'middle' });
                label.textContent = c.rotulo;
                camada.appendChild(label);
            }
        });

        (dados.nos || []).forEach(function (n) {
            const g = el('g', { class: 'mre-node' + (n.id === noSelecionadoId ? ' selecionado' : ''), 'data-id': n.id });
            g.appendChild(el('circle', { cx: n.x, cy: n.y, r: 14, fill: CORES[n.tipo] || CORES.outro }));
            const label = el('text', { x: n.x, y: n.y + 26, 'text-anchor': 'middle' });
            label.textContent = n.nome || n.ip || '(sem nome)';
            g.appendChild(label);

            g.addEventListener('pointerdown', function (e) {
                e.stopPropagation();
                iniciarDragNo(n, e);
            });
            g.addEventListener('click', function (e) {
                e.stopPropagation();
                onClickNo(n);
            });

            camada.appendChild(g);
        });
    }

    // ── Arrastar nó ────────────────────────────────────────────────────
    let arrastandoNo = null;

    function iniciarDragNo(no, evento) {
        if (modoConectar) return;
        arrastandoNo = no;
        svg.setPointerCapture(evento.pointerId);

        function mover(e) {
            const pt = pontoSvg(e.clientX, e.clientY);
            no.x = Math.round(pt.x);
            no.y = Math.round(pt.y);
            desenhar();
        }
        function soltar() {
            svg.removeEventListener('pointermove', mover);
            svg.removeEventListener('pointerup', soltar);
            arrastandoNo = null;
            agendarSalvar();
        }
        svg.addEventListener('pointermove', mover);
        svg.addEventListener('pointerup', soltar);
    }

    function pontoSvg(clientX, clientY) {
        const ret = svg.getBoundingClientRect();
        return { x: (clientX - ret.left - tx) / escala, y: (clientY - ret.top - ty) / escala };
    }

    // ── Clique num nó: conectar ou abrir painel ──────────────────────────
    function onClickNo(no) {
        if (modoConectar) {
            if (!conectarOrigemId) {
                conectarOrigemId = no.id;
                toast('Agora clique no segundo nó pra conectar.', true);
                return;
            }
            if (conectarOrigemId === no.id) {
                conectarOrigemId = null;
                return;
            }
            const rotulo = prompt('Rótulo da conexão (opcional):', '') || '';
            dados.conexoes.push({ id: 'c' + Math.random().toString(16).slice(2), origem: conectarOrigemId, destino: no.id, rotulo: rotulo });
            conectarOrigemId = null;
            desenhar();
            agendarSalvar();
            return;
        }

        noSelecionadoId = no.id;
        desenhar();
        abrirPainelNo(no);
    }

    function abrirPainelNo(no) {
        fecharPainelNo();
        const painel = document.createElement('div');
        painel.className = 'mre-painel';
        painel.id = 'mre-painel-no';

        let linkAtivo = '';
        if (no.ativo_id) {
            linkAtivo = '<a href="' + URL_ATIVO_VER + '?id=' + no.ativo_id + '" target="_blank" class="small d-block mb-2"><i class="bi bi-box-arrow-up-right me-1"></i>Ver Ativo cadastrado</a>';
        }

        painel.innerHTML =
            '<div class="d-flex justify-content-between align-items-center mb-2">' +
                '<strong>Detalhes do nó</strong>' +
                '<button type="button" class="btn-close" id="mre-painel-fechar"></button>' +
            '</div>' +
            '<div class="mb-2">' +
                '<label class="form-label small mb-1">Nome</label>' +
                '<input type="text" class="form-control form-control-sm" id="mre-painel-nome" value="' + (no.nome || '').replace(/"/g, '&quot;') + '">' +
            '</div>' +
            '<div class="mb-2">' +
                '<label class="form-label small mb-1">Tipo</label>' +
                '<select class="form-select form-select-sm" id="mre-painel-tipo">' +
                    Object.keys(CORES).map(t => '<option value="' + t + '"' + (t === no.tipo ? ' selected' : '') + '>' + t.charAt(0).toUpperCase() + t.slice(1) + '</option>').join('') +
                '</select>' +
            '</div>' +
            '<div class="mb-2">' +
                '<label class="form-label small mb-1">IP</label>' +
                '<input type="text" class="form-control form-control-sm font-monospace" id="mre-painel-ip" value="' + (no.ip || '') + '"' + (no.origem === 'scanner' ? ' readonly' : '') + '>' +
            '</div>' +
            (no.mac ? '<div class="small text-muted mb-1">MAC: ' + no.mac + '</div>' : '') +
            (no.vendor ? '<div class="small text-muted mb-2">Fabricante: ' + no.vendor + '</div>' : '') +
            linkAtivo +
            '<button type="button" class="btn btn-sm btn-outline-danger w-100 mt-2" id="mre-painel-excluir"><i class="bi bi-trash me-1"></i>Excluir nó</button>';

        document.getElementById('mre-canvas-wrap').appendChild(painel);

        document.getElementById('mre-painel-fechar').addEventListener('click', fecharPainelNo);
        document.getElementById('mre-painel-nome').addEventListener('input', function () { no.nome = this.value; desenhar(); agendarSalvar(); });
        document.getElementById('mre-painel-tipo').addEventListener('change', function () { no.tipo = this.value; desenhar(); agendarSalvar(); });
        document.getElementById('mre-painel-ip').addEventListener('input', function () { no.ip = this.value; agendarSalvar(); });
        document.getElementById('mre-painel-excluir').addEventListener('click', function () {
            if (!confirm('Excluir este nó e as conexões dele?')) return;
            dados.nos = dados.nos.filter(n => n.id !== no.id);
            dados.conexoes = dados.conexoes.filter(c => c.origem !== no.id && c.destino !== no.id);
            fecharPainelNo();
            noSelecionadoId = null;
            desenhar();
            agendarSalvar();
        });
    }

    function fecharPainelNo() {
        const existente = document.getElementById('mre-painel-no');
        if (existente) existente.remove();
    }

    svg.addEventListener('click', function () {
        noSelecionadoId = null;
        fecharPainelNo();
        desenhar();
    });

    // ── Modo conectar ──────────────────────────────────────────────────
    document.getElementById('btn-modo-conectar').addEventListener('click', function () {
        modoConectar = !modoConectar;
        conectarOrigemId = null;
        this.classList.toggle('btn-primary', modoConectar);
        this.classList.toggle('btn-outline-primary', !modoConectar);
        toast(modoConectar ? 'Modo conectar ativado -- clique em dois nós.' : 'Modo conectar desativado.', true);
    });

    // ── Novo nó manual ─────────────────────────────────────────────────
    document.getElementById('btn-novo-no').addEventListener('click', function () {
        document.getElementById('novo-no-nome').value = '';
        document.getElementById('novo-no-ip').value = '';
        document.getElementById('novo-no-tipo').value = 'computador';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNovoNo')).show();
    });

    document.getElementById('btn-confirmar-novo-no').addEventListener('click', function () {
        const nome = document.getElementById('novo-no-nome').value.trim();
        if (!nome) { toast('Informe um nome.', false); return; }

        const ret = svg.getBoundingClientRect();
        const centro = pontoSvg(ret.left + ret.width / 2, ret.top + ret.height / 2);

        dados.nos.push({
            id: 'n' + Math.random().toString(16).slice(2),
            nome: nome,
            ip: document.getElementById('novo-no-ip').value.trim(),
            mac: '',
            vendor: '',
            tipo: document.getElementById('novo-no-tipo').value,
            ativo_id: null,
            origem: 'manual',
            x: Math.round(centro.x + (Math.random() - 0.5) * 60),
            y: Math.round(centro.y + (Math.random() - 0.5) * 60)
        });

        bootstrap.Modal.getInstance(document.getElementById('modalNovoNo')).hide();
        desenhar();
        agendarSalvar();
    });

    // ── Renomear mapa ──────────────────────────────────────────────────
    document.getElementById('mre-titulo').addEventListener('click', function () {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRenomear')).show();
    });

    document.getElementById('btn-confirmar-renomear').addEventListener('click', async function () {
        const nome = document.getElementById('renomear-nome').value.trim();
        const descricao = document.getElementById('renomear-descricao').value.trim();
        if (!nome) { toast('Informe um nome.', false); return; }

        try {
            const res = await fetch(URL_RENOMEAR, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + MAPA_ID + '&nome=' + encodeURIComponent(nome) + '&descricao=' + encodeURIComponent(descricao)
            });
            const dadosResp = await res.json();
            if (dadosResp.success) {
                document.getElementById('mre-titulo').textContent = nome;
                bootstrap.Modal.getInstance(document.getElementById('modalRenomear')).hide();
            } else {
                toast('Falha ao renomear.', false);
            }
        } catch (err) {
            toast('Erro ao comunicar com o servidor.', false);
        }
    });

    function toast(msg) {
        document.getElementById('mre-status').textContent = msg;
        setTimeout(function () { document.getElementById('mre-status').textContent = 'Salvo'; document.getElementById('mre-status').className = 'mre-status salvo'; }, 2500);
    }

    if (!dados.nos) dados.nos = [];
    if (!dados.conexoes) dados.conexoes = [];
    desenhar();
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - ' . $mapa['nome'];
require __DIR__ . '/../layouts/main.php';
