<?php
/**
 * Bloco "Subcategorias" dentro do painel de uma categoria (Chamados > Categorias).
 * Espera: $categoria, $subcategorias, $setores, $slasPorSubcategoria, $slasPorCategoria.
 */

use App\Services\ChamadoSlaService;

$categoriaUsaSla = !empty($categoria['usa_sla']);
$slasDaCategoria = [];
foreach ($slasPorCategoria[$categoria['id']] ?? [] as $slaCat) {
    $slasDaCategoria[$slaCat['prioridade']] = $slaCat;
}
?>
<div class="border-top mt-4 pt-3">
    <div class="d-flex justify-content-between align-items-baseline mb-2">
        <h6 class="mb-0"><i class="bi bi-diagram-2 me-1"></i> Subcategorias</h6>
        <small class="text-muted">Detalham o assunto: <?= htmlspecialchars($categoria['nome']) ?> › ...</small>
    </div>

    <form method="post" action="<?= url('/chamados/categorias/subcategoria/criar') ?>" class="row g-2 align-items-end mb-3">
        <input type="hidden" name="categoria_id" value="<?= (int)$categoria['id'] ?>">
        <div class="col-md-5">
            <label class="form-label small mb-1">Nova subcategoria</label>
            <input type="text" name="nome" class="form-control form-control-sm" placeholder="Ex: Excel" required maxlength="100">
        </div>
        <div class="col-md-4">
            <label class="form-label small mb-1">Setor responsável</label>
            <select name="setor_padrao_id" class="form-select form-select-sm">
                <option value="">— Usar o da categoria —</option>
                <?php foreach ($setores as $s): ?>
                    <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-sm btn-primary w-100 text-nowrap"><i class="bi bi-plus-lg"></i> Adicionar subcategoria</button>
        </div>
    </form>

    <?php if (empty($subcategorias)): ?>
        <p class="text-muted small mb-0">Nenhuma subcategoria. Sem elas, o chamado fica só com a categoria, como hoje.</p>
    <?php endif; ?>

    <div class="list-group">
        <?php foreach ($subcategorias as $sub): ?>
            <?php $idSub = 'subcategoria' . (int)$sub['id']; ?>
            <div class="list-group-item p-0">
                <div class="d-flex justify-content-between align-items-center px-3 py-2" style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#<?= $idSub ?>">
                    <div class="small">
                        <span class="text-muted"><?= htmlspecialchars($categoria['nome']) ?> ›</span>
                        <strong><?= htmlspecialchars($sub['nome']) ?></strong>
                        <?php if (!$sub['ativo']): ?><span class="badge text-bg-secondary ms-1">Inativa</span><?php endif; ?>
                        <span class="badge text-bg-light border ms-1"><?= htmlspecialchars($sub['setor_padrao_nome'] ?? 'Setor da categoria') ?></span>
                        <span class="badge <?= $sub['sla_proprio'] ? 'text-bg-info' : 'text-bg-light border' ?> ms-1">
                            <i class="bi bi-stopwatch"></i> <?= $sub['sla_proprio'] ? 'SLA próprio' : ($categoriaUsaSla ? 'SLA da categoria' : 'Sem SLA') ?>
                        </span>
                        <?php if ((int)$sub['total_chamados'] > 0): ?>
                            <span class="text-muted ms-1"><?= (int)$sub['total_chamados'] ?> chamado<?= (int)$sub['total_chamados'] > 1 ? 's' : '' ?></span>
                        <?php endif; ?>
                    </div>
                    <i class="bi bi-pencil text-muted small"></i>
                </div>

                <div class="collapse" id="<?= $idSub ?>">
                    <div class="border-top px-3 py-3 bg-body-tertiary">
                        <div class="row g-4">
                            <div class="col-md-5">
                                <form method="post" action="<?= url('/chamados/categorias/subcategoria/atualizar') ?>" class="mb-2">
                                    <input type="hidden" name="id" value="<?= (int)$sub['id'] ?>">
                                    <div class="mb-2">
                                        <label class="form-label small">Nome</label>
                                        <input type="text" name="nome" class="form-control form-control-sm" value="<?= htmlspecialchars($sub['nome']) ?>" required maxlength="100">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small">Setor responsável</label>
                                        <select name="setor_padrao_id" class="form-select form-select-sm">
                                            <option value="">— Usar o da categoria —</option>
                                            <?php foreach ($setores as $s): ?>
                                                <option value="<?= (int)$s['id'] ?>" <?= (int)($sub['setor_padrao_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nome']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small d-block">Prazos (SLA)</label>
                                        <div class="form-check">
                                            <input type="radio" name="sla_modo" value="herdar" class="form-check-input" id="slaHerdar<?= (int)$sub['id'] ?>" <?= !$sub['sla_proprio'] ? 'checked' : '' ?>>
                                            <label class="form-check-label small" for="slaHerdar<?= (int)$sub['id'] ?>"><?= $categoriaUsaSla ? 'Usar os prazos da categoria' : 'Seguir a categoria (sem SLA)' ?></label>
                                        </div>
                                        <div class="form-check">
                                            <input type="radio" name="sla_modo" value="proprio" class="form-check-input" id="slaProprio<?= (int)$sub['id'] ?>" <?= $sub['sla_proprio'] ? 'checked' : '' ?>>
                                            <label class="form-check-label small" for="slaProprio<?= (int)$sub['id'] ?>">Definir prazos próprios</label>
                                        </div>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input type="checkbox" name="ativo" class="form-check-input" id="subAtiva<?= (int)$sub['id'] ?>" <?= $sub['ativo'] ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="subAtiva<?= (int)$sub['id'] ?>">Subcategoria ativa</label>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-check-lg"></i> Salvar</button>
                                </form>

                                <form method="post" action="<?= url('/chamados/categorias/subcategoria/excluir') ?>" onsubmit="return confirm('Excluir a subcategoria &quot;<?= htmlspecialchars(addslashes($sub['nome'])) ?>&quot;?');">
                                    <input type="hidden" name="id" value="<?= (int)$sub['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Excluir subcategoria</button>
                                </form>
                            </div>

                            <div class="col-md-7">
                                <h6 class="mb-2 small fw-semibold">SLA por prioridade</h6>
                                <div class="d-flex fw-semibold small text-muted border-bottom pb-1 mb-1">
                                    <div style="width:90px">Prioridade</div>
                                    <div class="flex-fill">1ª resposta</div>
                                    <div class="flex-fill">Resolução</div>
                                    <div style="width:40px"></div>
                                </div>

                                <?php if ($sub['sla_proprio']): ?>
                                    <?php foreach ($slasPorSubcategoria[$sub['id']] ?? [] as $sla): ?>
                                        <form method="post" action="<?= url('/chamados/categorias/subcategoria/sla') ?>" class="d-flex align-items-center gap-2 mb-2">
                                            <input type="hidden" name="id" value="<?= (int)$sla['id'] ?>">
                                            <input type="hidden" name="categoria_id" value="<?= (int)$categoria['id'] ?>">
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
                                <?php elseif (!$categoriaUsaSla): ?>
                                    <div class="small text-muted"><i class="bi bi-info-circle"></i> A categoria está sem SLA, então chamados desta subcategoria também abrem sem prazo. Para ter prazo só aqui, escolha "Definir prazos próprios" e salve.</div>
                                <?php else: ?>
                                    <?php foreach (['urgente', 'alta', 'media', 'baixa'] as $prioridade): ?>
                                        <?php $slaCat = $slasDaCategoria[$prioridade] ?? null; ?>
                                        <div class="d-flex align-items-center gap-2 mb-1 small text-muted">
                                            <div style="width:90px"><?= htmlspecialchars(ChamadoSlaService::PRIORIDADES[$prioridade]) ?></div>
                                            <div class="flex-fill"><?= $slaCat ? (int)$slaCat['tempo_primeira_resposta_min'] . ' min' : '—' ?></div>
                                            <div class="flex-fill"><?= $slaCat ? (int)$slaCat['tempo_resolucao_min'] . ' min' : '—' ?></div>
                                            <div style="width:40px"></div>
                                        </div>
                                    <?php endforeach; ?>
                                    <div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> Usando os prazos da categoria. Escolha "Definir prazos próprios" e salve para editar.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
