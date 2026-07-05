<?php

use App\Components\Alert;

ob_start();
?>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h5 class="mb-1"><i class="bi bi-globe"></i> Sites (VirtualHosts)</h5>
        <small class="text-muted">
            Habilite ou desabilite sites (equivalente a <code>a2ensite</code>/<code>a2dissite</code>).
            A configuração é validada antes de recarregar; se ficar inválida, a mudança é desfeita automaticamente.
        </small>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Arquivo</th>
                    <th>ServerName</th>
                    <th>DocumentRoot</th>
                    <th>Status</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sites as $s): ?>
                    <tr>
                        <td>
                            <code><?= htmlspecialchars($s['nome']) ?></code>
                            <?php if ($s['atual']): ?>
                                <span class="badge text-bg-primary ms-1" title="Servindo a RD Intranet agora mesmo">
                                    <i class="bi bi-broadcast"></i> em uso
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($s['server_name']) ?></td>
                        <td><code><?= htmlspecialchars($s['docroot']) ?></code></td>
                        <td>
                            <?= $s['habilitado']
                                ? '<span class="badge text-bg-success">Habilitado</span>'
                                : '<span class="badge text-bg-secondary">Desabilitado</span>' ?>
                        </td>
                        <td class="text-end">
                            <div class="btn-group">
                                <a href="<?= url('/apache/sites/ver?nome=' . urlencode($s['nome'])) ?>"
                                   class="btn btn-sm btn-outline-primary" title="Ver conteúdo">
                                    <i class="bi bi-eye"></i>
                                </a>

                                <?php if ($s['atual']): ?>
                                    <button class="btn btn-sm btn-outline-secondary" disabled title="Em uso, não pode desabilitar por aqui">
                                        <i class="bi bi-lock"></i>
                                    </button>
                                <?php elseif ($s['habilitado']): ?>
                                    <form method="post" action="<?= url('/apache/sites/desabilitar') ?>" class="d-inline">
                                        <input type="hidden" name="nome" value="<?= htmlspecialchars($s['nome']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-warning" title="Desabilitar">
                                            <i class="bi bi-toggle-on"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="<?= url('/apache/sites/habilitar') ?>" class="d-inline">
                                        <input type="hidden" name="nome" value="<?= htmlspecialchars($s['nome']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Habilitar">
                                            <i class="bi bi-toggle-off"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Sites Apache';

require __DIR__ . '/../layouts/main.php';
