<?php
ob_start();

use App\Services\AtivoService;

$statusCores = [
    'ativo' => 'success',
    'manutencao' => 'warning',
    'estoque' => 'secondary',
    'baixado' => 'danger',
];
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-boxes me-1"></i> Ativos de TI</h4>
        <small class="text-muted">Controle do parque de computadores, monitores, impressoras, switches e servidores.</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('/ativos/lista') ?>" class="btn btn-outline-secondary"><i class="bi bi-list-ul"></i> Ver lista</a>
        <a href="<?= url('/ativos/novo') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Novo Ativo</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php foreach (AtivoService::TIPOS as $chave => $info): ?>
        <div class="col-md-4" style="flex:1 1 200px">
            <a href="<?= url('/ativos/lista?tipo=' . $chave) ?>" class="card border-0 shadow-sm text-decoration-none h-100">
                <div class="card-body text-center">
                    <i class="bi <?= $info['icone'] ?> display-6 text-primary"></i>
                    <div class="fs-3 fw-bold mt-2"><?= (int)($por_tipo[$chave] ?? 0) ?></div>
                    <div class="text-muted small"><?= htmlspecialchars($info['label']) ?></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white"><strong>Por status</strong></div>
            <div class="card-body">
                <?php if (empty($por_status)): ?>
                    <p class="text-muted mb-0">Nenhum ativo cadastrado ainda.</p>
                <?php else: ?>
                    <?php foreach (AtivoService::STATUS as $chave => $label): ?>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span><span class="badge text-bg-<?= $statusCores[$chave] ?>">&nbsp;</span> <?= htmlspecialchars($label) ?></span>
                            <strong><?= (int)($por_status[$chave] ?? 0) ?></strong>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><strong>Coleta via SNMP</strong></div>
            <div class="card-body">
                <form method="post" action="<?= url('/ativos/snmp/config') ?>" class="row g-2 align-items-end mb-3">
                    <div class="col-auto">
                        <label class="form-label small mb-0">Community padrão</label>
                        <input type="text" name="comunidade" class="form-control form-control-sm" value="<?= htmlspecialchars($comunidadePadrao) ?>" style="width:160px">
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-sm btn-outline-secondary">Salvar</button>
                    </div>
                </form>
                <div class="d-flex justify-content-between align-items-center small text-muted">
                    <span><i class="bi bi-info-circle"></i> Coleta periódica (a cada 30 min) dos ativos com SNMP habilitado.</span>
                    <?php if ($coletaSnmpAtiva): ?>
                        <span class="text-success"><i class="bi bi-check-circle"></i> Ativa</span>
                    <?php else: ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="botaoAtivarColetaSnmp">Ativar coleta periódica</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Cadastrados recentemente</strong>
                <span class="text-muted small">Total: <?= (int)$total ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentes)): ?>
                    <p class="text-muted p-3 mb-0">Nenhum ativo cadastrado ainda. <a href="<?= url('/ativos/novo') ?>">Cadastre o primeiro</a>.</p>
                <?php else: ?>
                    <table class="table table-hover align-middle mb-0">
                        <tbody>
                            <?php foreach ($recentes as $a): ?>
                                <tr>
                                    <td class="font-monospace small"><?= htmlspecialchars($a['codigo_patrimonio']) ?></td>
                                    <td><?= htmlspecialchars($a['nome']) ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars(AtivoService::TIPOS[$a['tipo']]['label']) ?></td>
                                    <td class="text-end">
                                        <a href="<?= url('/ativos/ver?id=' . $a['id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
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

<script>
(function () {
    const botao = document.getElementById('botaoAtivarColetaSnmp');
    if (!botao) return;

    botao.addEventListener('click', async function () {
        botao.disabled = true;
        try {
            const res = await fetch(<?= json_encode(url('/ativos/snmp/ativar-coleta')) ?>, { method: 'POST' });
            const dados = await res.json();
            alert(dados.message || (dados.success ? 'Ativado.' : 'Falha ao ativar.'));
            location.reload();
        } catch (e) {
            botao.disabled = false;
        }
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Ativos de TI - Dashboard';

require __DIR__ . '/../layouts/main.php';
