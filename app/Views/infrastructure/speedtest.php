<?php
ob_start();

use App\Components\Alert;
use App\Components\Badge;
?>

<style>
.tech-card {
    background: #0f172a;
    border-radius: 16px;
    border: 1px solid #1e293b;
    color: #e2e8f0;
}
.tech-label { font-size:11px; text-transform:uppercase; letter-spacing:.08em; color:#94a3b8; }
.tech-num { font-family:'SFMono-Regular',Consolas,monospace; font-weight:700; }
</style>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-speedometer2 me-1"></i> Teste de Velocidade</h4>
        <small class="text-muted">Mede a velocidade de internet do servidor via Speedtest CLI (Ookla).</small>
    </div>
    <?php if ($instalado): ?>
    <button type="button" class="btn btn-outline-primary" id="botaoTestar">
        <i class="bi bi-play-circle"></i> Testar agora
    </button>
    <?php endif; ?>
</div>

<?php if (!$instalado): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body text-center py-5">
            <i class="bi bi-speedometer2 display-5 text-muted"></i>
            <h5 class="mt-3">Speedtest CLI não está instalado neste servidor</h5>
            <p class="text-muted">
                Adiciona o repositório oficial da Ookla e instala o Speedtest CLI.
            </p>
            <button type="button" class="btn btn-primary" id="botaoInstalar">
                <i class="bi bi-download"></i> Instalar Speedtest CLI
            </button>
        </div>
    </div>
<?php else: ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="tech-card p-3">
                <div class="tech-label mb-1"><i class="bi bi-arrow-down-circle me-1"></i> Download</div>
                <div class="tech-num" style="font-size:26px; color:#22c55e">
                    <?= $ultimo ? number_format((float)$ultimo['download_mbps'], 2, ',', '.') . ' Mbps' : '—' ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="tech-card p-3">
                <div class="tech-label mb-1"><i class="bi bi-arrow-up-circle me-1"></i> Upload</div>
                <div class="tech-num" style="font-size:26px; color:#06b6d4">
                    <?= $ultimo ? number_format((float)$ultimo['upload_mbps'], 2, ',', '.') . ' Mbps' : '—' ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="tech-card p-3">
                <div class="tech-label mb-1"><i class="bi bi-hourglass-split me-1"></i> Ping / Jitter</div>
                <div class="tech-num" style="font-size:26px">
                    <?= $ultimo ? number_format((float)$ultimo['ping_ms'], 1, ',', '.') . ' ms' : '—' ?>
                    <?php if ($ultimo): ?>
                        <span class="text-muted" style="font-size:14px">(jitter <?= number_format((float)$ultimo['jitter_ms'], 1, ',', '.') ?> ms)</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($ultimo): ?>
        <div class="text-muted small mb-4">
            Último teste em <?= htmlspecialchars(data_br($ultimo['executado_em'])) ?>
            <?php if ($ultimo['servidor']): ?> · servidor <?= htmlspecialchars($ultimo['servidor']) ?><?php endif; ?>
            <?php if ($ultimo['isp']): ?> · ISP <?= htmlspecialchars($ultimo['isp']) ?><?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <?php if (!$periodicoAtivo): ?>
                <div class="d-flex justify-content-between align-items-center">
                    <div class="small text-muted">
                        <i class="bi bi-info-circle"></i> Não há teste automático diário configurado.
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="botaoPeriodico">
                        Ativar teste diário
                    </button>
                </div>
            <?php else: ?>
                <div class="text-success"><i class="bi bi-check-circle"></i> Teste diário ativo.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <strong><i class="bi bi-clock-history"></i> Histórico</strong>
        </div>
        <div class="card-body p-0">
            <?php if (empty($historico)): ?>
                <div class="text-center text-muted py-4">Nenhum teste executado ainda.</div>
            <?php else: ?>
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Quando</th>
                            <th>Status</th>
                            <th class="text-end">Download</th>
                            <th class="text-end">Upload</th>
                            <th class="text-end">Ping</th>
                            <th>Servidor</th>
                            <th>ISP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historico as $h): ?>
                            <tr>
                                <td class="small"><?= htmlspecialchars(data_br($h['executado_em'])) ?></td>
                                <td>
                                    <?= $h['status'] === 'concluido' ? Badge::make('Concluído', 'success') : Badge::make('Erro', 'danger') ?>
                                </td>
                                <td class="text-end font-monospace"><?= $h['download_mbps'] !== null ? number_format((float)$h['download_mbps'], 2, ',', '.') . ' Mbps' : '—' ?></td>
                                <td class="text-end font-monospace"><?= $h['upload_mbps'] !== null ? number_format((float)$h['upload_mbps'], 2, ',', '.') . ' Mbps' : '—' ?></td>
                                <td class="text-end font-monospace"><?= $h['ping_ms'] !== null ? number_format((float)$h['ping_ms'], 1, ',', '.') . ' ms' : '—' ?></td>
                                <td class="small"><?= htmlspecialchars($h['servidor'] ?? '—') ?></td>
                                <td class="small"><?= htmlspecialchars($h['isp'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

<div class="modal fade" id="modalAcao" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAcaoTitulo">Processando</h5>
            </div>
            <div class="modal-body" id="modalAcaoCorpo">
                <div class="text-center text-muted py-3"><i class="bi bi-hourglass-split"></i> Aguarde, pode levar até 1 minuto...</div>
            </div>
            <div class="modal-footer" id="modalAcaoRodape" style="display:none">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="location.reload()">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const URLS = {
        instalar: <?= json_encode(url('/infraestrutura/velocidade/instalar')) ?>,
        testar: <?= json_encode(url('/infraestrutura/velocidade/testar')) ?>,
        periodico: <?= json_encode(url('/infraestrutura/velocidade/periodico')) ?>,
    };

    async function executar(url, titulo) {
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAcao'));
        const corpo = document.getElementById('modalAcaoCorpo');
        const rodape = document.getElementById('modalAcaoRodape');

        document.getElementById('modalAcaoTitulo').textContent = titulo;
        corpo.innerHTML = '<div class="text-center text-muted py-3"><i class="bi bi-hourglass-split"></i> Aguarde, pode levar até 1 minuto...</div>';
        rodape.style.display = 'none';
        modal.show();

        try {
            const res = await fetch(url, { method: 'POST' });
            const dados = await res.json();

            const cor = dados.success ? 'success' : 'danger';
            const icone = dados.success ? 'check-circle' : 'x-circle';
            corpo.innerHTML = '<div class="alert alert-' + cor + '"><i class="bi bi-' + icone + '"></i> ' +
                String(dados.message || '').replace(/</g, '&lt;') + '</div>';
        } catch (e) {
            corpo.innerHTML = '<div class="alert alert-danger mb-0">Erro ao comunicar com o servidor.</div>';
        } finally {
            rodape.style.display = '';
        }
    }

    const botaoInstalar = document.getElementById('botaoInstalar');
    if (botaoInstalar) {
        botaoInstalar.addEventListener('click', function () {
            if (!confirm('Instalar o Speedtest CLI neste servidor?')) return;
            executar(URLS.instalar, 'Instalando Speedtest CLI');
        });
    }

    const botaoTestar = document.getElementById('botaoTestar');
    if (botaoTestar) {
        botaoTestar.addEventListener('click', function () {
            executar(URLS.testar, 'Testando velocidade');
        });
    }

    const botaoPeriodico = document.getElementById('botaoPeriodico');
    if (botaoPeriodico) {
        botaoPeriodico.addEventListener('click', function () {
            executar(URLS.periodico, 'Ativando teste diário');
        });
    }
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - Teste de Velocidade';

require __DIR__ . '/../layouts/main.php';
