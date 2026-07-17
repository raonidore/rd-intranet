<?php
ob_start();

use App\Components\Alert;
use App\Components\Badge;
use App\Services\EntraService;
?>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-microsoft me-1"></i> Microsoft Entra</h4>
        <small class="text-muted">Gestão de usuários e licenças via Microsoft Graph -- sem precisar entrar no portal confuso da Microsoft.</small>
    </div>
    <a href="<?= url('/entra/configuracao') ?>" class="btn btn-outline-secondary"><i class="bi bi-gear"></i> Configuração</a>
</div>

<?php if (!$configurado): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-plug display-6 text-muted d-block mb-3"></i>
            <p class="text-muted mb-3">Módulo ainda não configurado -- é preciso um App Registration no tenant do cliente.</p>
            <a href="<?= url('/entra/configuracao') ?>" class="btn btn-primary"><i class="bi bi-gear"></i> Configurar agora</a>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="bi bi-people display-6 text-primary"></i>
                    <div class="fs-3 fw-bold mt-2"><?= (int)$totalUsuarios ?></div>
                    <div class="text-muted small">Usuários no tenant</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="bi bi-person-check display-6 text-success"></i>
                    <div class="fs-3 fw-bold mt-2"><?= (int)$totalAtivos ?></div>
                    <div class="text-muted small">Contas ativas</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <a href="<?= url('/entra/usuarios') ?>" class="card border-0 shadow-sm h-100 text-decoration-none text-dark">
                <div class="card-body text-center">
                    <i class="bi bi-person-gear display-6 text-secondary"></i>
                    <div class="fs-6 fw-bold mt-2">Gerenciar usuários</div>
                    <div class="text-muted small">Ver, criar, resetar senha, licenças</div>
                </div>
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><strong>Licenças do tenant</strong></div>
        <div class="card-body p-0">
            <?php if (empty($skus)): ?>
                <p class="text-muted p-3 mb-0">Nenhuma licença encontrada (ou sem permissão pra consultar).</p>
            <?php else: ?>
                <table class="table table-sm mb-0">
                    <thead>
                        <tr><th>Licença</th><th>Usadas</th><th>Total</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($skus as $sku): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars(EntraService::nomeAmigavelSku($sku['skuPartNumber'] ?? '—')) ?>
                                    <span class="text-muted small font-monospace">(<?= htmlspecialchars($sku['skuPartNumber'] ?? '—') ?>)</span>
                                </td>
                                <td><?= (int)($sku['consumedUnits'] ?? 0) ?></td>
                                <td><?= (int)($sku['prepaidUnits']['enabled'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Microsoft Entra';

require __DIR__ . '/../layouts/main.php';
