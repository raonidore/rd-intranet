<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-4 d-flex justify-content-between align-items-start">
    <div>
        <h4 class="mb-1"><i class="bi bi-diagram-3 me-1"></i> Chamados - Setores</h4>
        <small class="text-muted">Setores de atendimento e quais usuários (já cadastrados no sistema) atendem em cada um.</small>
    </div>
    <button type="button" class="btn btn-outline-dark text-nowrap" data-bs-toggle="modal" data-bs-target="#modalPopSetores">
        <i class="bi bi-broadcast"></i> POP - Setores
    </button>
</div>

<!-- POP -- Procedimento Operacional Padrão de gestão de setores -->
<div class="modal fade pop-modal" id="modalPopSetores" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="pop-topbar">
                <span class="pop-breadcrumb"><i class="bi bi-broadcast"></i> POP -- Setores de Atendimento</span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="pop-body">
                <p class="pop-intro">Setor é o "departamento" que atende o chamado -- toda categoria tem um setor padrão, e quem abre o chamado ainda pode escolher outro na hora, se precisar.</p>

                <div class="pop-step">
                    <div class="pop-step-num">1</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-plus-lg"></i> Adicionar setor</div>
                        <div class="pop-step-text">Só o <strong>nome</strong> é necessário pra criar (ex: "Suporte técnico", "Financeiro", "Infraestrutura") -- o resto se configura depois, expandindo o card do setor.</div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">2</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-toggle-on"></i> Setor ativo</div>
                        <div class="pop-step-text">Desmarcado, o setor some das opções de "Categoria" e "Setor responsável" na abertura de chamados novos -- chamados que já usam ele continuam normalmente.</div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">3</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-people"></i> Usuários que atendem neste setor</div>
                        <div class="pop-step-text">Marque, entre os usuários já cadastrados no sistema, quem faz parte deste setor -- são eles que <strong>enxergam e atendem</strong> os chamados direcionados aqui. Um usuário pode estar em mais de um setor ao mesmo tempo.</div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">4</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-link-45deg"></i> Onde este setor é usado</div>
                        <div class="pop-step-text">Além de aparecer na abertura de chamado, é o setor escolhido aqui que vira o <strong>"setor responsável padrão"</strong> de uma categoria (Chamados &gt; Categorias) -- configure os setores primeiro, categorias depois.</div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">5</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-trash"></i> Excluir setor</div>
                        <div class="pop-step-text">Dentro do card expandido -- <strong>prefira desativar</strong> se alguma categoria ou chamado já usa esse setor, pra não perder a referência.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.pop-modal .modal-content { background:#0d1117; color:#c9d1d9; border:1px solid #30363d; border-radius:14px; }
.pop-topbar { display:flex; justify-content:space-between; align-items:center; padding:14px 20px; background:#161b22; border-bottom:1px solid #30363d; border-radius:14px 14px 0 0; }
.pop-topbar .pop-breadcrumb { font-weight:600; color:#58a6ff; display:flex; align-items:center; gap:8px; font-size:1rem; }
.pop-body { padding:1.3rem 1.6rem; max-height:72vh; overflow-y:auto; }
.pop-intro { color:#8b949e; font-size:.88rem; margin-bottom:1.2rem; padding-bottom:1rem; border-bottom:1px dashed #30363d; }
.pop-step { display:flex; gap:1rem; padding:.85rem 0; border-bottom:1px solid #21262d; }
.pop-step:last-child { border-bottom:0; padding-bottom:0; }
.pop-step-num { flex:0 0 auto; width:34px; height:34px; border-radius:9px; background:#132030; color:#58a6ff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.95rem; border:1px solid #1f3b57; }
.pop-step-title { font-weight:600; color:#e6edf3; font-size:.92rem; display:flex; align-items:center; gap:6px; }
.pop-step-text { color:#8b949e; font-size:.83rem; margin-top:3px; line-height:1.55; }
.pop-step-text strong { color:#c9d1d9; }
</style>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="post" action="<?= url('/chamados/setores/criar') ?>" class="d-flex gap-2">
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
                <span class="badge text-bg-light border ms-1"><?= (int)$setor['total_usuarios'] ?> usuário(s)</span>
            </div>
            <i class="bi bi-chevron-down text-muted"></i>
        </div>
        <div class="collapse" id="<?= $idColapso ?>">
            <div class="card-body border-top">
                <div class="row g-4">
                    <div class="col-md-5">
                        <h6 class="mb-2">Dados do setor</h6>
                        <form method="post" action="<?= url('/chamados/setores/atualizar') ?>" class="mb-3">
                            <input type="hidden" name="id" value="<?= (int)$setor['id'] ?>">
                            <div class="mb-2">
                                <label class="form-label small">Nome</label>
                                <input type="text" name="nome" class="form-control form-control-sm" value="<?= htmlspecialchars($setor['nome']) ?>" required maxlength="100">
                            </div>
                            <div class="form-check mb-2">
                                <input type="checkbox" name="ativo" class="form-check-input" id="ativo<?= (int)$setor['id'] ?>" <?= $setor['ativo'] ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="ativo<?= (int)$setor['id'] ?>">Setor ativo</label>
                            </div>
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-check-lg"></i> Salvar
                            </button>
                        </form>

                        <form method="post" action="<?= url('/chamados/setores/excluir') ?>" onsubmit="return confirm('Excluir o setor &quot;<?= htmlspecialchars(addslashes($setor['nome'])) ?>&quot;?');">
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
                            <form method="post" action="<?= url('/chamados/setores/usuarios') ?>">
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
$titulo = 'Chamados - Setores';

require __DIR__ . '/../layouts/main.php';
