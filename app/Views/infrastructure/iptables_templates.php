<?php

use App\Components\Alert;

ob_start();
?>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-magic me-1"></i> Regras Prontas</h4>
        <small class="text-muted">Escolha uma situação comum, informe os dados pedidos (geralmente só a interface) e pronto.</small>
    </div>
    <a href="<?= url('/infraestrutura/iptables') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>
</div>

<div class="row g-3">
    <?php foreach ($catalogo as $chave => $t): ?>
        <div class="col-md-6 col-lg-4">
            <a href="<?= url('/infraestrutura/iptables/templates/form?chave=' . urlencode($chave)) ?>"
               class="card border-0 shadow-sm h-100 text-decoration-none text-dark">
                <div class="card-body">
                    <div class="mb-2" style="font-size: 28px; color:#0d6efd;">
                        <i class="bi <?= htmlspecialchars($t['icone']) ?>"></i>
                    </div>
                    <h6 class="mb-1"><?= htmlspecialchars($t['nome']) ?></h6>
                    <p class="text-muted small mb-0"><?= htmlspecialchars($t['descricao']) ?></p>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - Regras Prontas de Firewall';

require __DIR__ . '/../layouts/main.php';
