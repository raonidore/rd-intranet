<?php

use App\Components\Alert;

ob_start();
?>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h5 class="mb-1"><i class="bi bi-puzzle"></i> Módulos Apache</h5>
        <small class="text-muted">
            Habilite ou desabilite módulos (equivalente a <code>a2enmod</code>/<code>a2dismod</code>).
            A configuração é validada (<code>apache2ctl configtest</code>) antes de recarregar; se ficar inválida, a mudança é desfeita automaticamente.
        </small>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <input type="text" class="form-control" id="filtro" placeholder="Filtrar por nome do módulo...">
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0" style="max-height:600px; overflow-y:auto">
        <table class="table table-hover align-middle mb-0" id="tabela-modulos">
            <thead>
                <tr>
                    <th>Módulo</th>
                    <th>Status</th>
                    <th class="text-end">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($modulos as $m): ?>
                    <tr class="linha-modulo" data-busca="<?= htmlspecialchars(strtolower($m['nome'])) ?>">
                        <td>
                            <code><?= htmlspecialchars($m['nome']) ?></code>
                            <?php if ($m['protegido']): ?>
                                <span class="badge text-bg-secondary ms-1" title="Módulo essencial, não pode ser desabilitado por aqui">
                                    <i class="bi bi-shield-lock"></i> essencial
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $m['habilitado']
                                ? '<span class="badge text-bg-success">Habilitado</span>'
                                : '<span class="badge text-bg-secondary">Desabilitado</span>' ?>
                        </td>
                        <td class="text-end">
                            <?php if ($m['protegido']): ?>
                                <button class="btn btn-sm btn-outline-secondary" disabled>
                                    <i class="bi bi-lock"></i>
                                </button>
                            <?php elseif ($m['habilitado']): ?>
                                <form method="post" action="<?= url('/apache/modulos/desabilitar') ?>" class="d-inline">
                                    <input type="hidden" name="nome" value="<?= htmlspecialchars($m['nome']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-warning" title="Desabilitar">
                                        <i class="bi bi-toggle-on"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="<?= url('/apache/modulos/habilitar') ?>" class="d-inline">
                                    <input type="hidden" name="nome" value="<?= htmlspecialchars($m['nome']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Habilitar">
                                        <i class="bi bi-toggle-off"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.getElementById('filtro').addEventListener('input', function (e) {
    const termo = e.target.value.toLowerCase();
    document.querySelectorAll('.linha-modulo').forEach(function (linha) {
        linha.style.display = linha.dataset.busca.includes(termo) ? '' : 'none';
    });
});
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Módulos Apache';

require __DIR__ . '/../layouts/main.php';
