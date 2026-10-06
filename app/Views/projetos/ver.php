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
.cor-swatches { display: flex; gap: 8px; flex-wrap: wrap; }
.cor-swatch { width: 26px; height: 26px; border-radius: 50%; cursor: pointer; border: 2px solid transparent; display: inline-block; }
.cor-swatch.selecionada { border-color: #212529; box-shadow: 0 0 0 2px #fff inset; }
.cor-swatch.cor-nenhuma { background: #fff; border: 2px dashed #ced4da; position: relative; }
.cor-swatch.cor-nenhuma.selecionada { border-color: #212529; border-style: solid; }
.cor-swatch.cor-livre { background: #fff; border: 2px dashed #adb5bd; display: inline-flex; align-items: center; justify-content: center; color: #6c757d; position: relative; overflow: hidden; }
.cor-swatch.cor-livre input[type="color"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.card-pintado .text-muted, .card-pintado .text-danger { color: inherit !important; opacity: .85; }
.card-pintado .badge.text-bg-light { background: rgba(255,255,255,.55) !important; }
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
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#painelLista" type="button">
                    <i class="bi bi-list-ul"></i> Lista
                </button>
            </li>
            <li class="nav-item ms-auto">
                <button type="button" class="btn btn-outline-secondary btn-sm mt-1 me-1" data-bs-toggle="modal" data-bs-target="#modalNovaColuna" title="Adicionar uma coluna ao quadro">
                    <i class="bi bi-layout-three-columns"></i> Coluna
                </button>
                <button type="button" class="btn btn-primary btn-sm mt-1" data-bs-toggle="modal" data-bs-target="#modalNovaTarefa">
                    <i class="bi bi-plus-lg"></i> Nova tarefa
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="painelQuadro">
                <div class="kanban-board d-flex gap-3" style="overflow-x:auto">
                    <?php foreach ($colunas as $indiceColuna => $colunaQuadro): $colunaChave = $colunaQuadro['id']; ?>
                        <div class="kanban-col flex-shrink-0" style="width:270px">
                            <div class="d-flex justify-content-between align-items-center mb-2 small text-uppercase text-muted fw-semibold">
                                <span class="text-truncate" title="Situação: <?= htmlspecialchars(\App\Services\ProjetoColunaService::SITUACOES[$colunaQuadro['situacao']]) ?>"><?= htmlspecialchars($colunaQuadro['nome']) ?></span>
                                <span class="d-flex align-items-center gap-1">
                                    <span class="badge text-bg-light border"><?= count($quadro[$colunaChave]) ?></span>
                                    <span class="dropdown">
                                        <button type="button" class="btn btn-sm btn-link text-muted p-0 px-1" data-bs-toggle="dropdown" title="Opções da coluna"><i class="bi bi-three-dots-vertical"></i></button>
                                        <ul class="dropdown-menu dropdown-menu-end small text-none" style="text-transform:none">
                                            <li><button type="button" class="dropdown-item btn-editar-coluna" data-id="<?= (int)$colunaChave ?>" data-nome="<?= htmlspecialchars($colunaQuadro['nome']) ?>" data-situacao="<?= $colunaQuadro['situacao'] ?>"><i class="bi bi-pencil"></i> Renomear / situação</button></li>
                                            <?php foreach ([-1 => ['bi-arrow-left', 'Mover para a esquerda', $indiceColuna > 0], 1 => ['bi-arrow-right', 'Mover para a direita', $indiceColuna < count($colunas) - 1]] as $direcao => [$iconeDirecao, $rotuloDirecao, $podeMover]): ?>
                                                <?php if ($podeMover): ?>
                                                <li>
                                                    <form method="post" action="<?= url('/projetos/colunas/mover') ?>">
                                                        <input type="hidden" name="id" value="<?= (int)$colunaChave ?>">
                                                        <input type="hidden" name="direcao" value="<?= $direcao ?>">
                                                        <button type="submit" class="dropdown-item"><i class="bi <?= $iconeDirecao ?>"></i> <?= $rotuloDirecao ?></button>
                                                    </form>
                                                </li>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            <?php if (count($colunas) > 1): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><button type="button" class="dropdown-item text-danger btn-excluir-coluna" data-id="<?= (int)$colunaChave ?>" data-nome="<?= htmlspecialchars($colunaQuadro['nome']) ?>" data-total="<?= count($quadro[$colunaChave]) ?>"><i class="bi bi-trash"></i> Excluir coluna</button></li>
                                            <?php endif; ?>
                                        </ul>
                                    </span>
                                </span>
                            </div>
                            <div class="kanban-lista d-flex flex-column gap-2 p-2 rounded" style="min-height:80px; background:#f4f6f9" data-coluna="<?= (int)$colunaChave ?>">
                                <?php foreach ($quadro[$colunaChave] as $tarefa):
                                    $atrasada = $tarefa['coluna'] !== 'concluido' && !empty($tarefa['prazo']) && strtotime($tarefa['prazo']) < strtotime(date('Y-m-d'));
                                    $corCartao = ProjetoTarefaService::corHex($tarefa['cor'] ?? null);
                                    $cartaoInteiro = $corCartao && ($tarefa['cor_estilo'] ?? 'lateral') === 'inteiro';
                                    $estiloCartao = $corCartao
                                        ? ($cartaoInteiro ? "; background:{$corCartao}; color:" . ProjetoTarefaService::corTexto($corCartao) . '; border:0' : "; border-left:4px solid {$corCartao}")
                                        : '';
                                ?>
                                    <div class="card shadow-sm kanban-card <?= $cartaoInteiro ? 'card-pintado' : '' ?>" data-id="<?= (int)$tarefa['id'] ?>"
                                        style="cursor:pointer<?= $estiloCartao ?>"
                                        data-bs-toggle="modal" data-bs-target="#modalTarefa<?= (int)$tarefa['id'] ?>">
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
                                                <span class="small text-muted d-flex align-items-center gap-2">
                                                    <?php
                                                        $mensagensCard = count(array_filter($timeline, fn ($c) => (int)($c['tarefa_id'] ?? 0) === (int)$tarefa['id'] && $c['tipo'] === 'nota'));
                                                        $novasCard = $naoLidasPorTarefa[(int)$tarefa['id']] ?? null;
                                                    ?>
                                                    <?php if ($novasCard): ?>
                                                        <span class="badge text-bg-danger badge-novas-tarefa <?= $novasCard['mencoes'] ? 'badge-mencao' : '' ?>" data-tarefa-id="<?= (int)$tarefa['id'] ?>"
                                                              title="<?= $novasCard['mencoes'] ? 'Você foi mencionado' : 'Mensagens novas para você' ?>">
                                                            <i class="bi <?= $novasCard['mencoes'] ? 'bi-at' : 'bi-chat-dots-fill' ?>"></i> <?= $novasCard['total'] ?>
                                                        </span>
                                                    <?php elseif ($mensagensCard): ?>
                                                        <span title="Mensagens na conversa"><i class="bi bi-chat-dots"></i> <?= $mensagensCard ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($tarefa['total_responsaveis'] || $tarefa['total_externos']): ?>
                                                        <span><i class="bi bi-people"></i> <?= (int)$tarefa['total_responsaveis'] + (int)$tarefa['total_externos'] ?></span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="flex-shrink-0" style="width:180px">
                        <button type="button" class="btn btn-outline-secondary w-100 mt-4" data-bs-toggle="modal" data-bs-target="#modalNovaColuna" style="border-style:dashed">
                            <i class="bi bi-plus-lg"></i> Coluna
                        </button>
                    </div>
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

            <div class="tab-pane fade" id="painelLista">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:6px"></th>
                                <th>Tarefa</th>
                                <th>Fase</th>
                                <th>Status</th>
                                <th>Prazo</th>
                                <th>Pessoas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $totalListado = 0; ?>
                            <?php foreach ($colunas as $colunaQuadro): $colunaLabel = htmlspecialchars($colunaQuadro['nome']); foreach ($quadro[$colunaQuadro['id']] as $tarefa): $totalListado++;
                                $atrasadaLista = $tarefa['coluna'] !== 'concluido' && !empty($tarefa['prazo']) && strtotime($tarefa['prazo']) < strtotime(date('Y-m-d'));
                                $corLista = ProjetoTarefaService::corHex($tarefa['cor'] ?? null);
                            ?>
                                <tr style="cursor:pointer" data-bs-toggle="modal" data-bs-target="#modalTarefa<?= (int)$tarefa['id'] ?>">
                                    <td><?php if ($corLista): ?><span class="d-inline-block rounded-circle" style="width:10px;height:10px;background:<?= $corLista ?>"></span><?php endif; ?></td>
                                    <td>
                                        <?= htmlspecialchars($tarefa['titulo']) ?>
                                        <?php if ($tarefa['tag']): ?><span class="badge text-bg-light border ms-1"><?= htmlspecialchars($tarefa['tag']) ?></span><?php endif; ?>
                                    </td>
                                    <td class="text-muted small"><?= htmlspecialchars($tarefa['fase_nome'] ?? '-') ?></td>
                                    <td><span class="badge text-bg-light border"><?= $colunaLabel ?></span></td>
                                    <td class="small <?= $atrasadaLista ? 'text-danger fw-semibold' : 'text-muted' ?>"><?= $tarefa['prazo'] ? date('d/m/Y', strtotime($tarefa['prazo'])) : '-' ?></td>
                                    <td class="small text-muted">
                                        <?php if ($tarefa['total_responsaveis'] || $tarefa['total_externos']): ?>
                                            <i class="bi bi-people"></i> <?= (int)$tarefa['total_responsaveis'] + (int)$tarefa['total_externos'] ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; endforeach; ?>
                            <?php if ($totalListado === 0): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma tarefa ainda.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h6 class="card-title mb-3"><i class="bi bi-clock-history"></i> Linha do tempo</h6>
        <?php
            // Quem pode ser marcado no campo da Linha do tempo: "" = nota geral (pessoas do projeto), ID = a tarefa escolhida.
            $semEu = fn (array $lista) => array_values(array_map(
                fn ($pp) => ['chave' => $pp['tipo'] . ':' . (int)$pp['id'], 'nome' => $pp['nome'], 'tipo' => $pp['tipo']],
                array_filter($lista, fn ($pp) => !($pp['tipo'] === 'interno' && (int)$pp['id'] === (int)$usuarioLogadoId))
            ));
            $mapaMarcaveis = ['' => $semEu($marcaveisProjeto)];
            $comentariosSvc = new \App\Services\ProjetoComentarioService();
            foreach ($quadro as $colunaTarefas) {
                foreach ($colunaTarefas as $t) {
                    $mapaMarcaveis[(string)$t['id']] = $semEu($comentariosSvc->marcaveisDaTarefa((int)$t['id']));
                }
            }
        ?>
        <ul class="list-unstyled mb-3">
            <?php if (empty($timeline)): ?>
                <li class="text-muted small">Nada por aqui ainda.</li>
            <?php endif; ?>
            <?php
                $nomesMarcaveis = [];
                foreach ($mapaMarcaveis as $listaNomes) {
                    foreach ($listaNomes as $pp) {
                        $nomesMarcaveis[$pp['nome']] = true;
                    }
                }
                $meuNome = (string)($_SESSION['usuario']['nome'] ?? '');
            ?>
            <?php foreach (array_reverse($timeline) as $item): ?>
                <?php $mencaoNova = in_array((int)$item['id'], $mencoesGeraisNovas, true); ?>
                <li class="mb-3 pb-3 border-bottom <?= $mencaoNova ? 'nota-mencao-nova' : '' ?>">
                    <?php if ($mencaoNova): ?><div class="small fw-semibold text-danger mb-1"><i class="bi bi-at"></i> Você foi mencionado</div><?php endif; ?>
                    <?php if ($item['tipo'] === 'sistema'): ?>
                        <div class="small text-muted">
                            <i class="bi bi-gear"></i> <?= htmlspecialchars($item['conteudo']) ?>
                            <?php if (!empty($item['usuario_nome']) || !empty($item['participante_nome'])): ?> · por <strong><?= htmlspecialchars($item['usuario_nome'] ?? $item['participante_nome']) ?></strong><?php endif; ?>
                            <?php if ($item['tarefa_titulo']): ?> · <span class="fst-italic"><?= htmlspecialchars($item['tarefa_titulo']) ?></span><?php endif; ?>
                            · <?= date('d/m/Y H:i', strtotime($item['criado_em'])) ?>
                        </div>
                    <?php else: ?>
                        <div>
                            <strong><?= htmlspecialchars($item['usuario_nome'] ?? $item['participante_nome'] ?? 'Alguém') ?></strong>
                            <?php if ($item['participante_nome']): ?><span class="badge text-bg-light border ms-1">externo</span><?php endif; ?>
                            <?php if ($item['tarefa_titulo']): ?><span class="text-muted small"> em "<?= htmlspecialchars($item['tarefa_titulo']) ?>"</span><?php endif; ?>
                            <span class="text-muted small">· <?= date('d/m/Y H:i', strtotime($item['criado_em'])) ?></span>
                            <?php
                                $textoNota = htmlspecialchars($item['conteudo']);
                                foreach (array_keys($nomesMarcaveis + ($meuNome !== '' ? [$meuNome => true] : [])) as $nomeMarcado) {
                                    $marca = '@' . htmlspecialchars($nomeMarcado);
                                    $textoNota = str_ireplace($marca, '<span class="mencao' . ($nomeMarcado === $meuNome ? ' mencao-eu' : '') . '">' . $marca . '</span>', $textoNota);
                                }
                            ?>
                            <p class="mb-0 mt-1" style="white-space:pre-wrap; word-break:break-word"><?= linkificar($textoNota) ?></p>
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

        <form method="post" action="<?= url('/projetos/comentar') ?>" enctype="multipart/form-data" id="formComentarProjeto" class="position-relative"
              data-marcaveis-mapa="<?= htmlspecialchars(json_encode($mapaMarcaveis, JSON_UNESCAPED_UNICODE)) ?>">
            <div class="list-group position-absolute shadow-sm d-none lista-mencoes" style="z-index:30; bottom:100%; min-width:260px; max-height:220px; overflow-y:auto"></div>
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
            <textarea name="conteudo" class="form-control mb-2" rows="2" placeholder="Escreva um comentário... Use @ para marcar alguém." required></textarea>
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
                            <label class="form-label">Cor do cartão <span class="text-muted fw-normal">(opcional)</span></label>
                            <?php $corAtual = ''; $estiloAtual = 'lateral'; require __DIR__ . '/_paleta_cor.php'; ?>
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

<?php $situacoesColuna = \App\Services\ProjetoColunaService::SITUACOES; ?>
<!-- Colunas do quadro: nova / editar / excluir -->
<div class="modal fade" id="modalNovaColuna" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" action="<?= url('/projetos/colunas/criar') ?>" class="modal-content">
            <input type="hidden" name="projeto_id" value="<?= (int)$projeto['id'] ?>">
            <div class="modal-header"><h5 class="modal-title">Nova coluna</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <label class="form-label">Nome</label>
                <input type="text" name="nome" class="form-control mb-3" maxlength="60" required placeholder="Ex: Instalação, Testes, Validação do cliente">
                <label class="form-label">Os cartões nessa coluna contam como</label>
                <select name="situacao" class="form-select">
                    <?php foreach ($situacoesColuna as $valor => $rotulo): ?>
                        <option value="<?= $valor ?>" <?= $valor === 'em_andamento' ? 'selected' : '' ?>><?= $rotulo ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">É o que vale para estatísticas, "Atrasadas", "Aguardando terceiro" e o progresso das fases. Ela entra no fim do quadro; use o menu ⋮ para mudar de lugar.</div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Adicionar coluna</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalEditarColuna" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" action="<?= url('/projetos/colunas/atualizar') ?>" class="modal-content">
            <input type="hidden" name="id" id="editarColunaId">
            <div class="modal-header"><h5 class="modal-title">Coluna</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <label class="form-label">Nome</label>
                <input type="text" name="nome" id="editarColunaNome" class="form-control mb-3" maxlength="60" required>
                <label class="form-label">Os cartões nessa coluna contam como</label>
                <select name="situacao" id="editarColunaSituacao" class="form-select">
                    <?php foreach ($situacoesColuna as $valor => $rotulo): ?>
                        <option value="<?= $valor ?>"><?= $rotulo ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Mudar a situação vale também para os cartões que já estão na coluna.</div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Salvar</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalExcluirColuna" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" action="<?= url('/projetos/colunas/excluir') ?>" class="modal-content">
            <input type="hidden" name="id" id="excluirColunaId">
            <div class="modal-header"><h5 class="modal-title">Excluir a coluna "<span id="excluirColunaNome"></span>"</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="mb-2">Os <strong id="excluirColunaTotal">0</strong> cartão(ões) dela vão para:</p>
                <select name="destino_id" id="excluirColunaDestino" class="form-select" required>
                    <?php foreach ($colunas as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-danger">Excluir coluna</button></div>
        </form>
    </div>
</div>

<?php foreach ($quadro as $colunaTarefas): foreach ($colunaTarefas as $tarefa):
    $pessoas = $tarefaService->pessoas((int)$tarefa['id']);
    $marcaveisTarefa = (new \App\Services\ProjetoComentarioService())->marcaveisDaTarefa((int)$tarefa['id']);
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
                        <div class="col-12">
                            <?php $corAtual = (string)($tarefa['cor'] ?? ''); $estiloAtual = (string)($tarefa['cor_estilo'] ?? 'lateral'); require __DIR__ . '/_paleta_cor.php'; ?>
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

                <h6 class="small text-uppercase text-muted">Conversa</h6>
                <div class="conversa-tarefa mb-2" data-tarefa-id="<?= (int)$tarefa['id'] ?>">
                    <?php if (empty($comentariosTarefa)): ?>
                        <div class="text-muted small text-center py-2">Nenhuma mensagem ainda. Escreva abaixo para conversar com quem está na tarefa.</div>
                    <?php endif; ?>
                    <?php foreach ($comentariosTarefa as $c): ?>
                        <?php if ($c['tipo'] === 'sistema'): ?>
                            <div class="conversa-evento"><i class="bi bi-gear"></i> <?= htmlspecialchars($c['conteudo']) ?><?php if (!empty($c['usuario_nome']) || !empty($c['participante_nome'])): ?> · por <?= htmlspecialchars($c['usuario_nome'] ?? $c['participante_nome']) ?><?php endif; ?> · <?= date('d/m H:i', strtotime($c['criado_em'])) ?></div>
                        <?php else: ?>
                            <?php $minha = (int)($c['usuario_id'] ?? 0) === (int)$usuarioLogadoId; ?>
                            <div class="conversa-msg <?= $minha ? 'minha' : '' ?>">
                                <div class="conversa-autor">
                                    <?= $minha ? 'Você' : htmlspecialchars($c['usuario_nome'] ?? $c['participante_nome'] ?? 'Alguém') ?>
                                    <?php if (!empty($c['participante_externo_id'])): ?><span class="badge text-bg-light border">externo</span><?php endif; ?>
                                    <span class="text-muted">· <?= date('d/m/Y H:i', strtotime($c['criado_em'])) ?></span>
                                </div>
                                <?php
                                    $textoMsg = htmlspecialchars($c['conteudo']);
                                    foreach (array_merge($pessoas, $marcaveisTarefa) as $pp) {
                                        $ehEu = $pp['tipo'] === 'interno' && (int)$pp['id'] === (int)$usuarioLogadoId;
                                        $marca = '@' . htmlspecialchars($pp['nome']);
                                        $textoMsg = str_ireplace($marca, '<span class="mencao' . ($ehEu ? ' mencao-eu' : '') . '">' . $marca . '</span>', $textoMsg);
                                    }
                                ?>
                                <div class="conversa-texto"><?= linkificar($textoMsg) ?></div>
                                <?php foreach ($anexosTarefa as $a): ?>
                                    <?php if ((int)($a['comentario_id'] ?? 0) === (int)$c['id']): ?>
                                        <a class="small d-block" href="<?= url('/projetos/anexo?anexo_id=' . (int)$a['id']) ?>"><i class="bi bi-paperclip"></i> <?= htmlspecialchars($a['anexo_nome_original']) ?></a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php
                    $marcaveis = array_values(array_map(
                        fn ($pp) => ['chave' => $pp['tipo'] . ':' . (int)$pp['id'], 'nome' => $pp['nome'], 'tipo' => $pp['tipo']],
                        array_filter($marcaveisTarefa, fn ($pp) => !($pp['tipo'] === 'interno' && (int)$pp['id'] === (int)$usuarioLogadoId))
                    ));
                ?>
                <form method="post" action="<?= url('/projetos/comentar') ?>" enctype="multipart/form-data" class="mb-3 form-conversa-tarefa position-relative"
                      data-marcaveis="<?= htmlspecialchars(json_encode($marcaveis, JSON_UNESCAPED_UNICODE)) ?>">
                    <div class="list-group position-absolute shadow-sm d-none lista-mencoes" style="z-index:30; bottom:100%; min-width:260px; max-height:200px; overflow-y:auto"></div>
                    <input type="hidden" name="projeto_id" value="<?= (int)$projeto['id'] ?>">
                    <input type="hidden" name="tarefa_id" value="<?= (int)$tarefa['id'] ?>">
                    <input type="hidden" name="voltar_tarefa" value="1">
                    <textarea name="conteudo" class="form-control form-control-sm mb-1" rows="2" required
                              placeholder="<?= $marcaveis ? 'Escreva para ' . htmlspecialchars(implode(', ', array_column($marcaveis, 'nome'))) . '... Use @ para marcar alguém (Ctrl+Enter envia)' : 'Escreva uma mensagem... (Ctrl+Enter envia)' ?>"></textarea>
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <label class="btn btn-outline-secondary btn-sm mb-0">
                            <i class="bi bi-paperclip"></i> Anexar
                            <input type="file" name="arquivo" class="d-none campo-anexo-conversa">
                        </label>
                        <span class="small text-muted me-auto nome-anexo-conversa"></span>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-send"></i> Enviar</button>
                    </div>
                </form>

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

<style>
.conversa-tarefa { max-height: 340px; overflow-y: auto; background: #f6f8fa; border-radius: 8px; padding: 8px; }
.conversa-evento { font-size: 11px; color: #8a929a; text-align: center; margin: 4px 0; }
.conversa-msg { background: #fff; border: 1px solid #e3e7eb; border-radius: 10px; padding: 6px 10px; margin: 6px 0; max-width: 85%; font-size: .875rem; }
.conversa-msg.minha { background: #e7f1ff; border-color: #b6d4fe; margin-left: auto; }
.conversa-autor { font-size: 11px; font-weight: 600; margin-bottom: 2px; }
.conversa-texto { white-space: pre-wrap; word-break: break-word; }
.mencao { color: #0a58ca; font-weight: 600; }
.campo-mencoes { position: relative; }
.campo-mencoes-espelho { position: absolute; inset: 0; color: transparent; white-space: pre-wrap; word-wrap: break-word; overflow: hidden; pointer-events: none; }
.campo-mencoes textarea { position: relative; z-index: 1; background: transparent !important; }
mark.mencao-campo { background: #cfe2ff; color: transparent; border-radius: 3px; padding: 0; box-shadow: 0 0 0 1px #9ec5fe; }
.nota-mencao-nova { background: #fff8e1; border-left: 4px solid #dc3545 !important; padding-left: .75rem; border-radius: 6px; }
.mencao-eu { background: #fff3cd; color: #842029; border-radius: 4px; padding: 0 2px; }
.badge-mencao { animation: pulsoMencaoCard 1.4s ease-in-out infinite; }
@keyframes pulsoMencaoCard { 50% { box-shadow: 0 0 0 .3rem rgba(220, 53, 69, .3); } }
</style>

<script>
(function () {
    const URL_MARCAR_LIDO = <?= json_encode(url('/projetos/tarefas/marcar-lido')) ?>;

    document.querySelectorAll('[id^="modalTarefa"]').forEach(function (modal) {
        modal.addEventListener('shown.bs.modal', function () {
            const conversa = modal.querySelector('.conversa-tarefa');
            if (!conversa) return;
            conversa.scrollTop = conversa.scrollHeight;

            const tarefaId = conversa.dataset.tarefaId;
            const badge = document.querySelector('.badge-novas-tarefa[data-tarefa-id="' + tarefaId + '"]');
            if (!badge) return;
            fetch(URL_MARCAR_LIDO, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ tarefa_id: tarefaId }),
            }).then((r) => r.json()).then(function (dados) {
                if (!dados.success) return;
                badge.remove();
                if (typeof window.rdAtualizarAvisoProjetos === 'function') {
                    window.rdAtualizarAvisoProjetos(dados.total_nao_lidas, dados.mencoes);
                }
            }).catch(function () {});
        });
    });

    /**
     * @menção num campo de texto: "@" abre a lista de quem pode ser marcado
     * (setas/Enter/Tab/Esc ou clique); no envio manda mencoes[] só de quem
     * continua com "@Nome" no texto.
     */
    function ativarMencoes(form, texto, lista, obterMarcaveis) {
        const escolhidas = new Map(); // chave -> nome
        let opcoes = [];
        let ativa = 0;

        // Espelho atrás do campo: textarea não colore parte do texto, então uma
        // camada com o mesmo texto (invisível) pinta o fundo de cada @Nome marcado.
        const envoltorio = document.createElement('div');
        envoltorio.className = 'campo-mencoes';
        ['mb-1', 'mb-2'].forEach(function (classe) {
            if (texto.classList.contains(classe)) { texto.classList.remove(classe); envoltorio.classList.add(classe); }
        });
        texto.parentNode.insertBefore(envoltorio, texto);
        const espelho = document.createElement('div');
        espelho.className = 'form-control campo-mencoes-espelho' + (texto.classList.contains('form-control-sm') ? ' form-control-sm' : '');
        espelho.setAttribute('aria-hidden', 'true');
        envoltorio.appendChild(espelho);
        envoltorio.appendChild(texto);
        const aviso = document.createElement('div');
        aviso.className = 'small text-primary mb-1 d-none';
        envoltorio.after(aviso);

        function escaparHtml(t) {
            return t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function pintar() {
            let html = escaparHtml(texto.value);
            const marcados = [];
            escolhidas.forEach(function (nome) {
                const marca = escaparHtml('@' + nome);
                if (!html.toLowerCase().includes(marca.toLowerCase())) return;
                marcados.push(nome);
                html = html.split(marca).join('<mark class="mencao-campo">' + marca + '</mark>');
            });
            espelho.innerHTML = html + '\n ';
            espelho.scrollTop = texto.scrollTop;
            aviso.innerHTML = marcados.length ? '<i class="bi bi-bell"></i> Será avisado: <strong>' + escaparHtml(marcados.join(', ')) + '</strong>' : '';
            aviso.classList.toggle('d-none', marcados.length === 0);
        }
        texto.addEventListener('input', pintar);
        texto.addEventListener('scroll', function () { espelho.scrollTop = texto.scrollTop; });

        function termoAtual() {
            const antes = texto.value.slice(0, texto.selectionStart);
            const m = antes.match(/(?:^|\s)@([^@\n]{0,30})$/);
            return m ? m[1] : null;
        }

        function fecharLista() { lista.classList.add('d-none'); opcoes = []; }

        function desenharLista() {
            lista.innerHTML = opcoes.map((p, i) =>
                '<button type="button" class="list-group-item list-group-item-action py-1 small' + (i === ativa ? ' active' : '') + '" data-i="' + i + '">'
                + '<i class="bi ' + (p.tipo === 'interno' ? 'bi-person' : 'bi-person-badge') + '"></i> ' + p.nome.replace(/</g, '&lt;')
                + (p.tipo === 'externo' ? ' <span class="badge text-bg-light border">externo</span>' : '') + '</button>'
            ).join('');
            lista.classList.toggle('d-none', opcoes.length === 0);
        }

        function escolher(p) {
            const pos = texto.selectionStart;
            const antes = texto.value.slice(0, pos).replace(/@([^@\n]{0,30})$/, '@' + p.nome + ' ');
            texto.value = antes + texto.value.slice(pos);
            texto.selectionStart = texto.selectionEnd = antes.length;
            escolhidas.set(p.chave, p.nome);
            fecharLista();
            pintar();
            texto.focus();
        }

        texto.addEventListener('input', function () {
            const termo = termoAtual();
            const marcaveis = obterMarcaveis();
            if (termo === null || !marcaveis.length) return fecharLista();
            const t = termo.toLowerCase();
            opcoes = marcaveis.filter((p) => p.nome.toLowerCase().includes(t)).slice(0, 8);
            ativa = 0;
            desenharLista();
        });
        texto.addEventListener('keydown', function (ev) {
            if (lista.classList.contains('d-none')) return;
            if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
                ev.preventDefault();
                ativa = (ativa + (ev.key === 'ArrowDown' ? 1 : opcoes.length - 1)) % opcoes.length;
                desenharLista();
            } else if (ev.key === 'Enter' || ev.key === 'Tab') {
                ev.preventDefault();
                ev.stopImmediatePropagation();
                escolher(opcoes[ativa]);
            } else if (ev.key === 'Escape') {
                ev.stopPropagation();
                fecharLista();
            }
        });
        lista.addEventListener('mousedown', function (ev) {
            const item = ev.target.closest('[data-i]');
            if (!item) return;
            ev.preventDefault();
            escolher(opcoes[parseInt(item.dataset.i, 10)]);
        });
        texto.addEventListener('blur', function () { setTimeout(fecharLista, 150); });

        form.addEventListener('submit', function () {
            form.querySelectorAll('input[name="mencoes[]"]').forEach((i) => i.remove());
            escolhidas.forEach(function (nome, chave) {
                if (!texto.value.toLowerCase().includes('@' + nome.toLowerCase())) return;
                const campo = document.createElement('input');
                campo.type = 'hidden';
                campo.name = 'mencoes[]';
                campo.value = chave;
                form.appendChild(campo);
            });
            const botao = form.querySelector('button[type="submit"]');
            if (botao) botao.disabled = true;
        });
    }

    // Ctrl+Enter envia; nome do anexo escolhido aparece ao lado do botão.
    document.querySelectorAll('.form-conversa-tarefa').forEach(function (form) {
        const texto = form.querySelector('textarea');
        texto.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' && (ev.ctrlKey || ev.metaKey) && texto.value.trim() !== '') {
                ev.preventDefault();
                form.requestSubmit();
            }
        });
        form.querySelector('.campo-anexo-conversa').addEventListener('change', function () {
            form.querySelector('.nome-anexo-conversa').textContent = this.files[0] ? this.files[0].name : '';
        });
        ativarMencoes(form, texto, form.querySelector('.lista-mencoes'), () => JSON.parse(form.dataset.marcaveis || '[]'));
    });

    // Nota da Linha do tempo: quem pode ser marcado depende da tarefa escolhida (ou do projeto todo).
    const formGeral = document.getElementById('formComentarProjeto');
    if (formGeral) {
        const mapa = JSON.parse(formGeral.dataset.marcaveisMapa || '{}');
        const seletor = formGeral.querySelector('select[name="tarefa_id"]');
        ativarMencoes(formGeral, formGeral.querySelector('textarea'), formGeral.querySelector('.lista-mencoes'), () => mapa[seletor.value] || []);
    }

    // Link de e-mail/aviso e volta depois de enviar: ?tarefa=ID abre a janela da tarefa.
    // O bootstrap.bundle carrega depois do conteúdo (layout) -- espera a página terminar.
    document.addEventListener('DOMContentLoaded', function () {
        const tarefaUrl = new URLSearchParams(location.search).get('tarefa');
        const modalUrl = tarefaUrl ? document.getElementById('modalTarefa' + parseInt(tarefaUrl, 10)) : null;
        if (!modalUrl) return;
        bootstrap.Modal.getOrCreateInstance(modalUrl).show();
        modalUrl.addEventListener('hidden.bs.modal', function () {
            const url = new URL(location.href);
            url.searchParams.delete('tarefa');
            history.replaceState(null, '', url);
        }, { once: true });
    });
})();
</script>

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

    // --- Cor do cartão: clicar num swatch marca ele e guarda no campo escondido ---
    document.querySelectorAll('.paleta-cor').forEach(function (paleta) {
        const campo = paleta.querySelector('.campo-cor-tarefa');
        const grupo = paleta.querySelector('.cor-swatches');
        const livre = paleta.querySelector('.cor-livre');

        function marcar(swatch) {
            grupo.querySelectorAll('.cor-swatch').forEach(function (s) { s.classList.remove('selecionada'); });
            swatch.classList.add('selecionada');
        }
        grupo.addEventListener('click', function (ev) {
            const swatch = ev.target.closest('.cor-swatch[data-cor]');
            if (!swatch) return;
            marcar(swatch);
            campo.value = swatch.dataset.cor;
        });
        // "+": escolhe qualquer cor; vira uma bolinha nova antes do "+".
        paleta.querySelector('.seletor-cor-livre').addEventListener('input', function () {
            const hex = this.value.toLowerCase();
            let swatch = grupo.querySelector('.cor-swatch[data-cor="' + hex + '"]');
            if (!swatch) {
                swatch = document.createElement('span');
                swatch.className = 'cor-swatch';
                swatch.dataset.cor = hex;
                swatch.title = 'Cor personalizada ' + hex;
                swatch.style.background = hex;
                grupo.insertBefore(swatch, livre);
            }
            marcar(swatch);
            campo.value = hex;
        });
    });

    // --- Colunas do quadro: editar e excluir abrem as janelas já preenchidas ---
    document.querySelectorAll('.btn-editar-coluna').forEach(function (botao) {
        botao.addEventListener('click', function () {
            document.getElementById('editarColunaId').value = botao.dataset.id;
            document.getElementById('editarColunaNome').value = botao.dataset.nome;
            document.getElementById('editarColunaSituacao').value = botao.dataset.situacao;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditarColuna')).show();
        });
    });
    document.querySelectorAll('.btn-excluir-coluna').forEach(function (botao) {
        botao.addEventListener('click', function () {
            document.getElementById('excluirColunaId').value = botao.dataset.id;
            document.getElementById('excluirColunaNome').textContent = botao.dataset.nome;
            document.getElementById('excluirColunaTotal').textContent = botao.dataset.total;
            const destino = document.getElementById('excluirColunaDestino');
            Array.from(destino.options).forEach(function (op) {
                op.disabled = op.value === botao.dataset.id;
                op.hidden = op.value === botao.dataset.id;
            });
            destino.value = Array.from(destino.options).find((op) => !op.disabled)?.value || '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalExcluirColuna')).show();
        });
    });

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
