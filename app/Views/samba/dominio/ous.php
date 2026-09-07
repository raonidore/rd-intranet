<?php

use App\Components\Alert;

ob_start();
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-1"><i class="bi bi-diagram-2"></i> Unidades Organizacionais (OUs)</h5>
            <small class="text-muted">Organiza usuários/grupos/computadores e serve de alvo pra vínculo de GPO.</small>
        </div>
        <a href="<?= url('/samba/dominio/ous/novo') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Nova OU
        </a>
    </div>
</div>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr><th>OU</th><th class="text-end">Ações</th></tr>
            </thead>
            <tbody>
                <?php if (empty($ous)): ?>
                    <tr><td colspan="2" class="text-center text-muted py-4">Nenhuma OU cadastrada.</td></tr>
                <?php endif; ?>
                <?php foreach ($ous as $ou): ?>
                    <tr>
                        <td class="font-monospace"><?= htmlspecialchars($ou) ?></td>
                        <td class="text-end">
                            <a href="<?= url('/samba/dominio/ous/excluir?dn=' . urlencode($ou)) ?>"
                               class="btn btn-sm btn-outline-danger" title="Excluir"
                               onclick="return confirm('Excluir esta OU? Só funciona se estiver vazia.')">
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
$titulo = 'Samba - Domínio - OUs';
require __DIR__ . '/../../layouts/main.php';
