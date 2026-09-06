<?php

use App\Components\Alert;

ob_start();
?>

<div class="mb-4">
    <a href="<?= url('/projetos') ?>" class="text-decoration-none small text-muted d-block mb-1">
        <i class="bi bi-arrow-left"></i> Projetos
    </a>
    <h5 class="mb-1"><i class="bi bi-trash3"></i> Lixeira de Projetos</h5>
    <small class="text-muted">Projeto excluído fica aqui por 30 dias, dá pra restaurar antes disso -- depois é removido em definitivo sozinho.</small>
</div>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Projeto</th>
                    <th>Área</th>
                    <th>Excluído por</th>
                    <th>Excluído em</th>
                    <th>Prazo na lixeira</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($projetos)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            Nenhum projeto na lixeira.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($projetos as $projeto): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($projeto['titulo']) ?></strong></td>
                        <td><span class="badge text-bg-light border"><?= htmlspecialchars($projeto['area_nome']) ?></span></td>
                        <td><?= htmlspecialchars($projeto['excluido_por_nome'] ?? '-') ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($projeto['excluido_em'])) ?></td>
                        <td>
                            <?php if ((int)$projeto['dias_restantes'] <= 0): ?>
                                <span class="badge text-bg-danger">Purga hoje</span>
                            <?php elseif ((int)$projeto['dias_restantes'] <= 5): ?>
                                <span class="badge text-bg-warning">Faltam <?= (int)$projeto['dias_restantes'] ?> dia(s)</span>
                            <?php else: ?>
                                <span class="text-muted small">Faltam <?= (int)$projeto['dias_restantes'] ?> dias</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <form method="post" action="<?= url('/projetos/lixeira/restaurar') ?>" class="d-inline">
                                <input type="hidden" name="id" value="<?= (int)$projeto['id'] ?>">
                                <button class="btn btn-sm btn-outline-success" onclick="return confirm('Restaurar este projeto?')">
                                    <i class="bi bi-arrow-counterclockwise"></i> Restaurar
                                </button>
                            </form>

                            <form method="post" action="<?= url('/projetos/lixeira/excluir-definitivo') ?>" class="d-inline">
                                <input type="hidden" name="id" value="<?= (int)$projeto['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir em definitivo? Essa ação não pode ser desfeita -- apaga fases, tarefas, comentários e anexos.')">
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
$titulo = 'Lixeira de Projetos';

require __DIR__ . '/../layouts/main.php';
