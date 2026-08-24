<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-4 d-flex justify-content-between align-items-start">
    <div>
        <h4 class="mb-1"><i class="bi bi-truck me-1"></i> Fornecedores</h4>
        <small class="text-muted">Quem presta serviço pra gente, com os contratos vinculados.</small>
    </div>
    <a href="<?= url('/fornecedores/novo') ?>" class="btn btn-primary text-nowrap">
        <i class="bi bi-plus-lg"></i> Novo fornecedor
    </a>
</div>

<?php if (empty($fornecedores)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-truck" style="font-size:2rem;"></i>
            <p class="mb-0 mt-2">Nenhum fornecedor cadastrado ainda.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Tipo de serviço</th>
                        <th>Contato</th>
                        <th>Contratos</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fornecedores as $item): ?>
                        <tr style="cursor:pointer" onclick="location.href='<?= url('/fornecedores/ver?id=' . (int)$item['id']) ?>'">
                            <td><strong><?= htmlspecialchars($item['nome_fantasia']) ?></strong></td>
                            <td><?= htmlspecialchars($item['tipo_servico_nome'] ?? '-') ?></td>
                            <td class="text-muted small">
                                <?= htmlspecialchars($item['telefone'] ?? '') ?>
                                <?php if (!empty($item['telefone']) && !empty($item['email'])): ?> &middot; <?php endif; ?>
                                <?= htmlspecialchars($item['email'] ?? '') ?>
                            </td>
                            <td><span class="badge text-bg-light border"><?= (int)$item['total_contratos'] ?></span></td>
                            <td>
                                <?= $item['ativo']
                                    ? '<span class="badge text-bg-success">Ativo</span>'
                                    : '<span class="badge text-bg-secondary">Inativo</span>' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Fornecedores';

require __DIR__ . '/../layouts/main.php';
