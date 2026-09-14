<?php
ob_start();

use App\Components\Alert;
use App\Services\ChamadoSlaService;
?>

<?= Alert::flash() ?>

<div class="mb-4 d-flex justify-content-between align-items-start">
    <div>
        <h4 class="mb-1"><i class="bi bi-tags me-1"></i> Chamados - Categorias</h4>
        <small class="text-muted">Categoria define o setor padrão de roteamento e o prazo de SLA por prioridade. Categoria nova já nasce com um SLA padrão -- ajuste como preferir.</small>
    </div>
    <button type="button" class="btn btn-outline-dark text-nowrap" data-bs-toggle="modal" data-bs-target="#modalPopCategorias">
        <i class="bi bi-broadcast"></i> POP - Categorias
    </button>
</div>

<!-- POP -- Procedimento Operacional Padrão de gestão de categorias -->
<div class="modal fade pop-modal" id="modalPopCategorias" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="pop-topbar">
                <span class="pop-breadcrumb"><i class="bi bi-broadcast"></i> POP -- Categorias de Chamado</span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="pop-body">
                <p class="pop-intro">Categoria é escolhida na abertura de todo chamado -- ela decide pra onde ele vai e em quanto tempo precisa ser tratado. Veja o que cada campo faz:</p>

                <div class="pop-step">
                    <div class="pop-step-num">1</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-plus-lg"></i> Adicionar categoria</div>
                        <div class="pop-step-text">Formulário no topo -- <strong>Nome</strong> (obrigatório) e <strong>Setor responsável padrão</strong>. Categoria nova já nasce com uma linha de SLA padrão pra cada prioridade, prontas pra ajustar.</div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">2</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-diagram-3"></i> Setor responsável padrão</div>
                        <div class="pop-step-text">Quando alguém abre um chamado nessa categoria <strong>sem escolher um setor manualmente</strong>, é pra este setor que ele vai. Gerencie os setores em "Chamados &gt; Setores".</div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">3</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-toggle-on"></i> Categoria ativa</div>
                        <div class="pop-step-text">Desmarcada, a categoria some da lista de opções ao abrir um chamado novo -- mas <strong>chamados já existentes</strong> nela continuam intactos. Use pra aposentar uma categoria sem apagar o histórico.</div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">4</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-stopwatch"></i> SLA por prioridade</div>
                        <div class="pop-step-text">Clique no cabeçalho de uma categoria pra abrir o painel -- do lado direito, um prazo (em minutos) por prioridade (Baixa/Média/Alta/Urgente), com duas colunas: <strong>1ª resposta</strong> (tempo até alguém dar o primeiro retorno) e <strong>Resolução</strong> (tempo até o chamado ser resolvido). É esse prazo que o chamado usa pra calcular se está no prazo ou atrasado.</div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">5</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-trash"></i> Excluir categoria</div>
                        <div class="pop-step-text">Dentro do painel expandido -- <strong>prefira desativar</strong> em vez de excluir quando já existirem chamados nela, pra não perder a referência no histórico.</div>
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
