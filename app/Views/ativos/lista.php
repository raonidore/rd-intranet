<?php
ob_start();

use App\Components\Alert;
use App\Components\Badge;
use App\Services\AtivoService;

$statusCores = [
    'ativo' => 'success',
    'manutencao' => 'warning',
    'estoque' => 'secondary',
    'baixado' => 'danger',
];

/** Cabeçalho clicável (quando $ordenarChave existe) que reordena a lista, preservando os filtros atuais na URL. */
function thOrdenavel(string $coluna, string $label, ?string $ordenarChave, array $filtros): string
{
    if ($ordenarChave === null) {
        return '<th data-col="' . htmlspecialchars($coluna) . '">' . htmlspecialchars($label) . '</th>';
    }

    $ehColunaAtiva = ($filtros['ordenar'] ?? '') === $ordenarChave;
    $direcaoAtual = $ehColunaAtiva ? strtolower((string)($filtros['direcao'] ?? 'asc')) : null;
    $proximaDirecao = ($direcaoAtual === 'asc') ? 'desc' : 'asc';

    $icone = 'bi-arrow-down-up text-muted';
    if ($direcaoAtual === 'asc') {
        $icone = 'bi-caret-up-fill';
    } elseif ($direcaoAtual === 'desc') {
        $icone = 'bi-caret-down-fill';
    }

    $query = $_GET;
    $query['ordenar'] = $ordenarChave;
    $query['direcao'] = $proximaDirecao;

    return '<th data-col="' . htmlspecialchars($coluna) . '">'
        . '<a href="' . htmlspecialchars(url('/ativos/lista?' . http_build_query($query))) . '" class="text-decoration-none text-dark d-inline-flex align-items-center gap-1">'
        . htmlspecialchars($label) . ' <i class="bi ' . $icone . '" style="font-size:11px"></i>'
        . '</a></th>';
}
?>

<style>
/* Ícone de alerta de disco crítico (>=90% de uso) na coluna Ações --
   pisca de propósito, é pra chamar atenção mesmo sem precisar abrir
   o Panorama da Frota. */
@keyframes piscar-alerta-disco { 50% { opacity: .25; } }
.btn-icone-alerta-disco {
    background: transparent; border: 1px solid #ef4444; color: #ef4444;
    animation: piscar-alerta-disco 1.2s infinite;
}
.btn-icone-alerta-disco:hover { background: #ef4444; color: #fff; }

/* Painel "Panorama da Frota" -- mesmo visual "console remoto" escuro
   já usado nos modais de ativos/ver.php (.hitech-panel/.hitech-topbar),
   reaproveitado aqui pela mesma convenção do projeto (cada view com
   tema escuro declara o próprio <style>). */
.hitech-panel {
    background: #0d1117; border-radius: 12px; border: 1px solid #30363d;
    box-shadow: 0 0 24px rgba(88,166,255,.08);
    font-family: 'SFMono-Regular', Consolas, 'Courier New', monospace;
    color: #c9d1d9;
}
.hitech-topbar {
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    padding: 10px 14px; background: #161b22; border-bottom: 1px solid #30363d;
}
.hitech-panel .text-muted { color: #8b949e !important; }
.frota-stat-total { font-size: 42px; font-weight: 700; color: #58a6ff; line-height: 1; }
.frota-card { background: #161b22; border: 1px solid #30363d; border-radius: 10px; padding: 14px; height: 100%; }
.frota-card h6 { color: #8b949e; text-transform: uppercase; font-size: 11px; letter-spacing: .04em; margin-bottom: 10px; }
.frota-loading, .frota-erro { padding: 40px 16px; text-align: center; color: #8b949e; }
.frota-erro { color: #f85149; }
</style>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-list-ul me-1"></i> Ativos - Lista</h4>
        <small class="text-muted"><a href="<?= url('/ativos') ?>"><i class="bi bi-arrow-left"></i> Dashboard</a></small>
    </div>
    <div class="d-flex gap-2">
        <div class="dropdown">
            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                <i class="bi bi-layout-three-columns"></i> Colunas
            </button>
            <div class="dropdown-menu p-3" id="menuColunas" style="min-width:260px">
                <?php foreach (AtivoService::COLUNAS_LISTA as $chaveColuna => $info): ?>
                    <div class="form-check">
                        <input class="form-check-input campo-coluna" type="checkbox" value="<?= htmlspecialchars($chaveColuna) ?>" id="coluna-<?= htmlspecialchars($chaveColuna) ?>">
                        <label class="form-check-label small" for="coluna-<?= htmlspecialchars($chaveColuna) ?>"><?= htmlspecialchars($info['label']) ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalPanoramaFrota">
            <i class="bi bi-graph-up"></i> Panorama da Frota
        </button>
        <a href="<?= url('/ativos/novo') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Novo Ativo</a>
    </div>
</div>

<form method="get" action="<?= url('/ativos/lista') ?>" class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Tipo</label>
                <select name="tipo" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach (AtivoService::TIPOS as $chave => $info): ?>
                        <option value="<?= $chave ?>" <?= $filtros['tipo'] === $chave ? 'selected' : '' ?>><?= htmlspecialchars($info['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach (AtivoService::STATUS as $chave => $label): ?>
                        <option value="<?= $chave ?>" <?= $filtros['status'] === $chave ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Busca</label>
                <input type="text" name="busca" class="form-control" value="<?= htmlspecialchars($filtros['busca']) ?>" placeholder="Nome, código ou nº de série">
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Filtrar</button>
            </div>
        </div>
    </div>
</form>

<form id="formLote" method="get" action="<?= url('/ativos/etiquetas/lote') ?>">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($ativos)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-boxes display-6"></i>
                    <p class="mt-2 mb-0">Nenhum ativo encontrado com esses filtros.</p>
                </div>
            <?php else: ?>
                <div class="p-2 border-bottom d-flex justify-content-between align-items-center">
                    <div class="form-check ms-2">
                        <input class="form-check-input" type="checkbox" id="marcarTodos">
                        <label class="form-check-label small text-muted" for="marcarTodos">Selecionar todos</label>
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-secondary" id="botaoEtiquetasLote" disabled>
                        <i class="bi bi-qr-code"></i> Imprimir etiquetas selecionadas
                    </button>
                </div>
                <table class="table table-hover align-middle mb-0" id="tabelaAtivos">
                    <thead>
                        <tr>
                            <th style="width:32px"></th>
                            <?php foreach (AtivoService::COLUNAS_LISTA as $chaveColuna => $info): ?>
                                <?= thOrdenavel($chaveColuna, $info['label'], $info['ordenar'], $filtros) ?>
                            <?php endforeach; ?>
                            <th class="text-end" data-col="acoes">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ativos as $a): ?>
                            <?php $detalhesLinha = json_decode($a['detalhes'] ?? '', true) ?: []; ?>
                            <tr>
                                <td><input type="checkbox" class="form-check-input checkbox-ativo" name="ids[]" value="<?= (int)$a['id'] ?>"></td>
                                <td class="font-monospace small" data-col="codigo"><?= htmlspecialchars($a['codigo_patrimonio']) ?></td>
                                <td data-col="nome"><?= htmlspecialchars($a['nome']) ?></td>
                                <td data-col="apelido"><?= htmlspecialchars($a['apelido'] ?: '—') ?></td>
                                <td data-col="tipo"><i class="bi <?= AtivoService::TIPOS[$a['tipo']]['icone'] ?>"></i> <?= htmlspecialchars(AtivoService::TIPOS[$a['tipo']]['label']) ?></td>
                                <td data-col="status"><?= Badge::make(htmlspecialchars(AtivoService::STATUS[$a['status']] ?? $a['status']), $statusCores[$a['status']] ?? 'secondary') ?></td>
                                <td data-col="condicao">
                                    <?php if ($a['origem'] === 'agente'): ?>
                                        <?php
                                            $segundosHeartbeat = AtivoService::segundosDesdeUltimoHeartbeat($a);
                                            if ($segundosHeartbeat !== null) {
                                                $dicaStatus = 'Ao vivo -- último ping há ' . AtivoService::duracaoLegivel($segundosHeartbeat);
                                            } else {
                                                $minutosAtras = AtivoService::minutosDesdeUltimoCheckin($a);
                                                $dicaStatus = $minutosAtras !== null
                                                    ? 'Sem heartbeat ainda (agente antigo) -- baseado no último check-in completo, há ' . AtivoService::duracaoLegivel($minutosAtras * 60)
                                                    : 'Nunca se comunicou';
                                            }
                                        ?>
                                        <span data-bs-toggle="tooltip" title="<?= htmlspecialchars($dicaStatus) ?>">
                                            <?= Badge::make(AtivoService::estaLigada($a) ? 'Ligado' : 'Desligado', AtivoService::estaLigada($a) ? 'success' : 'secondary') ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted" data-col="setor"><?= htmlspecialchars($a['setor_nome'] ?? '—') ?></td>
                                <td class="small text-muted" data-col="localizacao"><?= htmlspecialchars($a['localizacao_nome'] ?? '—') ?></td>
                                <td class="small" data-col="versao_agente">
                                    <?php if (empty($a['agente_versao'])): ?>
                                        <span class="text-muted">—</span>
                                    <?php elseif ($a['agente_versao'] === 'ps1'): ?>
                                        <span class="text-muted font-monospace" title="Script .ps1 -- não tem número de versão próprio, sempre baixado atualizado">script (.ps1)</span>
                                    <?php elseif ($versaoAgenteExeAtual !== '' && $a['agente_versao'] !== $versaoAgenteExeAtual): ?>
                                        <span class="font-monospace text-warning" data-bs-toggle="tooltip" title="Diferente da versão cadastrada no servidor (v<?= htmlspecialchars($versaoAgenteExeAtual) ?>). Se estiver desatualizado, se atualiza sozinho no próximo check-in.">
                                            <i class="bi bi-exclamation-triangle"></i> v<?= htmlspecialchars($a['agente_versao']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="font-monospace text-success">v<?= htmlspecialchars($a['agente_versao']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="small font-monospace" data-col="ip"><?= htmlspecialchars($a['ip'] ?: '—') ?></td>
                                <td class="small text-muted" data-col="so"><?= htmlspecialchars($detalhesLinha['sistema_operacional'] ?? '—') ?></td>
                                <td class="text-end" data-col="acoes">
                                    <?php if (!empty($discosCriticos[$a['id']])): ?>
                                        <button type="button" class="btn btn-sm btn-icone-alerta-disco me-1" title="Disco em uso crítico"
                                                data-bs-toggle="modal" data-bs-target="#modalDiscoCritico"
                                                data-nome="<?= htmlspecialchars($a['apelido'] ?: $a['nome']) ?>"
                                                data-codigo="<?= htmlspecialchars($a['codigo_patrimonio']) ?>"
                                                data-volumes='<?= htmlspecialchars(json_encode($discosCriticos[$a['id']]), ENT_QUOTES) ?>'>
                                            <i class="bi bi-exclamation-triangle-fill"></i>
                                        </button>
                                    <?php endif; ?>
                                    <div class="btn-group" role="group">
                                        <a href="<?= url('/ativos/ver?id=' . $a['id']) ?>" class="btn btn-sm btn-outline-secondary" title="Ver"><i class="bi bi-eye"></i></a>
                                        <a href="<?= url('/ativos/editar?id=' . $a['id']) ?>" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                                        <a href="<?= url('/ativos/etiqueta?id=' . $a['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Etiqueta"><i class="bi bi-qr-code"></i></a>
                                        <a href="<?= url('/ativos/excluir?id=' . $a['id']) ?>" class="btn btn-sm btn-outline-danger" title="Excluir"><i class="bi bi-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</form>

<!-- Modal único, populado via JS a partir dos data-* do botão clicado -- mesmo padrão de modal compartilhado já usado em ativos/ver.php. -->
<div class="modal fade" id="modalDiscoCritico" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill text-danger"></i> Disco em uso crítico</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2"><strong id="discoCriticoNome"></strong> <span class="text-muted font-monospace" id="discoCriticoCodigo"></span></p>
                <table class="table table-sm mb-0">
                    <thead><tr><th>Unidade</th><th>Uso</th><th class="text-end">GB usado / total</th></tr></thead>
                    <tbody id="discoCriticoCorpo"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- "Panorama da Frota" -- carregado sob demanda via fetch quando o modal abre, não no load da lista inteira. -->
<div class="modal fade" id="modalPanoramaFrota" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content hitech-panel">
            <div class="hitech-topbar">
                <h5 class="modal-title mb-0"><i class="bi bi-graph-up"></i> Panorama da Frota</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="frotaLoading" class="frota-loading">
                    <div class="spinner-border" style="color:#58a6ff"></div>
                    <p class="mt-2 mb-0">Carregando panorama da frota...</p>
                </div>
                <div id="frotaErro" class="frota-erro d-none"></div>
                <div id="frotaConteudo" class="d-none">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="frota-card text-center">
                                <h6 class="mb-1">Total de PCs</h6>
                                <div class="frota-stat-total" id="frotaTotalPcs">0</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="frota-card text-center">
                                <h6 class="mb-1">Discos em uso crítico (&ge;90%)</h6>
                                <div class="frota-stat-total" id="frotaDiscosCriticos" style="color:#e66767">0</div>
                            </div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="frota-card">
                                <h6>Sistema Operacional</h6>
                                <canvas id="graficoFrotaSo"></canvas>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="frota-card">
                                <h6>Memória RAM</h6>
                                <canvas id="graficoFrotaRam"></canvas>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="frota-card">
                                <h6>Processador (GHz)</h6>
                                <canvas id="graficoFrotaCpu"></canvas>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="frota-card">
                                <h6>Disco (SSD x HD)</h6>
                                <canvas id="graficoFrotaDisco"></canvas>
                            </div>
                        </div>
                    </div>
                    <p class="frota-loading mt-3 mb-0" style="padding:8px 0">
                        "Não coletado" = máquinas que ainda não atualizaram pro agente com coleta de SSD/HD.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
// Tudo dentro de window.addEventListener('load', ...): o bootstrap.bundle.min.js
// só é carregado no fim do layout (depois deste conteúdo), então "bootstrap"
// ainda não existe se a gente chamar new bootstrap.Tooltip(...) direto aqui --
// isso derrubava (ReferenceError) o resto do script, incluindo a lógica de
// "Selecionar todos"/imprimir em lote logo abaixo, que nunca chegava a rodar.
window.addEventListener('load', function () {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    const marcarTodos = document.getElementById('marcarTodos');
    const checkboxes = document.querySelectorAll('.checkbox-ativo');
    const botaoLote = document.getElementById('botaoEtiquetasLote');

    function atualizarBotao() {
        const algumMarcado = Array.from(checkboxes).some(function (c) { return c.checked; });
        if (botaoLote) botaoLote.disabled = !algumMarcado;
    }

    if (marcarTodos) {
        marcarTodos.addEventListener('change', function () {
            checkboxes.forEach(function (c) { c.checked = marcarTodos.checked; });
            atualizarBotao();
        });
    }

    checkboxes.forEach(function (c) { c.addEventListener('change', atualizarBotao); });
});

// Visibilidade de coluna é só preferência de tela (não precisa ida e volta
// no servidor pra isso) -- guardada no localStorage deste navegador, não
// depende do bootstrap.bundle.min.js (carregado só no fim do layout), por
// isso roda direto aqui, sem esperar 'load'.
(function () {
    const CHAVE_STORAGE = 'rd_ativos_lista_colunas';
    const colunasPadrao = <?= json_encode(array_keys(array_filter(AtivoService::COLUNAS_LISTA, fn($info) => $info['padrao']))) ?>;

    function colunasSalvas() {
        try {
            const bruto = localStorage.getItem(CHAVE_STORAGE);
            const lista = bruto ? JSON.parse(bruto) : null;
            return Array.isArray(lista) ? lista : colunasPadrao;
        } catch (e) {
            return colunasPadrao;
        }
    }

    function aplicarVisibilidade(colunasVisiveis) {
        document.querySelectorAll('#tabelaAtivos [data-col]').forEach(function (el) {
            if (el.dataset.col === 'acoes') return;
            el.style.display = colunasVisiveis.includes(el.dataset.col) ? '' : 'none';
        });
    }

    const colunasAtuais = colunasSalvas();
    aplicarVisibilidade(colunasAtuais);

    document.querySelectorAll('.campo-coluna').forEach(function (checkbox) {
        checkbox.checked = colunasAtuais.includes(checkbox.value);
        checkbox.addEventListener('change', function () {
            const marcados = Array.from(document.querySelectorAll('.campo-coluna'))
                .filter(function (c) { return c.checked; })
                .map(function (c) { return c.value; });

            localStorage.setItem(CHAVE_STORAGE, JSON.stringify(marcados));
            aplicarVisibilidade(marcados);
        });
    });
})();

(function () {
    const modalEl = document.getElementById('modalDiscoCritico');
    if (!modalEl) return;

    modalEl.addEventListener('show.bs.modal', function (e) {
        const botao = e.relatedTarget;
        const volumes = JSON.parse(botao.dataset.volumes || '[]');

        document.getElementById('discoCriticoNome').textContent = botao.dataset.nome || '';
        document.getElementById('discoCriticoCodigo').textContent = botao.dataset.codigo ? '(' + botao.dataset.codigo + ')' : '';

        const corpo = document.getElementById('discoCriticoCorpo');
        corpo.innerHTML = '';
        volumes.forEach(function (v) {
            const tdUnidade = document.createElement('td');
            tdUnidade.className = 'font-monospace';
            tdUnidade.textContent = v.unidade;

            const tdPct = document.createElement('td');
            const badge = document.createElement('span');
            badge.className = 'badge text-bg-danger';
            badge.textContent = v.pct + '%';
            tdPct.appendChild(badge);

            const tdGb = document.createElement('td');
            tdGb.className = 'text-end';
            tdGb.textContent = v.usado_gb + ' GB / ' + v.total_gb + ' GB';

            const tr = document.createElement('tr');
            tr.append(tdUnidade, tdPct, tdGb);
            corpo.appendChild(tr);
        });
    });
})();

(function () {
    const modalEl = document.getElementById('modalPanoramaFrota');
    if (!modalEl) return;

    // Paleta validada pela skill de dataviz contra o fundo escuro real
    // desta tela (#0d1117, não o #1a1a19 padrão da skill) -- CVD/contraste
    // conferidos com scripts/validate_palette.js antes de usar aqui.
    // Cor por ENTIDADE (nome do SO), nunca por posição -- um filtro que
    // muda a contagem não deve repintar quem sobrou.
    const CORES_SO = {
        'Windows 11': '#3987e5',
        'Windows 10': '#d95926',
        'Windows 8.1': '#199e70',
        'Windows 8': '#c98500',
        'Windows 7': '#d55181',
        'Windows Server': '#9085e9',
        'Outro': '#8b949e',
    };

    // RAM/CPU são buckets ORDENADOS (tiers), não identidades -- rampa de
    // um hue só (azul), claro->escuro, dentro da faixa validada pro modo
    // escuro (step 250 a 600). "Outro"/"Não informado" não é magnitude,
    // usa o cinza neutro em vez de continuar a rampa.
    const RAMPA_ORDINAL = ['#86b6ef', '#6da7ec', '#5598e7', '#3987e5', '#2a78d6', '#256abf', '#1c5cab', '#184f95'];
    const CINZA_NEUTRO = '#8b949e';

    function coresOrdinais(labels) {
        return labels.map(function (label, i) {
            return (label === 'Outro' || label === 'Não informado') ? CINZA_NEUTRO : RAMPA_ORDINAL[i % RAMPA_ORDINAL.length];
        });
    }

    // SSD x HD por entidade, não por posição -- mesma lógica de CORES_SO.
    const CORES_DISCO = {
        'SSD': '#199e70',
        'HD': '#3987e5',
        'Desconhecido': '#8b949e',
        'Não coletado': '#30363d',
    };

    const INK_SECUNDARIA = '#8b949e';
    const GRID = '#21262d';

    const opcoesComuns = {
        responsive: true,
        plugins: { legend: { labels: { color: INK_SECUNDARIA } } },
        scales: {
            x: { ticks: { color: INK_SECUNDARIA }, grid: { color: GRID } },
            y: { ticks: { color: INK_SECUNDARIA }, grid: { color: GRID }, beginAtZero: true },
        },
    };

    let graficoSo = null;
    let graficoRam = null;
    let graficoCpu = null;
    let graficoDisco = null;
    let carregado = false;

    modalEl.addEventListener('show.bs.modal', function () {
        if (carregado) return;

        const loading = document.getElementById('frotaLoading');
        const erro = document.getElementById('frotaErro');
        const conteudo = document.getElementById('frotaConteudo');

        loading.classList.remove('d-none');
        erro.classList.add('d-none');
        conteudo.classList.add('d-none');

        fetch('<?= url('/ativos/relatorio-frota') ?>')
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (dados) {
                carregado = true;
                loading.classList.add('d-none');
                conteudo.classList.remove('d-none');

                document.getElementById('frotaTotalPcs').textContent = dados.total;
                document.getElementById('frotaDiscosCriticos').textContent = dados.discosCriticos;

                graficoSo = new Chart(document.getElementById('graficoFrotaSo'), {
                    type: 'doughnut',
                    data: {
                        labels: dados.so.labels,
                        datasets: [{
                            data: dados.so.dados,
                            backgroundColor: dados.so.labels.map(function (l) { return CORES_SO[l] || CINZA_NEUTRO; }),
                        }],
                    },
                    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { color: INK_SECUNDARIA } } } },
                });

                graficoRam = new Chart(document.getElementById('graficoFrotaRam'), {
                    type: 'bar',
                    data: {
                        labels: dados.ram.labels,
                        datasets: [{ data: dados.ram.dados, backgroundColor: coresOrdinais(dados.ram.labels) }],
                    },
                    options: Object.assign({ plugins: { legend: { display: false } } }, opcoesComuns),
                });

                graficoCpu = new Chart(document.getElementById('graficoFrotaCpu'), {
                    type: 'bar',
                    data: {
                        labels: dados.cpu.labels,
                        datasets: [{ data: dados.cpu.dados, backgroundColor: coresOrdinais(dados.cpu.labels) }],
                    },
                    options: Object.assign({ plugins: { legend: { display: false } } }, opcoesComuns),
                });

                graficoDisco = new Chart(document.getElementById('graficoFrotaDisco'), {
                    type: 'doughnut',
                    data: {
                        labels: dados.disco.labels,
                        datasets: [{
                            data: dados.disco.dados,
                            backgroundColor: dados.disco.labels.map(function (l) { return CORES_DISCO[l] || CINZA_NEUTRO; }),
                        }],
                    },
                    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { color: INK_SECUNDARIA } } } },
                });
            })
            .catch(function (e) {
                loading.classList.add('d-none');
                erro.classList.remove('d-none');
                erro.textContent = 'Não foi possível carregar o panorama da frota (' + e.message + ').';
            });
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Ativos - Lista';

require __DIR__ . '/../layouts/main.php';
