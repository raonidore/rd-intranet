<?php
ob_start();

use App\Components\Alert;
use App\Services\ProjetoService;
use App\Services\ProjetoTarefaService;

$statusClasses = [
    'planejamento' => 'text-bg-light border',
    'em_andamento' => 'text-bg-primary',
    'pausado' => 'text-bg-warning',
    'concluido' => 'text-bg-success',
    'cancelado' => 'text-bg-secondary',
];

$colunaLabels = [
    'a_fazer' => 'A fazer',
    'em_andamento' => 'Em andamento',
    'aguardando_terceiro' => 'Aguardando terceiro',
    'concluido' => 'Concluído',
];

/** @var ProjetoTarefaService $tarefaService */
?>

<?= Alert::flash() ?>

<div class="mb-4 d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <a href="<?= url('/projetos') ?>" class="text-decoration-none small text-muted d-block mb-1">
            <i class="bi bi-arrow-left"></i> Projetos
        </a>
        <h4 class="mb-1">
            <span class="badge text-bg-light border me-1"><?= htmlspecialchars($projeto['area_nome']) ?></span>
            <?= htmlspecialchars($projeto['titulo']) ?>
        </h4>
        <span class="badge <?= $statusClasses[$projeto['status']] ?? '' ?>"><?= ProjetoService::statusLabel($projeto['status']) ?></span>
        <?php if (!empty($projeto['cliente'])): ?>
            <span class="text-muted small ms-2"><i class="bi bi-building"></i> <?= htmlspecialchars($projeto['cliente']) ?></span>
        <?php endif; ?>
    </div>
    <?php if ($podeGerenciar): ?>
        <div class="d-flex gap-2">
            <select class="form-select form-select-sm" style="width:auto" onchange="mudarStatusProjeto(this.value)">
                <?php foreach (ProjetoService::statusLabelTodos() as $valor => $label): ?>
                    <option value="<?= $valor ?>" <?= $projeto['status'] === $valor ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalEditarProjeto">
                <i class="bi bi-pencil"></i> Editar
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalDuplicar">
                <i class="bi bi-copy"></i> Duplicar
            </button>
            <form method="post" action="<?= url('/projetos/excluir') ?>" onsubmit="return confirm('Excluir este projeto? Ele vai pra lixeira por 30 dias, dá pra restaurar até lá.');">
                <input type="hidden" name="id" value="<?= (int)$projeto['id'] ?>">
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-trash3"></i> Excluir
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($projeto['descricao'])): ?>
    <p class="text-muted mb-4" style="white-space:pre-wrap"><?= htmlspecialchars($projeto['descricao']) ?></p>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-4 col-6">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold"><?= (int)$resumo['total'] ?></div>
                <div class="text-muted small">Tarefas ativas</div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-6">
        <div class="card border-0 shadow-sm text-center <?= $resumo['atrasadas'] > 0 ? 'border-danger' : '' ?>">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold <?= $resumo['atrasadas'] > 0 ? 'text-danger' : '' ?>"><?= (int)$resumo['atrasadas'] ?></div>
                <div class="text-muted small">Atrasadas</div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-6">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold"><?= (int)$resumo['aguardando_terceiro'] ?></div>
                <div class="text-muted small">Aguardando terceiro</div>
            </div>
        </div>
    </div>
</div>

<?php if ((int)$projeto['usa_fases'] && !empty($fases)): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="card-title mb-0"><i class="bi bi-signpost-split"></i> Fases</h6>
            <?php if ($podeGerenciar): ?>
                <button type="button" class="btn btn-link btn-sm p-0" data-bs-toggle="modal" data-bs-target="#modalNovaFase">
                    <i class="bi bi-plus-lg"></i> Nova fase
                </button>
            <?php endif; ?>
        </div>
        <div class="row g-3">
            <?php foreach ($fases as $fase): $progresso = $tarefaService->progressoPorFase((int)$fase['id']); ?>
                <div class="col-md-3 col-6">
                    <div class="border rounded p-2">
                        <div class="d-flex justify-content-between small">
                            <strong><?= htmlspecialchars($fase['nome']) ?></strong>
                            <?php if ($podeGerenciar): ?>
                                <button type="button" class="btn btn-link btn-sm p-0 text-muted btn-editar-fase"
                                    data-id="<?= (int)$fase['id'] ?>" data-nome="<?= htmlspecialchars($fase['nome']) ?>"
                                    data-ordem="<?= (int)$fase['ordem'] ?>" data-inicio="<?= htmlspecialchars($fase['data_inicio'] ?? '') ?>"
                                    data-fim="<?= htmlspecialchars($fase['data_fim_prevista'] ?? '') ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                        <?php if ($fase['data_inicio'] || $fase['data_fim_prevista']): ?>
                            <div class="text-muted small font-monospace">
                                <?= $fase['data_inicio'] ? date('d/m', strtotime($fase['data_inicio'])) : '?' ?> → <?= $fase['data_fim_prevista'] ? date('d/m', strtotime($fase['data_fim_prevista'])) : '?' ?>
                            </div>
                        <?php endif; ?>
                        <div class="progress mt-2" style="height:6px">
                            <div class="progress-bar" style="width:<?= $progresso ?>%"></div>
                        </div>
                        <div class="text-end small text-muted mt-1"><?= $progresso ?>%</div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php elseif ($podeGerenciar && (int)$projeto['usa_fases']): ?>
<div class="mb-4">
    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovaFase">
        <i class="bi bi-plus-lg"></i> Adicionar a primeira fase
    </button>
</div>
<?php endif; ?>

<style>
.gantt-row { height: 34px; border-bottom: 1px solid #eef0f3; }
.gantt-label { width: 220px; flex-shrink: 0; font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding-right: 8px; }
.gantt-track { flex: 1; position: relative; }
.gantt-bar { position: absolute; top: 8px; height: 18px; border-radius: 5px; background: #8b95a5; }
.gantt-bar.gantt-fase { background: #495364; top: 6px; height: 22px; }
.gantt-bar.gantt-a_fazer { background: #adb5bd; }
.gantt-bar.gantt-em_andamento { background: #0d6efd; }
.gantt-bar.gantt-aguardando_terceiro { background: #ffc107; }
.gantt-bar.gantt-concluido { background: #198754; }
.gantt-bar.gantt-atrasada { background: #dc3545; }
.gantt-marco { position: absolute; top: 9px; width: 16px; height: 16px; background: #495364; transform: translateX(-8px) rotate(45deg); border-radius: 3px; }
.gantt-hoje { position: absolute; top: 0; bottom: 0; width: 2px; background: #dc3545; opacity: .5; z-index: 2; }
.gantt-wrap { overflow-x: auto; }
</style>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#painelQuadro" type="button">
                    <i class="bi bi-kanban"></i> Quadro
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#painelCronograma" type="button">
                    <i class="bi bi-bar-chart-steps"></i> Cronograma
                </button>
            </li>
            <li class="nav-item ms-auto">
                <button type="button" class="btn btn-primary btn-sm mt-1" data-bs-toggle="modal" data-bs-target="#modalNovaTarefa">
                    <i class="bi bi-plus-lg"></i> Nova tarefa
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="painelQuadro">
                <div class="kanban-board d-flex gap-3" style="overflow-x:auto">
                    <?php foreach ($colunaLabels as $colunaChave => $colunaLabel): ?>
                        <div class="kanban-col flex-shrink-0" style="width:270px">
                            <div class="d-flex justify-content-between align-items-center mb-2 small text-uppercase text-muted fw-semibold">
                                <span><?= $colunaLabel ?></span>
                                <span class="badge text-bg-light border"><?= count($quadro[$colunaChave]) ?></span>
                            </div>
                            <div class="kanban-lista d-flex flex-column gap-2 p-2 rounded" style="min-height:80px; background:#f4f6f9" data-coluna="<?= $colunaChave ?>">
                                <?php foreach ($quadro[$colunaChave] as $tarefa):
                                    $atrasada = $tarefa['coluna'] !== 'concluido' && !empty($tarefa['prazo']) && strtotime($tarefa['prazo']) < strtotime(date('Y-m-d'));
                                ?>
                                    <div class="card shadow-sm kanban-card" data-id="<?= (int)$tarefa['id'] ?>" style="cursor:pointer" data-bs-toggle="modal" data-bs-target="#modalTarefa<?= (int)$tarefa['id'] ?>">
                                        <div class="card-body p-2">
                                            <?php if ($tarefa['tag']): ?>
                                                <span class="badge text-bg-light border mb-1"><?= htmlspecialchars($tarefa['tag']) ?></span>
                                            <?php endif; ?>
                                            <div class="small fw-medium"><?= htmlspecialchars($tarefa['titulo']) ?></div>
                                            <?php if ($tarefa['fase_nome']): ?>
                                                <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($tarefa['fase_nome']) ?></div>
                                            <?php endif; ?>
                                            <div class="d-flex justify-content-between align-items-center mt-2">
                                                <span class="small <?= $atrasada ? 'text-danger fw-semibold' : 'text-muted' ?>">
                                                    <?= $tarefa['prazo'] ? date('d/m', strtotime($tarefa['prazo'])) : '' ?>
                                                </span>
                                                <span class="small text-muted">
                                                    <?php if ($tarefa['total_responsaveis'] || $tarefa['total_externos']): ?>
                                                        <i class="bi bi-people"></i> <?= (int)$tarefa['total_responsaveis'] + (int)$tarefa['total_externos'] ?>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="tab-pane fade" id="painelCronograma">
                <?php if (empty($gantt['linhas'])): ?>
                    <p class="text-muted small mb-0">Defina data de início/fim numa fase, ou data de início/prazo numa tarefa, pra elas aparecerem aqui.</p>
                <?php else: ?>
                    <div class="d-flex justify-content-between small text-muted mb-2">
                        <span><?= date('d/m/Y', strtotime($gantt['inicio'])) ?></span>
                        <span><?= date('d/m/Y', strtotime($gantt['fim'])) ?></span>
                    </div>
                    <div class="gantt-wrap" style="min-width:600px">
                        <?php foreach ($gantt['linhas'] as $linha): ?>
                            <div class="gantt-row d-flex align-items-center">
                                <div class="gantt-label fw-semibold"><?= htmlspecialchars($linha['nome']) ?></div>
                                <div class="gantt-track">
                                    <?php if ($gantt['hoje_pct'] !== null): ?>
                                        <div class="gantt-hoje" style="left:<?= $gantt['hoje_pct'] ?>%"></div>
                                    <?php endif; ?>
                                    <?php if ($linha['tipo'] === 'barra'): ?>
                                        <div class="gantt-bar gantt-fase" style="left:<?= $linha['left'] ?>%; width:<?= $linha['width'] ?>%" title="<?= htmlspecialchars($linha['nome']) ?>"></div>
                                    <?php elseif ($linha['tipo'] === 'marco'): ?>
                                        <div class="gantt-marco" style="left:<?= $linha['left'] ?>%" title="<?= htmlspecialchars($linha['nome']) ?> (marco)"></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php foreach ($linha['tarefas'] as $tarefa):
                                $atrasadaGantt = $tarefa['coluna'] !== 'concluido' && !empty($tarefa['prazo']) && strtotime($tarefa['prazo']) < strtotime(date('Y-m-d'));
                                $corBarra = $atrasadaGantt ? 'gantt-atrasada' : 'gantt-' . $tarefa['coluna'];
                            ?>
                                <div class="gantt-row d-flex align-items-center">
                                    <div class="gantt-label ps-3 text-muted"><?= htmlspecialchars($tarefa['titulo']) ?></div>
                                    <div class="gantt-track">
                                        <?php if ($gantt['hoje_pct'] !== null): ?>
                                            <div class="gantt-hoje" style="left:<?= $gantt['hoje_pct'] ?>%"></div>
                                        <?php endif; ?>
                                        <?php if ($tarefa['tipo'] === 'barra'): ?>
                                            <div class="gantt-bar <?= $corBarra ?>" style="left:<?= $tarefa['left'] ?>%; width:<?= $tarefa['width'] ?>%" title="<?= htmlspecialchars($tarefa['titulo']) ?>"></div>
                                        <?php else: ?>
                                            <div class="gantt-marco <?= $corBarra ?>" style="left:<?= $tarefa['left'] ?>%" title="<?= htmlspecialchars($tarefa['titulo']) ?> (marco)"></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-muted small mt-2 mb-0"><i class="bi bi-info-circle"></i> Losango = marco (só uma data conhecida); barra = intervalo. Linha vermelha = hoje.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h6 class="card-title mb-3"><i class="bi bi-clock-history"></i> Linha do tempo</h6>
        <ul class="list-unstyled mb-3">
            <?php if (empty($timeline)): ?>
                <li class="text-muted small">Nada por aqui ainda.</li>
            <?php endif; ?>
            <?php foreach (array_reverse($timeline) as $item): ?>
                <li class="mb-3 pb-3 border-bottom">
                    <?php if ($item['tipo'] === 'sistema'): ?>
                        <div class="small text-muted">
                            <i class="bi bi-gear"></i> <?= htmlspecialchars($item['conteudo']) ?>
                            <?php if ($item['tarefa_titulo']): ?> · <span class="fst-italic"><?= htmlspecialchars($item['tarefa_titulo']) ?></span><?php endif; ?>
                            · <?= date('d/m/Y H:i', strtotime($item['criado_em'])) ?>
                        </div>
                    <?php else: ?>
                        <div>
                            <strong><?= htmlspecialchars($item['usuario_nome'] ?? $item['participante_nome'] ?? 'Alguém') ?></strong>
                            <?php if ($item['participante_nome']): ?><span class="badge text-bg-light border ms-1">externo</span><?php endif; ?>
                            <?php if ($item['tarefa_titulo']): ?><span class="text-muted small"> em "<?= htmlspecialchars($item['tarefa_titulo']) ?>"</span><?php endif; ?>
                            <span class="text-muted small">· <?= date('d/m/Y H:i', strtotime($item['criado_em'])) ?></span>
                            <p class="mb-0 mt-1" style="white-space:pre-wrap"><?= htmlspecialchars($item['conteudo']) ?></p>
                            <?php if (!empty($item['latitude'])): ?>
                                <a class="small" target="_blank" rel="noopener" href="https://www.google.com/maps?q=<?= $item['latitude'] ?>,<?= $item['longitude'] ?>">
                                    <i class="bi bi-geo-alt"></i> Ver no mapa
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <form method="post" action="<?= url('/projetos/comentar') ?>" enctype="multipart/form-data" id="formComentarProjeto">
            <input type="hidden" name="projeto_id" value="<?= (int)$projeto['id'] ?>">
            <input type="hidden" name="latitude" id="comentarioLatitude">
            <input type="hidden" name="longitude" id="comentarioLongitude">
            <div class="mb-2">
                <select name="tarefa_id" class="form-select form-select-sm" style="max-width:320px">
                    <option value="">Nota geral do projeto</option>
                    <?php foreach ($quadro as $colunaTarefas): foreach ($colunaTarefas as $tarefa): ?>
                        <option value="<?= (int)$tarefa['id'] ?>"><?= htmlspecialchars($tarefa['titulo']) ?></option>
                    <?php endforeach; endforeach; ?>
                </select>
            </div>
            <textarea name="conteudo" class="form-control mb-2" rows="2" placeholder="Escreva um comentário..." required></textarea>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex gap-2 align-items-center">
                    <label class="btn btn-outline-secondary btn-sm mb-0">
                        <i class="bi bi-camera"></i> Foto/anexo
                        <input type="file" name="arquivo" accept="image/*,application/pdf" capture="environment" class="d-none" id="comentarioArquivo">
                    </label>
                    <span class="small text-muted" id="comentarioArquivoNome"></span>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnComentarioLocalizacao">
                        <i class="bi bi-geo-alt"></i> Localização
                    </button>
                    <span class="small text-success d-none" id="comentarioLocalizacaoOk"><i class="bi bi-check-circle"></i> Anexada</span>
                </div>
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-send"></i> Comentar</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h6 class="card-title mb-3"><i class="bi bi-paperclip"></i> Anexos do projeto</h6>
        <?php if (empty($anexos)): ?>
            <p class="text-muted small mb-0">Nenhum anexo ainda.</p>
        <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($anexos as $anexo): ?>
                    <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                        <span>
                            <i class="bi bi-<?= $anexo['anexo_origem'] === 'samba' ? 'folder-symlink' : 'paperclip' ?>"></i>
                            <?= htmlspecialchars($anexo['anexo_nome_original']) ?>
                            <?php if ($anexo['tarefa_titulo']): ?><span class="text-muted small">(<?= htmlspecialchars($anexo['tarefa_titulo']) ?>)</span><?php endif; ?>
                        </span>
                        <a href="<?= url('/projetos/anexo?anexo_id=' . (int)$anexo['id']) ?>" class="btn btn-link btn-sm p-0"><i class="bi bi-download"></i></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <form method="post" action="<?= url('/projetos/anexo-upload') ?>" enctype="multipart/form-data" class="mt-2">
            <input type="hidden" name="projeto_id" value="<?= (int)$projeto['id'] ?>">
            <label class="btn btn-link btn-sm p-0">
                Enviar arquivo
                <input type="file" name="arquivo" class="d-none" onchange="this.form.submit()">
            </label>
        </form>
    </div>
</div>

<!-- Modal editar projeto -->
<div class="modal fade" id="modalEditarProjeto" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?= url('/projetos/editar') ?>">
                <input type="hidden" name="id" value="<?= (int)$projeto['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Editar projeto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Título *</label>
                            <input type="text" name="titulo" class="form-control" required maxlength="200" value="<?= htmlspecialchars($projeto['titulo']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cliente</label>
                            <input type="text" name="cliente" class="form-control" maxlength="150" value="<?= htmlspecialchars($projeto['cliente'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Prioridade</label>
                            <select name="prioridade" class="form-select">
                                <?php foreach (['baixa' => 'Baixa', 'media' => 'Média', 'alta' => 'Alta', 'urgente' => 'Urgente'] as $valor => $label): ?>
                                    <option value="<?= $valor ?>" <?= $projeto['prioridade'] === $valor ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Início previsto</label>
                            <input type="date" name="data_inicio" class="form-control" value="<?= htmlspecialchars($projeto['data_inicio'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fim previsto</label>
                            <input type="date" name="data_fim_prevista" class="form-control" value="<?= htmlspecialchars($projeto['data_fim_prevista'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="usa_fases" value="1" id="editUsaFases" <?= $projeto['usa_fases'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="editUsaFases">Organizar em fases</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descrição</label>
                            <textarea name="descricao" class="form-control" rows="4"><?= htmlspecialchars($projeto['descricao'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal duplicar -->
<div class="modal fade" id="modalDuplicar" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="post" action="<?= url('/projetos/duplicar') ?>">
                <input type="hidden" name="id" value="<?= (int)$projeto['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Duplicar projeto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">Cria um projeto novo com a mesma estrutura de fases (sem as tarefas).</p>
                    <label class="form-label">Título do novo projeto</label>
                    <input type="text" name="novo_titulo" class="form-control" maxlength="200" placeholder="<?= htmlspecialchars($projeto['titulo']) ?> (cópia)">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Duplicar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal nova fase -->
<div class="modal fade" id="modalNovaFase" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="post" action="<?= url('/projetos/fases/criar') ?>">
                <input type="hidden" name="projeto_id" value="<?= (int)$projeto['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Nova fase</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Nome *</label>
                    <input type="text" name="nome" class="form-control mb-2" required maxlength="150" placeholder="Ex: Levantamento">
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small">Início</label>
                            <input type="date" name="data_inicio" class="form-control form-control-sm">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Fim previsto</label>
                            <input type="date" name="data_fim_prevista" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Adicionar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal editar fase -->
<div class="modal fade" id="modalEditarFase" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="post" action="<?= url('/projetos/fases/atualizar') ?>">
                <input type="hidden" name="projeto_id" value="<?= (int)$projeto['id'] ?>">
                <input type="hidden" name="id" id="editarFaseId">
                <div class="modal-header">
                    <h5 class="modal-title">Editar fase</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Nome *</label>
                    <input type="text" name="nome" id="editarFaseNome" class="form-control mb-2" required maxlength="150">
                    <label class="form-label small">Ordem</label>
                    <input type="number" name="ordem" id="editarFaseOrdem" class="form-control form-control-sm mb-2">
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small">Início</label>
                            <input type="date" name="data_inicio" id="editarFaseInicio" class="form-control form-control-sm">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Fim previsto</label>
                            <input type="date" name="data_fim_prevista" id="editarFaseFim" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger me-auto btn-excluir-fase">Excluir fase</button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
            <form method="post" action="<?= url('/projetos/fases/excluir') ?>" id="formExcluirFase" class="d-none">
                <input type="hidden" name="projeto_id" value="<?= (int)$projeto['id'] ?>">
                <input type="hidden" name="id" id="excluirFaseId">
            </form>
        </div>
    </div>
</div>

<!-- Modal nova tarefa -->
<div class="modal fade" id="modalNovaTarefa" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?= url('/projetos/tarefas/criar') ?>">
                <input type="hidden" name="projeto_id" value="<?= (int)$projeto['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Nova tarefa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Título *</label>
                            <input type="text" name="titulo" class="form-control" required maxlength="200">
                        </div>
                        <?php if (!empty($fases)): ?>
                        <div class="col-md-6">
                            <label class="form-label">Fase</label>
                            <select name="fase_id" class="form-select">
                                <option value="">Sem fase</option>
                                <?php foreach ($fases as $fase): ?>
                                    <option value="<?= (int)$fase['id'] ?>"><?= htmlspecialchars($fase['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-6">
                            <label class="form-label">Início <span class="text-muted fw-normal">(opcional -- pra aparecer como barra no Cronograma)</span></label>
                            <input type="date" name="data_inicio" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Prazo</label>
                            <input type="date" name="prazo" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tag <span class="text-muted fw-normal">(opcional)</span></label>
                            <input type="text" name="tag" class="form-control" maxlength="60" placeholder="Ex: Segurança, Infra...">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descrição</label>
                            <textarea name="descricao" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($quadro as $colunaTarefas): foreach ($colunaTarefas as $tarefa):
    $pessoas = $tarefaService->pessoas((int)$tarefa['id']);
    $comentariosTarefa = array_values(array_filter($timeline, fn ($c) => (int)($c['tarefa_id'] ?? 0) === (int)$tarefa['id']));
    $anexosTarefa = array_values(array_filter($anexos, fn ($a) => (int)($a['tarefa_id'] ?? 0) === (int)$tarefa['id']));
?>
<!-- Modal detalhe da tarefa #<?= (int)$tarefa['id'] ?> -->
<div class="modal fade" id="modalTarefa<?= (int)$tarefa['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= htmlspecialchars($tarefa['titulo']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="<?= url('/projetos/tarefas/atualizar') ?>" class="mb-3">
                    <input type="hidden" name="id" value="<?= (int)$tarefa['id'] ?>">
                    <input type="hidden" name="projeto_id" value="<?= (int)$projeto['id'] ?>">
                    <div class="row g-2">
                        <div class="col-12">
                            <input type="text" name="titulo" class="form-control form-control-sm" value="<?= htmlspecialchars($tarefa['titulo']) ?>" maxlength="200" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-0">Início</label>
                            <input type="date" name="data_inicio" class="form-control form-control-sm" value="<?= htmlspecialchars($tarefa['data_inicio'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-0">Prazo</label>
                            <input type="date" name="prazo" class="form-control form-control-sm" value="<?= htmlspecialchars($tarefa['prazo'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <input type="text" name="tag" class="form-control form-control-sm" value="<?= htmlspecialchars($tarefa['tag'] ?? '') ?>" placeholder="Tag" maxlength="60">
                        </div>
                        <?php if (!empty($fases)): ?>
                        <div class="col-12">
                            <select name="fase_id" class="form-select form-select-sm">
                                <option value="">Sem fase</option>
                                <?php foreach ($fases as $fase): ?>
                                    <option value="<?= (int)$fase['id'] ?>" <?= (int)($tarefa['fase_id'] ?? 0) === (int)$fase['id'] ? 'selected' : '' ?>><?= htmlspecialchars($fase['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-12">
                            <textarea name="descricao" class="form-control form-control-sm" rows="2" placeholder="Descrição"><?= htmlspecialchars($tarefa['descricao'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-outline-primary btn-sm">Salvar</button>
                            <button type="button" class="btn btn-outline-danger btn-sm btn-excluir-tarefa" data-id="<?= (int)$tarefa['id'] ?>">Excluir tarefa</button>
                        </div>
                    </div>
                </form>

                <h6 class="small text-uppercase text-muted">Pessoas</h6>
                <ul class="list-group list-group-flush mb-2">
                    <?php foreach ($pessoas as $pessoa): ?>
                        <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                            <span><?= htmlspecialchars($pessoa['nome']) ?> <span class="badge text-bg-light border"><?= $pessoa['tipo'] === 'interno' ? 'interno' : 'externo' ?></span></span>
                            <form method="post" action="<?= url($pessoa['tipo'] === 'interno' ? '/projetos/tarefas/responsavel-remover' : '/projetos/tarefas/externo-remover') ?>">
                                <input type="hidden" name="tarefa_id" value="<?= (int)$tarefa['id'] ?>">
                                <input type="hidden" name="projeto_id" value="<?= (int)$projeto['id'] ?>">
                                <input type="hidden" name="<?= $pessoa['tipo'] === 'interno' ? 'usuario_id' : 'participante_externo_id' ?>" value="<?= (int)$pessoa['id'] ?>">
                                <button type="submit" class="btn btn-link btn-sm text-danger p-0"><i class="bi bi-x-lg"></i></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="row g-2 mb-3">
                    <div class="col-md-6 position-relative">
                        <input type="text" class="form-control form-control-sm campo-buscar-responsavel" data-tarefa-id="<?= (int)$tarefa['id'] ?>" placeholder="Adicionar responsável interno..." autocomplete="off">
                        <div class="list-group position-absolute w-100 shadow-sm d-none lista-responsaveis-sugeridos" style="z-index:20; max-height:200px; overflow-y:auto"></div>
                    </div>
                    <div class="col-md-6 position-relative">
                        <input type="text" class="form-control form-control-sm campo-buscar-externo" data-tarefa-id="<?= (int)$tarefa['id'] ?>" placeholder="Adicionar participante externo..." autocomplete="off">
                        <div class="list-group position-absolute w-100 shadow-sm d-none lista-externos-sugeridos" style="z-index:20; max-height:200px; overflow-y:auto"></div>
                    </div>
                </div>
                <form method="post" action="<?= url('/projetos/tarefas/externo-adicionar') ?>" class="d-none form-cadastrar-externo" data-tarefa-id="<?= (int)$tarefa['id'] ?>">
                    <input type="hidden" name="tarefa_id" value="<?= (int)$tarefa['id'] ?>">
                    <input type="hidden" name="projeto_id" value="<?= (int)$projeto['id'] ?>">
                    <div class="row g-2 mb-3 p-2 border rounded">
                        <div class="col-md-6"><input type="text" name="nome" class="form-control form-control-sm" placeholder="Nome *" required></div>
                        <div class="col-md-6"><input type="email" name="email" class="form-control form-control-sm" placeholder="E-mail"></div>
                        <div class="col-md-6"><input type="text" name="telefone" class="form-control form-control-sm" placeholder="Telefone/WhatsApp"></div>
                        <div class="col-md-6"><input type="text" name="empresa" class="form-control form-control-sm" placeholder="Empresa"></div>
                        <div class="col-12 text-end"><button type="submit" class="btn btn-primary btn-sm">Cadastrar e adicionar</button></div>
                    </div>
                </form>

                <h6 class="small text-uppercase text-muted">Comentários</h6>
                <ul class="list-unstyled mb-2">
                    <?php if (empty($comentariosTarefa)): ?>
                        <li class="text-muted small">Nenhum comentário nesta tarefa ainda.</li>
                    <?php endif; ?>
                    <?php foreach ($comentariosTarefa as $c): ?>
                        <li class="mb-2 pb-2 border-bottom small">
                            <strong><?= htmlspecialchars($c['usuario_nome'] ?? $c['participante_nome'] ?? 'Alguém') ?></strong>
                            <span class="text-muted">· <?= date('d/m/Y H:i', strtotime($c['criado_em'])) ?></span>
                            <div style="white-space:pre-wrap"><?= htmlspecialchars($c['conteudo']) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <h6 class="small text-uppercase text-muted">Anexos</h6>
                <ul class="list-unstyled mb-0">
                    <?php if (empty($anexosTarefa)): ?>
                        <li class="text-muted small">Nenhum anexo nesta tarefa.</li>
                    <?php endif; ?>
                    <?php foreach ($anexosTarefa as $a): ?>
                        <li class="small d-flex justify-content-between">
                            <span><?= htmlspecialchars($a['anexo_nome_original']) ?></span>
                            <a href="<?= url('/projetos/anexo?anexo_id=' . (int)$a['id']) ?>"><i class="bi bi-download"></i></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php endforeach; endforeach; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
<script>
(function () {
    const URL_MOVER = <?= json_encode(url('/projetos/tarefas/mover')) ?>;

    document.querySelectorAll('.kanban-lista').forEach(function (lista) {
        Sortable.create(lista, {
            group: 'kanban',
            animation: 150,
            onEnd: function (evt) {
                const item = evt.item;
                const novaColuna = evt.to.dataset.coluna;
                const novaPosicao = evt.newIndex;

                fetch(URL_MOVER, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ id: item.dataset.id, coluna: novaColuna, posicao: novaPosicao }),
                }).catch(function () {});
            },
        });
    });

    function mudarStatusProjeto(status) {
        const form = document.createElement('form');
        form.method = 'post';
        form.action = <?= json_encode(url('/projetos/status')) ?>;
        form.innerHTML = '<input type="hidden" name="id" value="<?= (int)$projeto['id'] ?>"><input type="hidden" name="status" value="' + status + '">';
        document.body.appendChild(form);
        form.submit();
    }
    window.mudarStatusProjeto = mudarStatusProjeto;

    document.querySelectorAll('.btn-editar-fase').forEach(function (botao) {
        botao.addEventListener('click', function () {
            document.getElementById('editarFaseId').value = botao.dataset.id;
            document.getElementById('editarFaseNome').value = botao.dataset.nome;
            document.getElementById('editarFaseOrdem').value = botao.dataset.ordem;
            document.getElementById('editarFaseInicio').value = botao.dataset.inicio;
            document.getElementById('editarFaseFim').value = botao.dataset.fim;
            document.getElementById('excluirFaseId').value = botao.dataset.id;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditarFase')).show();
        });
    });
    document.querySelector('.btn-excluir-fase')?.addEventListener('click', function () {
        if (confirm('Excluir esta fase? As tarefas dela continuam no projeto, soltas.')) {
            document.getElementById('formExcluirFase').submit();
        }
    });

    document.querySelectorAll('.btn-excluir-tarefa').forEach(function (botao) {
        botao.addEventListener('click', function () {
            if (!confirm('Excluir esta tarefa?')) return;
            const form = document.createElement('form');
            form.method = 'post';
            form.action = <?= json_encode(url('/projetos/tarefas/excluir')) ?>;
            form.innerHTML = '<input type="hidden" name="id" value="' + botao.dataset.id + '"><input type="hidden" name="projeto_id" value="<?= (int)$projeto['id'] ?>">';
            document.body.appendChild(form);
            form.submit();
        });
    });

    // --- Comentário: nome do arquivo escolhido + localização opcional ---
    const campoArquivo = document.getElementById('comentarioArquivo');
    campoArquivo?.addEventListener('change', function () {
        document.getElementById('comentarioArquivoNome').textContent = this.files[0] ? this.files[0].name : '';
    });

    document.getElementById('btnComentarioLocalizacao')?.addEventListener('click', function () {
        if (!navigator.geolocation) { alert('Seu navegador não suporta localização.'); return; }
        navigator.geolocation.getCurrentPosition(function (pos) {
            document.getElementById('comentarioLatitude').value = pos.coords.latitude;
            document.getElementById('comentarioLongitude').value = pos.coords.longitude;
            document.getElementById('comentarioLocalizacaoOk').classList.remove('d-none');
        }, function () {
            alert('Não foi possível obter sua localização.');
        });
    });

    // --- Autocomplete: responsável interno ---
    document.querySelectorAll('.campo-buscar-responsavel').forEach(function (campo) {
        const lista = campo.closest('.position-relative').querySelector('.lista-responsaveis-sugeridos');
        let timer = null;
        campo.addEventListener('input', function () {
            clearTimeout(timer);
            const termo = campo.value.trim();
            if (termo.length < 2) { lista.classList.add('d-none'); return; }
            timer = setTimeout(async () => {
                const res = await fetch(<?= json_encode(url('/projetos/tarefas/usuarios-buscar')) ?> + '?q=' + encodeURIComponent(termo));
                const dados = await res.json();
                lista.innerHTML = '';
                if (!dados.success || !dados.usuarios.length) { lista.classList.add('d-none'); return; }
                dados.usuarios.forEach(u => {
                    const item = document.createElement('button');
                    item.type = 'button';
                    item.className = 'list-group-item list-group-item-action';
                    item.textContent = u.nome + (u.email ? ' (' + u.email + ')' : '');
                    item.onclick = async () => {
                        await fetch(<?= json_encode(url('/projetos/tarefas/responsavel-adicionar')) ?>, {
                            method: 'POST',
                            body: new URLSearchParams({ tarefa_id: campo.dataset.tarefaId, projeto_id: '<?= (int)$projeto['id'] ?>', usuario_id: u.id }),
                        });
                        location.reload();
                    };
                    lista.appendChild(item);
                });
                lista.classList.remove('d-none');
            }, 300);
        });
    });

    // --- Autocomplete: participante externo (com opção de cadastrar avulso) ---
    document.querySelectorAll('.campo-buscar-externo').forEach(function (campo) {
        const lista = campo.closest('.position-relative').querySelector('.lista-externos-sugeridos');
        const formCadastro = document.querySelector('.form-cadastrar-externo[data-tarefa-id="' + campo.dataset.tarefaId + '"]');
        let timer = null;
        campo.addEventListener('input', function () {
            clearTimeout(timer);
            const termo = campo.value.trim();
            if (termo.length < 2) { lista.classList.add('d-none'); return; }
            timer = setTimeout(async () => {
                const res = await fetch(<?= json_encode(url('/projetos/tarefas/externos-buscar')) ?> + '?q=' + encodeURIComponent(termo));
                const dados = await res.json();
                lista.innerHTML = '';

                (dados.participantes || []).forEach(p => {
                    const item = document.createElement('button');
                    item.type = 'button';
                    item.className = 'list-group-item list-group-item-action';
                    item.textContent = p.nome + (p.empresa ? ' -- ' + p.empresa : '');
                    item.onclick = async () => {
                        await fetch(<?= json_encode(url('/projetos/tarefas/externo-adicionar')) ?>, {
                            method: 'POST',
                            body: new URLSearchParams({ tarefa_id: campo.dataset.tarefaId, projeto_id: '<?= (int)$projeto['id'] ?>', participante_externo_id: p.id }),
                        });
                        location.reload();
                    };
                    lista.appendChild(item);
                });

                const novo = document.createElement('button');
                novo.type = 'button';
                novo.className = 'list-group-item list-group-item-action text-primary';
                novo.textContent = 'Nenhum resultado -- cadastrar novo participante';
                novo.onclick = () => { lista.classList.add('d-none'); formCadastro.classList.remove('d-none'); };
                lista.appendChild(novo);

                lista.classList.remove('d-none');
            }, 300);
        });
    });

    document.addEventListener('click', function (e) {
        document.querySelectorAll('.lista-responsaveis-sugeridos, .lista-externos-sugeridos').forEach(function (lista) {
            if (!lista.contains(e.target) && !lista.previousElementSibling?.contains(e.target) && e.target !== lista.previousElementSibling) {
                lista.classList.add('d-none');
            }
        });
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = $projeto['titulo'];

require __DIR__ . '/../layouts/main.php';
