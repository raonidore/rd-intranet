<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<style>
.rd-setor-opcao {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 8px 10px; margin-bottom: 8px;
    border: 1px solid #e9ecef; border-radius: 8px; background: #fafbfc;
}
.rd-setor-opcao .form-check-input { margin-top: 3px; flex-shrink: 0; }
.rd-setor-opcao label { display: flex; align-items: flex-start; gap: 8px; cursor: pointer; margin: 0; }
.rd-setor-opcao label > i { font-size: 1.1rem; margin-top: 1px; }
.rd-setor-usuarios { max-height: 260px; overflow-y: auto; border: 1px solid #e9ecef; border-radius: 8px; padding: 6px 10px; }
.rd-setor-usuario-linha { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 4px; padding: 4px 0; border-bottom: 1px solid #f1f3f5; }
.rd-setor-usuario-linha:last-child { border-bottom: 0; }
.rd-setor-supervisor-check { opacity: .55; transition: opacity .15s ease; }
.rd-setor-supervisor-check:has(input:not(:disabled)) { opacity: 1; }
</style>

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
                            <div class="rd-setor-opcao">
                                <input type="checkbox" name="ativo" class="form-check-input" id="ativo<?= (int)$setor['id'] ?>" <?= $setor['ativo'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="ativo<?= (int)$setor['id'] ?>">
                                    <i class="bi bi-toggle2-on text-success"></i>
                                    <span>
                                        <strong class="d-block">Setor ativo</strong>
                                        <span class="text-muted small">Aparece pra escolher em fila, transferência e menu do chatbot.</span>
                                    </span>
                                </label>
                            </div>
                            <div class="rd-setor-opcao">
                                <input type="checkbox" name="visivel_equipe" class="form-check-input" id="visivel<?= (int)$setor['id'] ?>" <?= !empty($setor['visivel_equipe']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="visivel<?= (int)$setor['id'] ?>">
                                    <i class="bi bi-people-fill text-primary"></i>
                                    <span>
                                        <strong class="d-block">Visível para a equipe</strong>
                                        <span class="text-muted small">Quem atende neste setor vê também os atendimentos que os colegas do setor estão atendendo, não só os próprios.</span>
                                    </span>
                                </label>
                            </div>
                            <div class="rd-setor-opcao">
                                <input type="checkbox" name="nps_ativo" class="form-check-input" id="nps<?= (int)$setor['id'] ?>" <?= $setor['nps_ativo'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="nps<?= (int)$setor['id'] ?>">
                                    <i class="bi bi-emoji-smile text-info"></i>
                                    <span>
                                        <strong class="d-block">Pesquisa de satisfação (NPS)</strong>
                                        <span class="text-muted small">Pergunta ao cliente como foi o atendimento, ao encerrar.</span>
                                    </span>
                                </label>
                            </div>
                            <div class="d-flex gap-2 flex-wrap mt-3">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="bi bi-check-lg"></i> Salvar
                                </button>
                            </div>
                        </form>

                        <form method="post" action="<?= url('/whatsapp/setores/excluir') ?>" onsubmit="return confirm('Excluir o setor &quot;<?= htmlspecialchars(addslashes($setor['nome'])) ?>&quot;? Atendimentos vinculados ficam sem setor.');">
                            <input type="hidden" name="id" value="<?= (int)$setor['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i> Excluir setor
                            </button>
                        </form>
                    </div>

                    <div class="col-md-7">
                        <h6 class="mb-1">Usuários que atendem neste setor</h6>
                        <p class="text-muted small mb-2">Marque <i class="bi bi-star-fill text-warning"></i> Supervisor pra quem pode assumir o atendimento de um colega deste setor.</p>
                        <?php if (empty($usuariosAtivos)): ?>
                            <p class="text-muted small">Nenhum usuário ativo cadastrado no sistema.</p>
                        <?php else: ?>
                            <form method="post" action="<?= url('/whatsapp/setores/usuarios') ?>">
                                <input type="hidden" name="setor_id" value="<?= (int)$setor['id'] ?>">
                                <div class="rd-setor-usuarios mb-2">
                                    <?php foreach ($usuariosAtivos as $usuario): ?>
                                        <?php
                                        $marcado = in_array((int)$usuario['id'], $usuariosPorSetor[$setor['id']] ?? [], true);
                                        $ehSupervisor = in_array((int)$usuario['id'], $supervisoresPorSetor[$setor['id']] ?? [], true);
                                        $idMembro = 'u' . (int)$setor['id'] . '_' . (int)$usuario['id'];
                                        $idSupervisor = 'sup' . (int)$setor['id'] . '_' . (int)$usuario['id'];
                                        ?>
                                        <div class="rd-setor-usuario-linha">
                                            <div class="form-check">
                                                <input type="checkbox" name="usuarios[]" value="<?= (int)$usuario['id'] ?>"
                                                       class="form-check-input campo-membro-setor" id="<?= $idMembro ?>"
                                                       data-alvo-supervisor="<?= $idSupervisor ?>"
                                                       <?= $marcado ? 'checked' : '' ?>>
                                                <label class="form-check-label small" for="<?= $idMembro ?>">
                                                    <?= htmlspecialchars($usuario['nome']) ?> <span class="text-muted">(<?= htmlspecialchars($usuario['login']) ?>)</span>
                                                </label>
                                            </div>
                                            <div class="form-check form-check-inline ms-2 rd-setor-supervisor-check">
                                                <input type="checkbox" name="supervisores[]" value="<?= (int)$usuario['id'] ?>"
                                                       class="form-check-input" id="<?= $idSupervisor ?>"
                                                       <?= $marcado ? '' : 'disabled' ?> <?= $ehSupervisor ? 'checked' : '' ?>>
                                                <label class="form-check-label small text-muted" for="<?= $idSupervisor ?>" title="Pode assumir atendimento de colegas deste setor">
                                                    <i class="bi bi-star-fill text-warning"></i> Supervisor
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary">
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

<script>
document.querySelectorAll('.campo-membro-setor').forEach(function (campoMembro) {
    const campoSupervisor = document.getElementById(campoMembro.dataset.alvoSupervisor);
    if (!campoSupervisor) return;

    campoMembro.addEventListener('change', function () {
        campoSupervisor.disabled = !campoMembro.checked;
        if (!campoMembro.checked) {
            campoSupervisor.checked = false;
        }
    });
});
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'WhatsApp - Setores';

require __DIR__ . '/../layouts/main.php';
