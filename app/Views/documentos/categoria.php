<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <a href="<?= url('/documentos') ?>" class="text-decoration-none small text-muted d-block mb-1">
        <i class="bi bi-arrow-left"></i> Documentos
    </a>
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <h4 class="mb-1"><i class="bi bi-folder-fill text-warning me-1"></i> <?= htmlspecialchars($categoria['nome']) ?></h4>
            <?php if (!empty($categoria['descricao'])): ?>
                <small class="text-muted"><?= htmlspecialchars($categoria['descricao']) ?></small>
            <?php endif; ?>
        </div>
        <?php if ($permissao['editar']): ?>
            <button type="button" class="btn btn-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#modalNovoDocumento">
                <i class="bi bi-plus-lg"></i> Novo documento
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($documentos)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-file-earmark" style="font-size:2rem;"></i>
            <p class="mb-0 mt-2">Nenhum documento nessa categoria ainda.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Anexo</th>
                        <th>Versão</th>
                        <th>Atualizado em</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documentos as $doc): ?>
                        <tr style="cursor:pointer" onclick="location.href='<?= url('/documentos/ver?id=' . (int)$doc['id']) ?>'">
                            <td><strong><?= htmlspecialchars($doc['titulo']) ?></strong></td>
                            <td>
                                <?php if (!empty($doc['anexo_origem'])): ?>
                                    <i class="bi bi-<?= $doc['anexo_origem'] === 'samba' ? 'folder-symlink' : 'paperclip' ?> text-muted"></i>
                                    <?= htmlspecialchars($doc['anexo_nome_original']) ?>
                                <?php else: ?>
                                    <span class="text-muted small">Sem anexo</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge text-bg-light border">v<?= (int)$doc['versao'] ?></span></td>
                            <td class="text-muted small"><?= date('d/m/Y H:i', strtotime($doc['atualizado_em'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if ($permissao['editar']): ?>
<div class="modal fade" id="modalNovoDocumento" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?= url('/documentos/novo') ?>">
                <input type="hidden" name="categoria_id" value="<?= (int)$categoria['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Novo documento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Título *</label>
                        <input type="text" name="titulo" class="form-control" required maxlength="200">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" class="form-control" rows="3"></textarea>
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
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = $categoria['nome'];

require __DIR__ . '/../layouts/main.php';
