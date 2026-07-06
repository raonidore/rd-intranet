<?php
ob_start();

use App\Components\Alert;
?>

<style>
.tech-card {
    background: #0f172a;
    border-radius: 16px;
    border: 1px solid #1e293b;
    color: #e2e8f0;
}
.tech-num { font-family:'SFMono-Regular',Consolas,monospace; font-size:26px; font-weight:700; }
.tech-label { font-size:11px; text-transform:uppercase; letter-spacing:.08em; color:#94a3b8; }
</style>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-speedometer me-1"></i> Tráfego de Banda</h4>
        <small class="text-muted">Taxa de download/upload ao vivo por interface (atualiza a cada 2s).</small>
    </div>
    <a href="<?= url('/infraestrutura/rede') ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Interfaces
    </a>
</div>

<div class="row g-3" id="trafego-container">
    <div class="col-12 text-center text-muted py-5">
        <i class="bi bi-hourglass-split display-6"></i>
        <p class="mt-2">Coletando primeira amostra...</p>
    </div>
</div>

<script>
(function () {
    const API_URL = '<?= url('/infraestrutura/rede/trafego/api') ?>';
    const container = document.getElementById('trafego-container');
    let anterior = null;

    function formatarTaxa(bytesPorSegundo) {
        if (bytesPorSegundo >= 1048576) return (bytesPorSegundo / 1048576).toFixed(2) + ' MB/s';
        if (bytesPorSegundo >= 1024) return (bytesPorSegundo / 1024).toFixed(1) + ' KB/s';
        return Math.max(0, Math.round(bytesPorSegundo)) + ' B/s';
    }

    function render(taxas) {
        if (!taxas.length) {
            container.innerHTML = '<div class="col-12 text-center text-muted py-5"><i class="bi bi-diagram-3 display-6"></i><p class="mt-2">Nenhuma interface encontrada</p></div>';
            return;
        }
        container.innerHTML = taxas.map(function (t) {
            return '<div class="col-md-4"><div class="card tech-card h-100"><div class="card-body">' +
                '<div class="tech-label mb-2">' + t.nome + '</div>' +
                '<div class="d-flex justify-content-between mb-1"><span><i class="bi bi-arrow-down text-info"></i> Download</span><span class="tech-num" style="font-size:16px">' + formatarTaxa(t.rx) + '</span></div>' +
                '<div class="d-flex justify-content-between"><span><i class="bi bi-arrow-up text-warning"></i> Upload</span><span class="tech-num" style="font-size:16px">' + formatarTaxa(t.tx) + '</span></div>' +
                '</div></div></div>';
        }).join('');
    }

    async function coletar() {
        try {
            const res = await fetch(API_URL);
            const dados = await res.json();

            if (anterior) {
                const deltaT = dados.timestamp - anterior.timestamp;
                const taxas = dados.interfaces.map(function (i) {
                    const antes = anterior.interfaces.find(function (a) { return a.nome === i.nome; });
                    const rx = antes && deltaT > 0 ? (i.rx_bytes - antes.rx_bytes) / deltaT : 0;
                    const tx = antes && deltaT > 0 ? (i.tx_bytes - antes.tx_bytes) / deltaT : 0;
                    return { nome: i.nome, rx: rx, tx: tx };
                });
                render(taxas);
            }

            anterior = dados;
        } catch (e) {
            console.warn('Falha ao coletar tráfego:', e);
        }
    }

    coletar();
    setInterval(coletar, 2000);
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - Tráfego de Banda';

require __DIR__ . '/../layouts/main.php';
