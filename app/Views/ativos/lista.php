<?php
ob_start();

use App\Components\Alert;
use App\Components\Badge;
use App\Services\AtivoService;

$statusCores = [
    'ativo' => 'success',
    'manutencao' => 'warning',
    'estoque' => 'secondary',
    'baixado' => 'danger',
];
?>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-list-ul me-1"></i> Ativos - Lista</h4>
        <small class="text-muted"><a href="<?= url('/ativos') ?>"><i class="bi bi-arrow-left"></i> Dashboard</a></small>
    </div>
    <a href="<?= url('/ativos/novo') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Novo Ativo</a>
</div>

<form method="get" action="<?= url('/ativos/lista') ?>" class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Tipo</label>
                <select name="tipo" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach (AtivoService::TIPOS as $chave => $info): ?>
                        <option value="<?= $chave ?>" <?= $filtros['tipo'] === $chave ? 'selected' : '' ?>><?= htmlspecialchars($info['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach (AtivoService::STATUS as $chave => $label): ?>
                        <option value="<?= $chave ?>" <?= $filtros['status'] === $chave ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Busca</label>
                <input type="text" name="busca" class="form-control" value="<?= htmlspecialchars($filtros['busca']) ?>" placeholder="Nome, código ou nº de série">
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Filtrar</button>
            </div>
        </div>
    </div>
</form>

<form id="formLote" method="get" action="<?= url('/ativos/etiquetas/lote') ?>">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($ativos)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-boxes display-6"></i>
                    <p class="mt-2 mb-0">Nenhum ativo encontrado com esses filtros.</p>
                </div>
            <?php else: ?>
                <div class="p-2 border-bottom d-flex justify-content-between align-items-center">
                    <div class="form-check ms-2">
                        <input class="form-check-input" type="checkbox" id="marcarTodos">
                        <label class="form-check-label small text-muted" for="marcarTodos">Selecionar todos</label>
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-secondary" id="botaoEtiquetasLote" disabled>
                        <i class="bi bi-qr-code"></i> Imprimir etiquetas selecionadas
                    </button>
                </div>
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:32px"></th>
                            <th>Código</th>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Status</th>
                            <th>Condição</th>
                            <th>Setor</th>
                            <th>Localização</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ativos as $a): ?>
                            <tr>
                                <td><input type="checkbox" class="form-check-input checkbox-ativo" name="ids[]" value="<?= (int)$a['id'] ?>"></td>
                                <td class="font-monospace small"><?= htmlspecialchars($a['codigo_patrimonio']) ?></td>
                                <td><?= htmlspecialchars($a['nome']) ?></td>
                                <td><i class="bi <?= AtivoService::TIPOS[$a['tipo']]['icone'] ?>"></i> <?= htmlspecialchars(AtivoService::TIPOS[$a['tipo']]['label']) ?></td>
                                <td><?= Badge::make(htmlspecialchars(AtivoService::STATUS[$a['status']] ?? $a['status']), $statusCores[$a['status']] ?? 'secondary') ?></td>
                                <td>
                                    <?php if ($a['origem'] === 'agente'): ?>
                                        <?php $minutosAtras = AtivoService::minutosDesdeUltimoCheckin($a); ?>
                                        <span data-bs-toggle="tooltip" title="<?= $minutosAtras !== null ? 'Visto há ' . $minutosAtras . ' min (não é ao vivo)' : 'Nunca se comunicou' ?>">
                                            <?= Badge::make(AtivoService::estaLigada($a) ? 'Ligado' : 'Desligado', AtivoService::estaLigada($a) ? 'success' : 'secondary') ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= htmlspecialchars($a['setor_nome'] ?? '—') ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($a['localizacao_nome'] ?? '—') ?></td>
                                <td class="text-end">
                                    <div class="btn-group" role="group">
                                        <a href="<?= url('/ativos/ver?id=' . $a['id']) ?>" class="btn btn-sm btn-outline-secondary" title="Ver"><i class="bi bi-eye"></i></a>
                                        <a href="<?= url('/ativos/editar?id=' . $a['id']) ?>" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                                        <a href="<?= url('/ativos/etiqueta?id=' . $a['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Etiqueta"><i class="bi bi-qr-code"></i></a>
                                        <a href="<?= url('/ativos/excluir?id=' . $a['id']) ?>" class="btn btn-sm btn-outline-danger" title="Excluir"><i class="bi bi-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</form>

<script>
(function () {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });
})();

(function () {
    const marcarTodos = document.getElementById('marcarTodos');
    const checkboxes = document.querySelectorAll('.checkbox-ativo');
    const botaoLote = document.getElementById('botaoEtiquetasLote');

    function atualizarBotao() {
        const algumMarcado = Array.from(checkboxes).some(function (c) { return c.checked; });
        if (botaoLote) botaoLote.disabled = !algumMarcado;
    }

    if (marcarTodos) {
        marcarTodos.addEventListener('change', function () {
            checkboxes.forEach(function (c) { c.checked = marcarTodos.checked; });
            atualizarBotao();
        });
    }

    checkboxes.forEach(function (c) { c.addEventListener('change', atualizarBotao); });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Ativos - Lista';

require __DIR__ . '/../layouts/main.php';
