<?php
ob_start();

use App\Components\Alert;
use App\Services\PermissionService;
use App\Services\ProjetoService;

$statusClasses = [
    'planejamento' => 'text-bg-light border',
    'em_andamento' => 'text-bg-primary',
    'pausado' => 'text-bg-warning',
    'concluido' => 'text-bg-success',
    'cancelado' => 'text-bg-secondary',
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
        <h4 class="mb-1"><i class="bi bi-kanban me-1"></i> Projetos</h4>
        <small class="text-muted">Assessorias e iniciativas, por área.</small>
    </div>
    <div class="d-flex gap-2">
        <?php if (PermissionService::temAcesso('projetos_estatisticas')): ?>
            <a href="<?= url('/projetos/estatisticas') ?>" class="btn btn-outline-secondary text-nowrap">
                <i class="bi bi-bar-chart-line"></i> Estatísticas
            </a>
        <?php endif; ?>
        <?php if (PermissionService::temAcesso('projetos_gerenciar')): ?>
            <a href="<?= url('/projetos/areas') ?>" class="btn btn-outline-secondary text-nowrap">
                <i class="bi bi-diagram-3"></i> Áreas
            </a>
        <?php endif; ?>
        <a href="<?= url('/projetos/novo') ?>" class="btn btn-primary text-nowrap">
            <i class="bi bi-plus-lg"></i> Novo projeto
        </a>
    </div>
</div>

<form method="get" class="card border-0 shadow-sm mb-3">
    <div class="card-body row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label small text-muted mb-1">Área</label>
            <select name="area_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Todas</option>
                <?php foreach ($areas as $a): ?>
                    <option value="<?= (int)$a['id'] ?>" <?= (int)($filtros['area_id'] ?? 0) === (int)$a['id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small text-muted mb-1">Status</label>
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Todos</option>
                <?php foreach (ProjetoService::statusLabelTodos() as $valor => $label): ?>
                    <option value="<?= $valor ?>" <?= ($filtros['status'] ?? '') === $valor ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <a href="<?= url('/projetos') ?>" class="btn btn-outline-secondary btn-sm">Limpar filtros</a>
        </div>
    </div>
</form>

<?php
    $totalNovasProjetos = array_sum(array_column($naoLidasPorProjeto, 'total'));
    $totalMencoesProjetos = array_sum(array_column($naoLidasPorProjeto, 'mencoes'));
    $nomesProjetos = array_column($projetos, 'titulo', 'id');
?>
<?php if ($totalNovasProjetos > 0): ?>
    <div class="alert <?= $totalMencoesProjetos ? 'alert-danger' : 'alert-warning' ?> d-flex align-items-start gap-2">
        <i class="bi <?= $totalMencoesProjetos ? 'bi-at' : 'bi-chat-dots-fill' ?> fs-4"></i>
        <div>
            <strong>
                <?= $totalMencoesProjetos ? ($totalMencoesProjetos === 1 ? 'Você foi mencionado em 1 mensagem' : "Você foi mencionado em {$totalMencoesProjetos} mensagens") . ' · ' : '' ?>
                <?= $totalNovasProjetos === 1 ? '1 mensagem nova' : "{$totalNovasProjetos} mensagens novas" ?> nas suas tarefas
            </strong>
            <div class="small">
                <?php foreach ($naoLidasPorProjeto as $projetoIdNovas => $novas): ?>
                    <?php if (!isset($nomesProjetos[$projetoIdNovas])) continue; ?>
                    <a href="<?= url('/projetos/ver?id=' . (int)$projetoIdNovas) ?>" class="me-3"><?= htmlspecialchars($nomesProjetos[$projetoIdNovas]) ?> (<?= $novas['total'] ?><?= $novas['mencoes'] ? ', @' . $novas['mencoes'] : '' ?>)</a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (empty($projetos)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-kanban" style="font-size:2rem;"></i>
            <p class="mb-0 mt-2">Nenhum projeto por aqui ainda.</p>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($projetos as $projeto): ?>
            <div class="col-md-6 col-lg-4">
                <a href="<?= url('/projetos/ver?id=' . (int)$projeto['id']) ?>" class="text-decoration-none text-reset">
                    <?php $novasProjeto = $naoLidasPorProjeto[(int)$projeto['id']] ?? null; ?>
                    <div class="card shadow-sm h-100 <?= $novasProjeto ? ($novasProjeto['mencoes'] ? 'border border-2 border-danger projeto-com-mencao' : 'border border-warning') : 'border-0' ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge text-bg-light border"><?= htmlspecialchars($projeto['area_nome']) ?></span>
                                <span class="badge <?= $prioridadeClasses[$projeto['prioridade']] ?? '' ?>"><?= ucfirst($projeto['prioridade']) ?></span>
                            </div>
                            <h6 class="mb-1"><?= htmlspecialchars($projeto['titulo']) ?></h6>
                            <?php if ($novasProjeto): ?>
                                <div class="small fw-semibold <?= $novasProjeto['mencoes'] ? 'text-danger' : 'text-warning-emphasis' ?> mb-1">
                                    <i class="bi <?= $novasProjeto['mencoes'] ? 'bi-at' : 'bi-chat-dots-fill' ?>"></i>
                                    <?= $novasProjeto['mencoes'] ? 'Você foi mencionado · ' : '' ?><?= $novasProjeto['total'] === 1 ? '1 mensagem nova' : $novasProjeto['total'] . ' mensagens novas' ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($projeto['cliente'])): ?>
                                <p class="text-muted small mb-2"><i class="bi bi-building"></i> <?= htmlspecialchars($projeto['cliente']) ?></p>
                            <?php endif; ?>
                            <span class="badge <?= $statusClasses[$projeto['status']] ?? '' ?>"><?= ProjetoService::statusLabel($projeto['status']) ?></span>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<style>
.projeto-com-mencao { animation: pulsoMencao 1.6s ease-in-out 3; }
@keyframes pulsoMencao { 50% { box-shadow: 0 0 0 .35rem rgba(220, 53, 69, .25) !important; } }
</style>

<?php
$conteudo = ob_get_clean();
$titulo = 'Projetos';

require __DIR__ . '/../layouts/main.php';
