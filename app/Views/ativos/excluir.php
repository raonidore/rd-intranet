<?php
ob_start();

use App\Services\AtivoService;
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-danger text-white">
        <h5 class="mb-0"><i class="bi bi-trash"></i> Confirmar Exclusão</h5>
    </div>

    <div class="card-body">
        <p>Você está prestes a remover o ativo:</p>

        <h4><?= htmlspecialchars($ativo['nome']) ?></h4>
        <p class="text-muted font-monospace"><?= htmlspecialchars($ativo['codigo_patrimonio']) ?> · <?= htmlspecialchars(AtivoService::TIPOS[$ativo['tipo']]['label']) ?></p>

        <div class="alert alert-warning">
            Esta ação remove o ativo do cadastro, junto com o histórico de programas e alertas coletados. Não afeta o equipamento físico.
        </div>

        <form method="post" action="<?= url('/ativos/excluir') ?>">
            <input type="hidden" name="id" value="<?= (int)$ativo['id'] ?>">

            <button class="btn btn-danger"><i class="bi bi-trash"></i> Sim, excluir</button>
            <a href="<?= url('/ativos/lista') ?>" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Excluir Ativo';
require __DIR__ . '/../layouts/main.php';
