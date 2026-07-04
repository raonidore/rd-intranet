<?php ob_start(); ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-danger text-white">
        <h5 class="mb-0"><i class="bi bi-trash"></i> Confirmar Exclusão</h5>
    </div>

    <div class="card-body">
        <p>Você está prestes a remover o compartilhamento:</p>

        <h4><?= htmlspecialchars($compartilhamento['nome']) ?></h4>

        <p class="text-muted"><?= htmlspecialchars($compartilhamento['caminho']) ?></p>

        <div class="alert alert-warning">
            Esta ação remove o compartilhamento do cadastro da RD Intranet e marcará alteração pendente no Deploy Center.
            A pasta física não será apagada neste momento.
        </div>

        <form method="post" action="<?= url('/samba/compartilhamentos/excluir') ?>">
            <input type="hidden" name="id" value="<?= htmlspecialchars($compartilhamento['id']) ?>">

            <button class="btn btn-danger"><i class="bi bi-trash"></i> Sim, excluir</button>
            <a href="<?= url('/samba/compartilhamentos') ?>" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Excluir Compartilhamento';
require __DIR__ . '/../layouts/main.php';
