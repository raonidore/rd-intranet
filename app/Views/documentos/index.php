<?php
ob_start();

use App\Components\Alert;
use App\Services\PermissionService;
?>

<?= Alert::flash() ?>

<div class="mb-4 d-flex justify-content-between align-items-start">
    <div>
        <h4 class="mb-1"><i class="bi bi-folder2-open me-1"></i> Documentos</h4>
        <small class="text-muted">Documentação e arquivos por categoria, com controle de quem pode ver e editar.</small>
    </div>
    <?php if (PermissionService::temAcesso('documentos_categorias')): ?>
        <a href="<?= url('/documentos/categorias') ?>" class="btn btn-outline-secondary text-nowrap">
            <i class="bi bi-gear"></i> Gerenciar categorias
        </a>
    <?php endif; ?>
</div>

<?php if (empty($categorias)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-folder2" style="font-size:2rem;"></i>
            <p class="mb-0 mt-2">Nenhuma categoria disponível pra você ainda.</p>
            <?php if (PermissionService::temAcesso('documentos_categorias')): ?>
                <p class="small mt-2">Crie uma categoria e conceda acesso em <a href="<?= url('/documentos/categorias') ?>">Gerenciar categorias</a>.</p>
            <?php else: ?>
                <p class="small mt-2">Peça a um administrador pra liberar acesso a uma categoria.</p>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($categorias as $categoria): ?>
            <div class="col-md-4">
                <a href="<?= url('/documentos/categoria?id=' . (int)$categoria['id']) ?>" class="text-decoration-none">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="card-title mb-1"><i class="bi bi-folder-fill text-warning me-1"></i> <?= htmlspecialchars($categoria['nome']) ?></h6>
                            <?php if (!empty($categoria['descricao'])): ?>
                                <p class="small text-muted mb-2"><?= htmlspecialchars($categoria['descricao']) ?></p>
                            <?php endif; ?>
                            <span class="badge text-bg-light border"><?= (int)$categoria['total_documentos'] ?> documento(s)</span>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Documentos';

require __DIR__ . '/../layouts/main.php';
