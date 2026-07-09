<?php

ob_start();
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-danger text-white">
                <h4 class="mb-0"><i class="bi bi-trash"></i> Confirmar exclusão</h4>
            </div>

            <div class="card-body">
                <div class="mb-4">
                    <h5 class="mb-1"><?= htmlspecialchars($regra['nome']) ?></h5>
                    <small class="text-muted font-monospace">
                        <?= htmlspecialchars($regra['tabela']) ?> / <?= htmlspecialchars($regra['cadeia']) ?> / <?= htmlspecialchars($regra['acao']) ?>
                    </small>
                </div>

                <div class="alert alert-warning">
                    <strong>Atenção!</strong> A regra será removida e o firewall reaplicado sem ela
                    (com a mesma janela de confirmação/reversão automática das demais alterações).
                </div>

                <form method="post" action="<?= url('/infraestrutura/iptables/excluir') ?>">
                    <input type="hidden" name="id" value="<?= (int)$regra['id'] ?>">

                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Sim, excluir regra
                    </button>
                    <a href="<?= url('/infraestrutura/iptables') ?>" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Excluir Regra de Firewall';

require __DIR__ . '/../layouts/main.php';
