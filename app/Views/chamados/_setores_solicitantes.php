<?php
/**
 * Aba "Setores do solicitante" de Chamados > Setores -- setores DA EMPRESA
 * de onde o chamado vem. Espera $setoresSolicitantes, $unidades e
 * $setorSolicitanteObrigatorio (ChamadoSetorController::index).
 */
$opcoesUnidade = function (?int $selecionada) use ($unidades): string {
    $html = '<option value="">Todas as unidades</option>';
    foreach ($unidades as $u) {
        $html .= '<option value="' . (int)$u['id'] . '"' . ((int)$u['id'] === $selecionada ? ' selected' : '') . '>' . htmlspecialchars($u['nome']) . '</option>';
    }
    return $html;
};
?>

<div class="alert alert-info small d-flex gap-2 align-items-start">
    <i class="bi bi-info-circle fs-5"></i>
    <div>
        Setores da empresa de onde os chamados <strong>vêm</strong> (Financeiro, RH, Vendas...). Aparecem na abertura do chamado
        como <strong>"Setor do solicitante"</strong>, logo depois da unidade, e servem para saber quem pede mais suporte.
        Não definem quem atende -- isso é a aba <a href="<?= url('/chamados/setores') ?>">Setores de atendimento</a>.
        Sem nenhum setor cadastrado aqui, o campo nem aparece.
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="post" action="<?= url('/chamados/setores/solicitantes/criar') ?>" class="row g-2">
            <div class="col-md-6">
                <input type="text" name="nome" class="form-control" placeholder="Nome do setor da empresa (ex: Financeiro, RH, Vendas)" required maxlength="100">
            </div>
            <div class="col-md-4">
                <select name="unidade_id" class="form-select" title="Unidade onde esse setor existe"><?= $opcoesUnidade(null) ?></select>
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-primary text-nowrap"><i class="bi bi-plus-lg"></i> Adicionar</button>
            </div>
        </form>

        <form method="post" action="<?= url('/chamados/setores/solicitantes/obrigatorio') ?>" class="mt-3 pt-3 border-top">
            <div class="form-check form-switch">
                <input type="checkbox" name="obrigatorio" class="form-check-input" id="setorSolicitanteObrigatorio" role="switch"
                       <?= $setorSolicitanteObrigatorio ? 'checked' : '' ?> onchange="this.form.submit()">
                <label class="form-check-label small" for="setorSolicitanteObrigatorio">
                    Exigir o setor do solicitante ao abrir chamado pelo painel
                    <span class="text-muted">(painel e agente 1.0.55+, só nas unidades que têm setor cadastrado; WhatsApp e e-mail seguem sem exigir)</span>
                </label>
            </div>
        </form>
    </div>
</div>

<?php if (empty($setoresSolicitantes)): ?>
    <p class="text-muted">Nenhum setor do solicitante cadastrado -- o campo não aparece na abertura de chamado.</p>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Setor</th>
                        <th>Unidade</th>
                        <th class="text-center">Chamados</th>
                        <th>Situação</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($setoresSolicitantes as $ss): ?>
                        <?php $idEdicao = 'editarSolicitante' . (int)$ss['id']; ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($ss['nome']) ?></td>
                            <td><?= $ss['unidade_nome'] ? htmlspecialchars($ss['unidade_nome']) : '<span class="text-muted">Todas</span>' ?></td>
                            <td class="text-center"><?= (int)$ss['total_chamados'] ?></td>
                            <td><?= $ss['ativo'] ? '<span class="badge text-bg-success">Ativo</span>' : '<span class="badge text-bg-secondary">Inativo</span>' ?></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#<?= $idEdicao ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </td>
                        </tr>
                        <tr class="collapse" id="<?= $idEdicao ?>">
                            <td colspan="5" class="bg-light">
                                <div class="d-flex flex-wrap gap-2 align-items-center py-1">
                                    <form method="post" action="<?= url('/chamados/setores/solicitantes/atualizar') ?>" class="d-flex flex-wrap gap-2 align-items-center">
                                        <input type="hidden" name="id" value="<?= (int)$ss['id'] ?>">
                                        <input type="text" name="nome" class="form-control form-control-sm" style="width:220px" value="<?= htmlspecialchars($ss['nome']) ?>" required maxlength="100">
                                        <select name="unidade_id" class="form-select form-select-sm" style="width:200px"><?= $opcoesUnidade($ss['unidade_id'] !== null ? (int)$ss['unidade_id'] : null) ?></select>
                                        <div class="form-check mb-0">
                                            <input type="checkbox" name="ativo" class="form-check-input" id="ssAtivo<?= (int)$ss['id'] ?>" <?= $ss['ativo'] ? 'checked' : '' ?>>
                                            <label class="form-check-label small" for="ssAtivo<?= (int)$ss['id'] ?>">Ativo</label>
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-check-lg"></i> Salvar</button>
                                    </form>
                                    <form method="post" action="<?= url('/chamados/setores/solicitantes/excluir') ?>" class="ms-auto"
                                          onsubmit="return confirm('Excluir o setor &quot;<?= htmlspecialchars(addslashes($ss['nome'])) ?>&quot;?');">
                                        <input type="hidden" name="id" value="<?= (int)$ss['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" <?= (int)$ss['total_chamados'] > 0 ? 'disabled title="Há chamados com esse setor -- desative em vez de excluir"' : '' ?>>
                                            <i class="bi bi-trash"></i> Excluir
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
