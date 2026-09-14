<?php
ob_start();

use App\Components\Alert;
use App\Services\ChamadoExternoService;
use App\Services\PermissionService;

$statusClasses = [
    'aberto' => 'text-bg-primary',
    'aguardando_fornecedor' => 'text-bg-warning',
    'em_andamento' => 'text-bg-info',
    'resolvido' => 'text-bg-success',
    'fechado' => 'text-bg-secondary',
];
$prioridadeClasses = [
    'baixa' => 'text-bg-light border',
    'media' => 'text-bg-light border',
    'alta' => 'text-bg-warning',
    'urgente' => 'text-bg-danger',
];
?>

<?= Alert::flash() ?>

<div class="mb-4 d-flex justify-content-between align-items-start">
    <div>
        <h4 class="mb-1"><i class="bi bi-building-gear me-1"></i> Chamados Externos</h4>
        <small class="text-muted">Chamados abertos com fornecedores pra resolver problemas internos.</small>
    </div>
    <div class="d-flex gap-2">
        <?php if (PermissionService::temAcesso('chamados_externos_estatisticas')): ?>
            <a href="<?= url('/chamados-externos/estatisticas') ?>" class="btn btn-outline-secondary text-nowrap">
                <i class="bi bi-bar-chart-line"></i> Estatísticas
            </a>
        <?php endif; ?>
        <?php if (PermissionService::temAcesso('chamados_externos_categorias')): ?>
            <a href="<?= url('/chamados-externos/categorias') ?>" class="btn btn-outline-secondary text-nowrap">
                <i class="bi bi-tags"></i> Categorias
            </a>
        <?php endif; ?>
        <button type="button" class="btn btn-outline-dark text-nowrap" data-bs-toggle="modal" data-bs-target="#modalPopChamadosExternos">
            <i class="bi bi-broadcast"></i> POP - Chamados Externos
        </button>
        <a href="<?= url('/chamados-externos/novo') ?>" class="btn btn-primary text-nowrap">
            <i class="bi bi-plus-lg"></i> Novo chamado
        </a>
    </div>
</div>

<!-- POP -- Procedimento Operacional Padrão de abertura de chamado externo -->
<div class="modal fade pop-modal" id="modalPopChamadosExternos" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="pop-topbar">
                <span class="pop-breadcrumb"><i class="bi bi-broadcast"></i> POP -- Abertura de Chamado Externo</span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="pop-body">
                <p class="pop-intro">
                    Chamado externo é diferente do chamado normal: aqui é <strong>você</strong> (o time interno)
                    quem abre um chamado <strong>contra um fornecedor</strong> pra resolver algo -- ex: link de
                    internet instável, garantia de equipamento, suporte de um sistema terceirizado. Passo a passo
                    do formulário "Novo chamado":
                </p>

                <div class="pop-step">
                    <div class="pop-step-num">1</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-card-text"></i> Título</div>
                        <div class="pop-step-text">Resuma o problema numa linha -- ex: "Instabilidade no link de internet".</div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">2</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-building"></i> Fornecedor</div>
                        <div class="pop-step-text">Obrigatório -- quem vai resolver o problema. Não achou na lista? Tem um atalho pra cadastrar um fornecedor novo sem sair da tela.</div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">3</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-tags"></i> Categoria <span class="text-muted small fw-normal">(opcional)</span></div>
                        <div class="pop-step-text">Classificação própria dos chamados externos -- gerenciada em "Categorias" nesta mesma tela, separada das categorias dos chamados internos.</div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">4</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-flag"></i> Prioridade</div>
                        <div class="pop-step-text">Baixa, Média, Alta ou Urgente -- ajuda a priorizar visualmente na lista, não tem SLA automático como o chamado interno.</div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">5</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-hash"></i> Protocolo do fornecedor <span class="text-muted small fw-normal">(opcional)</span></div>
                        <div class="pop-step-text">Se o fornecedor já te passou um número de protocolo/chamado do lado dele, registre aqui -- facilita muito na hora de cobrar retorno.</div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">6</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-hdd-network"></i> Ativo relacionado <span class="text-muted small fw-normal">(opcional)</span></div>
                        <div class="pop-step-text">Busque por patrimônio ou nome se o problema for sobre um equipamento específico já cadastrado.</div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">7</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-file-text"></i> Descrição</div>
                        <div class="pop-step-text">O que está acontecendo, com o máximo de detalhe -- é o que você vai repassar (ou copiar) pro fornecedor no primeiro contato.</div>
                    </div>
                </div>

                <div class="pop-step">
                    <div class="pop-step-num">8</div>
                    <div>
                        <div class="pop-step-title"><i class="bi bi-check-lg"></i> Abrir chamado</div>
                        <div class="pop-step-text">Registra o chamado -- depois, na ficha dele, dá pra comentar o andamento, anexar arquivo (inclusive de compartilhamento de rede/Samba) e mudar o status conforme o fornecedor responde.</div>
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

<form method="get" class="card border-0 shadow-sm mb-3">
    <div class="card-body row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Status</label>
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Todos</option>
                <?php foreach (ChamadoExternoService::statusLabelTodos() as $valor => $label): ?>
                    <option value="<?= $valor ?>" <?= ($filtros['status'] ?? '') === $valor ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Fornecedor</label>
            <select name="fornecedor_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Todos</option>
                <?php foreach ($fornecedores as $f): ?>
                    <option value="<?= (int)$f['id'] ?>" <?= (int)($filtros['fornecedor_id'] ?? 0) === (int)$f['id'] ? 'selected' : '' ?>><?= htmlspecialchars($f['nome_fantasia']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Categoria</label>
            <select name="categoria_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Todas</option>
                <?php foreach ($categorias as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= (int)($filtros['categoria_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <a href="<?= url('/chamados-externos') ?>" class="btn btn-outline-secondary btn-sm">Limpar filtros</a>
        </div>
    </div>
</form>

<?php if (empty($chamados)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-building-gear" style="font-size:2rem;"></i>
            <p class="mb-0 mt-2">Nenhum chamado externo encontrado.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nº</th>
                        <th>Título</th>
                        <th>Fornecedor</th>
                        <th>Categoria</th>
                        <th>Prioridade</th>
                        <th>Status</th>
                        <th>Aberto em</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($chamados as $chamado): ?>
                        <tr style="cursor:pointer" onclick="location.href='<?= url('/chamados-externos/ver?id=' . (int)$chamado['id']) ?>'">
                            <td class="font-monospace text-muted small"><?= htmlspecialchars($chamado['numero_controle'] ?? $chamado['id']) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($chamado['titulo']) ?></strong>
                                <?php if (!empty($chamado['ativo_patrimonio'])): ?>
                                    <span class="badge text-bg-light border ms-1"><i class="bi bi-cpu"></i> <?= htmlspecialchars($chamado['ativo_patrimonio']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($chamado['fornecedor_nome']) ?></td>
                            <td><?= htmlspecialchars($chamado['categoria_nome'] ?? '-') ?></td>
                            <td><span class="badge <?= $prioridadeClasses[$chamado['prioridade']] ?? '' ?>"><?= ucfirst($chamado['prioridade']) ?></span></td>
                            <td><span class="badge <?= $statusClasses[$chamado['status']] ?? '' ?>"><?= ChamadoExternoService::statusLabel($chamado['status']) ?></span></td>
                            <td class="text-muted small"><?= date('d/m/Y', strtotime($chamado['aberto_em'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Chamados Externos';

require __DIR__ . '/../layouts/main.php';
