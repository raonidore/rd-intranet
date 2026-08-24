<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-4 d-flex justify-content-between align-items-start">
    <div>
        <a href="<?= url('/documentos') ?>" class="text-decoration-none small text-muted d-block mb-1">
            <i class="bi bi-arrow-left"></i> Documentos
        </a>
        <h4 class="mb-1"><i class="bi bi-folder2-open me-1"></i> Categorias e permissões</h4>
        <small class="text-muted">Sem nenhuma permissão concedida, só administradores enxergam a categoria.</small>
    </div>
    <button type="button" class="btn btn-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#modalNovaCategoria">
        <i class="bi bi-plus-lg"></i> Nova categoria
    </button>
</div>

<?php if (empty($categorias)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">Nenhuma categoria cadastrada ainda.</div>
    </div>
<?php else: ?>
    <div class="accordion" id="acordeaoCategorias">
        <?php foreach ($categorias as $categoria):
            $collapseId = 'categoria' . (int)$categoria['id'];
            $permissoes = $permissoesPorCategoria[$categoria['id']] ?? [];
            $mapaPermissoesUsuario = [];
            $mapaPermissoesGrupo = [];
            foreach ($permissoes as $p) {
                if ($p['sujeito_tipo'] === 'usuario') {
                    $mapaPermissoesUsuario[$p['sujeito_id']] = $p;
                } else {
                    $mapaPermissoesGrupo[$p['sujeito_id']] = $p;
                }
            }
        ?>
        <div class="accordion-item border-0 shadow-sm mb-2">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>">
                    <div class="d-flex justify-content-between align-items-center w-100 me-3">
                        <span>
                            <strong><?= htmlspecialchars($categoria['nome']) ?></strong>
                            <?php if (!empty($categoria['descricao'])): ?>
                                <span class="text-muted small ms-2"><?= htmlspecialchars($categoria['descricao']) ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="d-flex align-items-center gap-2">
                            <span class="badge text-bg-light border"><?= (int)$categoria['total_documentos'] ?> doc(s)</span>
                            <?= $categoria['ativo'] ? '' : '<span class="badge text-bg-secondary">Inativa</span>' ?>
                        </span>
                    </div>
                </button>
            </h2>
            <div id="<?= $collapseId ?>" class="accordion-collapse collapse" data-bs-parent="#acordeaoCategorias">
                <div class="accordion-body">
                    <form method="post" action="<?= url('/documentos/categorias/atualizar') ?>" class="row g-3 mb-4">
                        <input type="hidden" name="id" value="<?= (int)$categoria['id'] ?>">
                        <div class="col-md-4">
                            <label class="form-label">Nome</label>
                            <input type="text" name="nome" class="form-control" required maxlength="150" value="<?= htmlspecialchars($categoria['nome']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Descrição</label>
                            <input type="text" name="descricao" class="form-control" maxlength="255" value="<?= htmlspecialchars($categoria['descricao'] ?? '') ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="ativo" value="1" id="ativa<?= $categoria['id'] ?>" <?= $categoria['ativo'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="ativa<?= $categoria['id'] ?>">Ativa</label>
                            </div>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-save"></i> Salvar dados</button>
                            <button type="submit" formaction="<?= url('/documentos/categorias/excluir') ?>" class="btn btn-outline-danger btn-sm"
                                    onclick="return confirm('Excluir esta categoria?');"><i class="bi bi-trash"></i> Excluir</button>
                        </div>
                    </form>

                    <hr>

                    <h6 class="mb-3">Quem pode ver / editar / excluir</h6>
                    <form class="form-permissoes" data-categoria-id="<?= (int)$categoria['id'] ?>">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>Nome</th>
                                    <th class="text-center">Visualizar</th>
                                    <th class="text-center">Editar</th>
                                    <th class="text-center">Excluir</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($grupos as $grupo): $p = $mapaPermissoesGrupo[$grupo['id']] ?? null; ?>
                                    <tr>
                                        <td><span class="badge text-bg-light border">Grupo</span></td>
                                        <td><?= htmlspecialchars($grupo['nome']) ?></td>
                                        <td class="text-center"><input type="checkbox" name="grupos[<?= $grupo['id'] ?>][visualizar]" <?= $p && $p['pode_visualizar'] ? 'checked' : '' ?>></td>
                                        <td class="text-center"><input type="checkbox" name="grupos[<?= $grupo['id'] ?>][editar]" <?= $p && $p['pode_editar'] ? 'checked' : '' ?>></td>
                                        <td class="text-center"><input type="checkbox" name="grupos[<?= $grupo['id'] ?>][excluir]" <?= $p && $p['pode_excluir'] ? 'checked' : '' ?>></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php foreach ($usuarios as $usuario): $p = $mapaPermissoesUsuario[$usuario['id']] ?? null; ?>
                                    <tr>
                                        <td><span class="badge text-bg-light border">Usuário</span></td>
                                        <td><?= htmlspecialchars($usuario['nome']) ?> <span class="text-muted small">(<?= htmlspecialchars($usuario['login']) ?>)</span></td>
                                        <td class="text-center"><input type="checkbox" name="usuarios[<?= $usuario['id'] ?>][visualizar]" <?= $p && $p['pode_visualizar'] ? 'checked' : '' ?>></td>
                                        <td class="text-center"><input type="checkbox" name="usuarios[<?= $usuario['id'] ?>][editar]" <?= $p && $p['pode_editar'] ? 'checked' : '' ?>></td>
                                        <td class="text-center"><input type="checkbox" name="usuarios[<?= $usuario['id'] ?>][excluir]" <?= $p && $p['pode_excluir'] ? 'checked' : '' ?>></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save"></i> Salvar permissões</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Modal nova categoria -->
<div class="modal fade" id="modalNovaCategoria" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?= url('/documentos/categorias/criar') ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Nova categoria</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="nome" class="form-control" required maxlength="150">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Descrição</label>
                        <input type="text" name="descricao" class="form-control" maxlength="255">
                    </div>
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
document.querySelectorAll('.form-permissoes').forEach(function (form) {
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const botao = form.querySelector('button[type=submit]');
        botao.disabled = true;

        const params = new URLSearchParams(new FormData(form));
        params.append('categoria_id', form.dataset.categoriaId);

        try {
            const res = await fetch(<?= json_encode(url('/documentos/categorias/permissoes')) ?>, { method: 'POST', body: params });
            const dados = await res.json();
            alert(dados.message || (dados.success ? 'Salvo.' : 'Erro ao salvar.'));
        } catch (err) {
            alert('Erro de rede ao salvar.');
        } finally {
            botao.disabled = false;
        }
    });
});
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Categorias de Documentos';

require __DIR__ . '/../layouts/main.php';
