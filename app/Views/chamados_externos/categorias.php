<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-4 d-flex justify-content-between align-items-start">
    <div>
        <a href="<?= url('/chamados-externos') ?>" class="text-decoration-none small text-muted d-block mb-1">
            <i class="bi bi-arrow-left"></i> Chamados Externos
        </a>
        <h4 class="mb-1"><i class="bi bi-tags me-1"></i> Categorias</h4>
    </div>
    <button type="button" class="btn btn-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#modalNovaCategoria">
        <i class="bi bi-plus-lg"></i> Nova categoria
    </button>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Ativa</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categorias as $categoria): ?>
                    <tr>
                        <td>
                            <input type="text" class="form-control form-control-sm campo-nome" value="<?= htmlspecialchars($categoria['nome']) ?>">
                        </td>
                        <td>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input campo-ativo" type="checkbox" <?= $categoria['ativo'] ? 'checked' : '' ?>>
                            </div>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-outline-secondary btn-sm btn-salvar" data-id="<?= (int)$categoria['id'] ?>">
                                <i class="bi bi-save"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm btn-excluir" data-id="<?= (int)$categoria['id'] ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal nova categoria -->
<div class="modal fade" id="modalNovaCategoria" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?= url('/chamados-externos/categorias/criar') ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Nova categoria</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Nome *</label>
                    <input type="text" name="nome" class="form-control" required maxlength="100">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Cadastrar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.btn-salvar').forEach(function (botao) {
    botao.addEventListener('click', async function () {
        const linha = botao.closest('tr');
        const nome = linha.querySelector('.campo-nome').value;
        const ativo = linha.querySelector('.campo-ativo').checked ? '1' : '';

        const res = await fetch(<?= json_encode(url('/chamados-externos/categorias/atualizar')) ?>, {
            method: 'POST',
            body: new URLSearchParams({ id: botao.dataset.id, nome: nome, ativo: ativo }),
        });
        const dados = await res.json().catch(() => null);
        location.reload();
    });
});

document.querySelectorAll('.btn-excluir').forEach(function (botao) {
    botao.addEventListener('click', async function () {
        if (!confirm('Excluir esta categoria?')) return;

        await fetch(<?= json_encode(url('/chamados-externos/categorias/excluir')) ?>, {
            method: 'POST',
            body: new URLSearchParams({ id: botao.dataset.id }),
        });
        location.reload();
    });
});
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Categorias de Chamados Externos';

require __DIR__ . '/../layouts/main.php';
