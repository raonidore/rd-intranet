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
        <a href="<?= url('/chamados-externos/novo') ?>" class="btn btn-primary text-nowrap">
            <i class="bi bi-plus-lg"></i> Novo chamado
        </a>
    </div>
</div>

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
