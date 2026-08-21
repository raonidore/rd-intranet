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
        <form method="post" action="<?= url('/infraestrutura/rede/ping') ?>" id="formPing" class="d-flex gap-2">
            <input type="text" name="destino" class="form-control" placeholder="Ex: 8.8.8.8 ou google.com"
                   value="<?= htmlspecialchars($destino) ?>" required>
            <button type="submit" class="btn btn-primary text-nowrap">
                <i class="bi bi-play-fill"></i> Executar
            </button>
        </form>
    </div>
</div>

<?php if ($resultado !== null): ?>
    <div class="card border-0 shadow-sm" id="cardResultadoPing">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span>Resultado para <code><?= htmlspecialchars($destino) ?></code></span>
            <div class="d-flex align-items-center gap-1">
                <?= $resultado['success'] ? '<span class="badge text-bg-success">OK</span>' : '<span class="badge text-bg-danger">Falhou</span>' ?>
                <div class="dropdown d-inline-block" data-rd-export-ignore
                     data-rd-export-container="#cardResultadoPing"
                     data-rd-export-titulo="Ping"
                     data-rd-export-ferramenta="ping"
                     data-rd-export-alvo="<?= htmlspecialchars($destino) ?>">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-download"></i> Exportar
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" data-rd-export-formato="html">HTML</a></li>
                        <li><a class="dropdown-item" href="#" data-rd-export-formato="pdf">PDF</a></li>
                        <li><a class="dropdown-item" href="#" data-rd-export-formato="jpg">JPG</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="card-body">
            <pre class="bg-dark text-light p-3 rounded mb-0" style="white-space:pre-wrap"><?= htmlspecialchars($resultado['output']) ?></pre>
        </div>
    </div>
<?php endif; ?>

<script src="<?= asset_url('/assets/js/rd-diagnostico.js') ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js"></script>
<script>
    RdDiagnostico.armarFormulario(document.getElementById('formPing'), 'Executando Ping... só um instante.');
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - Ping';

require __DIR__ . '/../layouts/main.php';
