<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-diagram-3 me-1"></i> WhatsApp - Setores</h4>
    <small class="text-muted">Setores de atendimento e quais usuários (já cadastrados no sistema) atendem em cada um.</small>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="post" action="<?= url('/whatsapp/setores/criar') ?>" class="d-flex gap-2">
            <input type="text" name="nome" class="form-control" placeholder="Nome do novo setor (ex: Suporte técnico)" required maxlength="100">
            <button type="submit" class="btn btn-primary text-nowrap">
                <i class="bi bi-plus-lg"></i> Adicionar setor
            </button>
        </form>
    </div>
</div>

<?php if (empty($setores)): ?>
    <p class="text-muted">Nenhum setor cadastrado ainda.</p>
<?php endif; ?>

<?php foreach ($setores as $setor): ?>
    <?php $idColapso = 'setor' . (int)$setor['id']; ?>
    <div class="card border-0 shadow-sm mb-2">
        <div class="card-header bg-white d-flex justify-content-between align-items-center" style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#<?= $idColapso ?>">
            <div>
                <strong><?= htmlspecialchars($setor['nome']) ?></strong>
                <?= $setor['ativo'] ? '<span class="badge text-bg-success ms-1">Ativo</span>' : '<span class="badge text-bg-secondary ms-1">Inativo</span>' ?>
                <?php if ($setor['nps_ativo']): ?><span class="badge text-bg-info ms-1">NPS</span><?php endif; ?>
                <span class="badge text-bg-light border ms-1"><?= (int)$setor['total_usuarios'] ?> usuário(s)</span>
            </div>
            <i class="bi bi-chevron-down text-muted"></i>
        </div>
        <div class="collapse" id="<?= $idColapso ?>">
            <div class="card-body border-top">
                <div class="row g-4">
                    <div class="col-md-5">
                        <h6 class="mb-2">Dados do setor</h6>
                        <form method="post" action="<?= url('/whatsapp/setores/atualizar') ?>" class="mb-3">
                            <input type="hidden" name="id" value="<?= (int)$setor['id'] ?>">
                            <div class="mb-2">
                                <label class="form-label small">Nome</label>
                                <input type="text" name="nome" class="form-control form-control-sm" value="<?= htmlspecialchars($setor['nome']) ?>" required maxlength="100">
                            </div>
                            <div class="form-check mb-2">
                                <input type="checkbox" name="ativo" class="form-check-input" id="ativo<?= (int)$setor['id'] ?>" <?= $setor['ativo'] ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="ativo<?= (int)$setor['id'] ?>">Setor ativo</label>
                            </div>
                            <div class="form-check mb-2">
                                <input type="checkbox" name="nps_ativo" class="form-check-input" id="nps<?= (int)$setor['id'] ?>" <?= $setor['nps_ativo'] ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="nps<?= (int)$setor['id'] ?>">
                                    Pesquisa de satisfação (NPS) ao encerrar
                                </label>
                            </div>
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-check-lg"></i> Salvar
                            </button>
                        </form>

                        <form method="post" action="<?= url('/whatsapp/setores/excluir') ?>" onsubmit="return confirm('Excluir o setor &quot;<?= htmlspecialchars(addslashes($setor['nome'])) ?>&quot;? Atendimentos vinculados ficam sem setor.');">
                            <input type="hidden" name="id" value="<?= (int)$setor['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i> Excluir setor
                            </button>
                        </form>
                    </div>

                    <div class="col-md-7">
                        <h6 class="mb-2">Usuários que atendem neste setor</h6>
                        <?php if (empty($usuariosAtivos)): ?>
                            <p class="text-muted small">Nenhum usuário ativo cadastrado no sistema.</p>
                        <?php else: ?>
                            <form method="post" action="<?= url('/whatsapp/setores/usuarios') ?>">
                                <input type="hidden" name="setor_id" value="<?= (int)$setor['id'] ?>">
                                <div class="row row-cols-1 row-cols-md-2 g-1 mb-2" style="max-height:220px; overflow-y:auto">
                                    <?php foreach ($usuariosAtivos as $usuario): ?>
                                        <?php $marcado = in_array((int)$usuario['id'], $usuariosPorSetor[$setor['id']] ?? [], true); ?>
                                        <div class="col">
                                            <div class="form-check">
                                                <input type="checkbox" name="usuarios[]" value="<?= (int)$usuario['id'] ?>"
                                                       class="form-check-input" id="u<?= (int)$setor['id'] ?>_<?= (int)$usuario['id'] ?>"
                                                       <?= $marcado ? 'checked' : '' ?>>
                                                <label class="form-check-label small" for="u<?= (int)$setor['id'] ?>_<?= (int)$usuario['id'] ?>">
                                                    <?= htmlspecialchars($usuario['nome']) ?> <span class="text-muted">(<?= htmlspecialchars($usuario['login']) ?>)</span>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-check-lg"></i> Salvar usuários do setor
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'WhatsApp - Setores';

require __DIR__ . '/../layouts/main.php';
