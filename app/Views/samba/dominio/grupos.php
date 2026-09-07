<?php

use App\Components\Alert;

ob_start();
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-1"><i class="bi bi-collection"></i> Grupos do Domínio</h5>
            <small class="text-muted">Grupos do Active Directory, geridos via <code>samba-tool</code>.</small>
        </div>
        <a href="<?= url('/samba/dominio/grupos/novo') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Novo grupo
        </a>
    </div>
</div>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Grupo</th>
                    <th>Membros</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($grupos)): ?>
                    <tr><td colspan="3" class="text-center text-muted py-4">Nenhum grupo encontrado.</td></tr>
                <?php endif; ?>
                <?php foreach ($grupos as $g): ?>
                    <tr>
                        <td class="font-monospace"><?= htmlspecialchars($g['nome']) ?></td>
                        <td><?= (int)($g['membros'] ?? 0) ?></td>
                        <td class="text-end">
                            <a href="<?= url('/samba/dominio/grupos/membros?nome=' . urlencode($g['nome'])) ?>"
                               class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-people"></i> Membros
                            </a>
                            <a href="<?= url('/samba/dominio/grupos/excluir?nome=' . urlencode($g['nome'])) ?>"
                               class="btn btn-sm btn-outline-danger" title="Excluir"
                               onclick="return confirm('Excluir o grupo \'<?= htmlspecialchars($g['nome'], ENT_QUOTES) ?>\'?')">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Samba - Domínio - Grupos';
require __DIR__ . '/../../layouts/main.php';
