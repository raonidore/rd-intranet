<?php
ob_start();

use App\Components\Alert;
use App\Services\ModuloCatalogo;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-people-fill me-1"></i> Grupos</h4>
    <small class="text-muted">Grupos de usuários -- conceda módulos ou permissões pro grupo inteiro de uma vez, em vez de usuário por usuário.</small>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="post" action="<?= url('/administracao/grupos/criar') ?>" class="row g-2">
            <div class="col-md-4">
                <input type="text" name="nome" class="form-control" placeholder="Nome do grupo (ex: Financeiro)" required maxlength="100">
            </div>
            <div class="col-md-6">
                <input type="text" name="descricao" class="form-control" placeholder="Descrição (opcional)" maxlength="255">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary text-nowrap w-100">
                    <i class="bi bi-plus-lg"></i> Adicionar
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (empty($grupos)): ?>
    <p class="text-muted">Nenhum grupo cadastrado ainda.</p>
<?php endif; ?>

<?php foreach ($grupos as $grupo): ?>
    <?php $idColapso = 'grupo' . (int)$grupo['id']; ?>
    <div class="card border-0 shadow-sm mb-2">
        <div class="card-header bg-white d-flex justify-content-between align-items-center" style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#<?= $idColapso ?>">
            <div>
                <strong><?= htmlspecialchars($grupo['nome']) ?></strong>
                <?php if (!empty($grupo['descricao'])): ?>
                    <span class="text-muted small ms-1"><?= htmlspecialchars($grupo['descricao']) ?></span>
                <?php endif; ?>
                <span class="badge text-bg-light border ms-1"><?= (int)$grupo['total_usuarios'] ?> usuário(s)</span>
                <span class="badge text-bg-light border ms-1"><?= count($modulosPorGrupo[$grupo['id']] ?? []) ?> módulo(s)</span>
            </div>
            <i class="bi bi-chevron-down text-muted"></i>
        </div>
        <div class="collapse" id="<?= $idColapso ?>">
            <div class="card-body border-top">
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <h6 class="mb-2">Dados do grupo</h6>
                        <form method="post" action="<?= url('/administracao/grupos/atualizar') ?>" class="mb-3">
                            <input type="hidden" name="id" value="<?= (int)$grupo['id'] ?>">
                            <div class="mb-2">
                                <label class="form-label small">Nome</label>
                                <input type="text" name="nome" class="form-control form-control-sm" value="<?= htmlspecialchars($grupo['nome']) ?>" required maxlength="100">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Descrição</label>
                                <input type="text" name="descricao" class="form-control form-control-sm" value="<?= htmlspecialchars($grupo['descricao'] ?? '') ?>" maxlength="255">
                            </div>
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-check-lg"></i> Salvar
                            </button>
                        </form>

                        <form method="post" action="<?= url('/administracao/grupos/excluir') ?>" onsubmit="return confirm('Excluir o grupo &quot;<?= htmlspecialchars(addslashes($grupo['nome'])) ?>&quot;?');">
                            <input type="hidden" name="id" value="<?= (int)$grupo['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i> Excluir grupo
                            </button>
                        </form>
                    </div>

                    <div class="col-md-8">
                        <h6 class="mb-2">Usuários do grupo</h6>
                        <?php if (empty($usuariosAtivos)): ?>
                            <p class="text-muted small">Nenhum usuário ativo cadastrado no sistema.</p>
                        <?php else: ?>
                            <form method="post" action="<?= url('/administracao/grupos/usuarios') ?>">
                                <input type="hidden" name="grupo_id" value="<?= (int)$grupo['id'] ?>">
                                <div class="row row-cols-1 row-cols-md-2 g-1 mb-2" style="max-height:220px; overflow-y:auto">
                                    <?php foreach ($usuariosAtivos as $usuario): ?>
                                        <?php $marcado = in_array((int)$usuario['id'], $usuariosPorGrupo[$grupo['id']] ?? [], true); ?>
                                        <div class="col">
                                            <div class="form-check">
                                                <input type="checkbox" name="usuarios[]" value="<?= (int)$usuario['id'] ?>"
                                                       class="form-check-input" id="gu<?= (int)$grupo['id'] ?>_<?= (int)$usuario['id'] ?>"
                                                       <?= $marcado ? 'checked' : '' ?>>
                                                <label class="form-check-label small" for="gu<?= (int)$grupo['id'] ?>_<?= (int)$usuario['id'] ?>">
                                                    <?= htmlspecialchars($usuario['nome']) ?> <span class="text-muted">(<?= htmlspecialchars($usuario['login']) ?>)</span>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-check-lg"></i> Salvar usuários do grupo
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <h6 class="mb-1">Módulos concedidos ao grupo</h6>
                    <p class="text-muted small mb-2">Quem estiver no grupo ganha acesso a esses módulos, somado ao que já tiver individualmente. Precisa logar de novo pra valer.</p>
                    <form method="post" action="<?= url('/administracao/grupos/modulos') ?>">
                        <input type="hidden" name="grupo_id" value="<?= (int)$grupo['id'] ?>">
                        <div class="row g-3 mb-2" style="max-height:340px; overflow-y:auto">
                            <?php foreach ($modulosAgrupados as $nomeGrupoModulo => $itens): ?>
                                <div class="col-md-4">
                                    <div class="border rounded p-2 h-100">
                                        <div class="small fw-bold mb-1 d-flex align-items-center gap-1">
                                            <i class="bi <?= ModuloCatalogo::iconeDoGrupo($nomeGrupoModulo) ?>"></i>
                                            <?= htmlspecialchars($nomeGrupoModulo) ?>
                                        </div>
                                        <?php foreach ($itens as $chave => $label): ?>
                                            <div class="form-check">
                                                <input type="checkbox" name="modulos[]" value="<?= $chave ?>"
                                                       class="form-check-input" id="gm<?= (int)$grupo['id'] ?>_<?= $chave ?>"
                                                       <?= in_array($chave, $modulosPorGrupo[$grupo['id']] ?? [], true) ? 'checked' : '' ?>>
                                                <label class="form-check-label small" for="gm<?= (int)$grupo['id'] ?>_<?= $chave ?>">
                                                    <?= htmlspecialchars($label) ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-check-lg"></i> Salvar módulos do grupo
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Grupos';

require __DIR__ . '/../layouts/main.php';
