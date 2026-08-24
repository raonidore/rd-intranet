<?php
ob_start();

use App\Components\Alert;
use App\Services\ChamadoSlaService;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-tags me-1"></i> Chamados - Categorias</h4>
    <small class="text-muted">Categoria define o setor padrão de roteamento e o prazo de SLA por prioridade. Categoria nova já nasce com um SLA padrão -- ajuste como preferir.</small>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="post" action="<?= url('/chamados/categorias/criar') ?>" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label small mb-1">Nome</label>
                <input type="text" name="nome" class="form-control" placeholder="Ex: Impressoras" required maxlength="100">
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">Setor responsável padrão</label>
                <select name="setor_padrao_id" class="form-select">
                    <option value="">— Nenhum —</option>
                    <?php foreach ($setores as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 text-nowrap"><i class="bi bi-plus-lg"></i> Adicionar</button>
            </div>
        </form>
    </div>
</div>

<?php if (empty($categorias)): ?>
    <p class="text-muted">Nenhuma categoria cadastrada ainda.</p>
<?php endif; ?>

<?php foreach ($categorias as $categoria): ?>
    <?php $idColapso = 'categoria' . (int)$categoria['id']; ?>
    <div class="card border-0 shadow-sm mb-2">
        <div class="card-header bg-white d-flex justify-content-between align-items-center" style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#<?= $idColapso ?>">
            <div>
                <strong><?= htmlspecialchars($categoria['nome']) ?></strong>
                <?= $categoria['ativo'] ? '<span class="badge text-bg-success ms-1">Ativa</span>' : '<span class="badge text-bg-secondary ms-1">Inativa</span>' ?>
                <span class="badge text-bg-light border ms-1"><?= htmlspecialchars($categoria['setor_padrao_nome'] ?? 'Sem setor padrão') ?></span>
            </div>
            <i class="bi bi-chevron-down text-muted"></i>
        </div>
        <div class="collapse" id="<?= $idColapso ?>">
            <div class="card-body border-top">
                <div class="row g-4">
                    <div class="col-md-5">
                        <h6 class="mb-2">Dados da categoria</h6>
                        <form method="post" action="<?= url('/chamados/categorias/atualizar') ?>" class="mb-3">
                            <input type="hidden" name="id" value="<?= (int)$categoria['id'] ?>">
                            <div class="mb-2">
                                <label class="form-label small">Nome</label>
                                <input type="text" name="nome" class="form-control form-control-sm" value="<?= htmlspecialchars($categoria['nome']) ?>" required maxlength="100">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Setor responsável padrão</label>
                                <select name="setor_padrao_id" class="form-select form-select-sm">
                                    <option value="">— Nenhum —</option>
                                    <?php foreach ($setores as $s): ?>
                                        <option value="<?= (int)$s['id'] ?>" <?= (int)($categoria['setor_padrao_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nome']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-check mb-2">
                                <input type="checkbox" name="ativo" class="form-check-input" id="ativa<?= (int)$categoria['id'] ?>" <?= $categoria['ativo'] ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="ativa<?= (int)$categoria['id'] ?>">Categoria ativa</label>
                            </div>
                            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-check-lg"></i> Salvar</button>
                        </form>

                        <form method="post" action="<?= url('/chamados/categorias/excluir') ?>" onsubmit="return confirm('Excluir a categoria &quot;<?= htmlspecialchars(addslashes($categoria['nome'])) ?>&quot;?');">
                            <input type="hidden" name="id" value="<?= (int)$categoria['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Excluir categoria</button>
                        </form>
                    </div>

                    <div class="col-md-7">
                        <h6 class="mb-2">SLA por prioridade</h6>
                        <div class="d-flex fw-semibold small text-muted border-bottom pb-1 mb-1">
                            <div style="width:90px">Prioridade</div>
                            <div class="flex-fill">1ª resposta</div>
                            <div class="flex-fill">Resolução</div>
                            <div style="width:40px"></div>
                        </div>
                        <?php foreach ($slasPorCategoria[$categoria['id']] ?? [] as $sla): ?>
                            <form method="post" action="<?= url('/chamados/categorias/sla') ?>" class="d-flex align-items-center gap-2 mb-2">
                                <input type="hidden" name="id" value="<?= (int)$sla['id'] ?>">
                                <div style="width:90px" class="small"><?= htmlspecialchars(ChamadoSlaService::PRIORIDADES[$sla['prioridade']]) ?></div>
                                <div class="flex-fill input-group input-group-sm">
                                    <input type="number" name="tempo_primeira_resposta_min" class="form-control" value="<?= (int)$sla['tempo_primeira_resposta_min'] ?>" min="1">
                                    <span class="input-group-text">min</span>
                                </div>
                                <div class="flex-fill input-group input-group-sm">
                                    <input type="number" name="tempo_resolucao_min" class="form-control" value="<?= (int)$sla['tempo_resolucao_min'] ?>" min="1">
                                    <span class="input-group-text">min</span>
                                </div>
                                <button type="submit" class="btn btn-sm btn-outline-primary" style="width:40px" title="Salvar"><i class="bi bi-check-lg"></i></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Chamados - Categorias';

require __DIR__ . '/../layouts/main.php';
