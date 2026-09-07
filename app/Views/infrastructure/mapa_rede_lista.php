<?php

use App\Components\Alert;

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-diagram-3 me-1"></i> Mapa de Rede</h4>
        <small class="text-muted">Diagramas de topologia -- importe dispositivos do IP Scanner e monte o mapa de verdade por cima.</small>
    </div>
    <a href="<?= url('/infraestrutura/rede/mapa/novo') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Novo mapa
    </a>
</div>

<?= Alert::flash() ?>

<?php if (empty($mapas)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-diagram-3 display-5 text-muted"></i>
            <h5 class="mt-3">Nenhum mapa ainda</h5>
            <p class="text-muted">Crie um mapa vazio ou vá em <a href="<?= url('/infraestrutura/rede/scanner') ?>">IP Scanner</a> e exporte uma varredura direto pra cá.</p>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($mapas as $m): ?>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="mb-1"><?= htmlspecialchars($m['nome']) ?></h5>
                        <p class="text-muted small mb-2"><?= htmlspecialchars($m['descricao'] ?: 'Sem descrição') ?></p>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="badge text-bg-light border"><?= (int)$m['total_nos'] ?> nó(s)</span>
                            <small class="text-muted">Atualizado em <?= date('d/m/Y H:i', strtotime($m['atualizado_em'])) ?></small>
                        </div>
                    </div>
                    <div class="card-footer bg-white d-flex justify-content-between">
                        <a href="<?= url('/infraestrutura/rede/mapa/ver?id=' . $m['id']) ?>" class="btn btn-sm btn-primary">
                            <i class="bi bi-pencil"></i> Abrir
                        </a>
                        <form method="post" action="<?= url('/infraestrutura/rede/mapa/excluir') ?>" onsubmit="return confirm('Excluir o mapa \'<?= htmlspecialchars($m['nome'], ENT_QUOTES) ?>\'?')">
                            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - Mapa de Rede';
require __DIR__ . '/../layouts/main.php';
