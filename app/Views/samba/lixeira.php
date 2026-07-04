<?php

use App\Components\Alert;

ob_start();
?>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h5 class="mb-1">
            <i class="bi bi-trash3"></i> Lixeira Administrativa Samba
        </h5>
        <small class="text-muted">
            Itens removidos com segurança de /srv/samba/Compartilhamentos.
        </small>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Caminho</th>
                    <th>Data</th>
                    <th>Tamanho aprox.</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($itens)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            Nenhum item na lixeira administrativa.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($itens as $item): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($item['nome']) ?></strong></td>
                        <td><code><?= htmlspecialchars($item['caminho']) ?></code></td>
                        <td><?= htmlspecialchars($item['data']) ?></td>
                        <td><?= number_format($item['tamanho_kb'] / 1024, 2, ',', '.') ?> MB</td>
                        <td class="text-end">
                            <form method="post" action="<?= url('/samba/lixeira/restaurar') ?>" class="d-inline">
                                <input type="hidden" name="nome" value="<?= htmlspecialchars($item['nome']) ?>">
                                <button class="btn btn-sm btn-outline-success"
                                        onclick="return confirm('Restaurar este item?')">
                                    <i class="bi bi-arrow-counterclockwise"></i> Restaurar
                                </button>
                            </form>

                            <form method="post" action="<?= url('/samba/lixeira/excluir') ?>" class="d-inline">
                                <input type="hidden" name="nome" value="<?= htmlspecialchars($item['nome']) ?>">
                                <button class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Excluir definitivamente? Esta ação não poderá ser desfeita.')">
                                    <i class="bi bi-x-circle"></i> Excluir definitivo
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Lixeira Administrativa Samba';

require __DIR__ . '/../layouts/main.php';
