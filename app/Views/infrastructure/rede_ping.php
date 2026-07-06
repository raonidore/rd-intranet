<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-broadcast me-1"></i> Ping</h4>
        <small class="text-muted">Testa conectividade com um host ou IP.</small>
    </div>
    <a href="<?= url('/infraestrutura/rede') ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Interfaces
    </a>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="post" action="<?= url('/infraestrutura/rede/ping') ?>" class="d-flex gap-2">
            <input type="text" name="destino" class="form-control" placeholder="Ex: 8.8.8.8 ou google.com"
                   value="<?= htmlspecialchars($destino) ?>" required>
            <button type="submit" class="btn btn-primary text-nowrap">
                <i class="bi bi-play-fill"></i> Executar
            </button>
        </form>
    </div>
</div>

<?php if ($resultado !== null): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            Resultado para <code><?= htmlspecialchars($destino) ?></code>
            <?= $resultado['success'] ? '<span class="badge text-bg-success ms-1">OK</span>' : '<span class="badge text-bg-danger ms-1">Falhou</span>' ?>
        </div>
        <div class="card-body">
            <pre class="bg-dark text-light p-3 rounded mb-0" style="white-space:pre-wrap"><?= htmlspecialchars($resultado['output']) ?></pre>
        </div>
    </div>
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - Ping';

require __DIR__ . '/../layouts/main.php';
