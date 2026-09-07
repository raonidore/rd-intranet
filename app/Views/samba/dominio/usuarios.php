<?php

use App\Components\Alert;
use App\Components\Badge;

ob_start();
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-1"><i class="bi bi-people"></i> Usuários do Domínio</h5>
            <small class="text-muted">Contas do Active Directory, geridas via <code>samba-tool</code>.</small>
        </div>
        <a href="<?= url('/samba/dominio/usuarios/novo') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Novo usuário
        </a>
    </div>
</div>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Usuário</th>
                    <th>Status</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($usuarios)): ?>
                    <tr><td colspan="3" class="text-center text-muted py-4">Nenhum usuário encontrado.</td></tr>
                <?php endif; ?>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td class="font-monospace"><?= htmlspecialchars($u['username']) ?></td>
                        <td>
                            <?= !empty($u['habilitado']) ? Badge::make('Ativo', 'success') : Badge::make('Desativado', 'secondary') ?>
                        </td>
                        <td class="text-end">
                            <a href="<?= url('/samba/dominio/usuarios/ver?username=' . urlencode($u['username'])) ?>"
                               class="btn btn-sm btn-outline-secondary" title="Ver detalhes">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="<?= url('/samba/dominio/usuarios/senha?username=' . urlencode($u['username'])) ?>"
                               class="btn btn-sm btn-outline-secondary" title="Resetar senha">
                                <i class="bi bi-key"></i>
                            </a>
                            <a href="<?= url('/samba/dominio/usuarios/expiracao?username=' . urlencode($u['username'])) ?>"
                               class="btn btn-sm btn-outline-secondary" title="Expiração de senha">
                                <i class="bi bi-calendar-event"></i>
                            </a>
                            <a href="<?= url('/samba/dominio/usuarios/desbloquear?username=' . urlencode($u['username'])) ?>"
                               class="btn btn-sm btn-outline-secondary" title="Desbloquear (conta travada por tentativas)"
                               onclick="return confirm('Desbloquear esta conta?')">
                                <i class="bi bi-shield-lock"></i>
                            </a>
                            <?php if (!empty($u['habilitado'])): ?>
                                <a href="<?= url('/samba/dominio/usuarios/desativar?username=' . urlencode($u['username'])) ?>"
                                   class="btn btn-sm btn-outline-warning" title="Desativar">
                                    <i class="bi bi-lock"></i>
                                </a>
                            <?php else: ?>
                                <a href="<?= url('/samba/dominio/usuarios/ativar?username=' . urlencode($u['username'])) ?>"
                                   class="btn btn-sm btn-outline-success" title="Ativar">
                                    <i class="bi bi-unlock"></i>
                                </a>
                            <?php endif; ?>
                            <a href="<?= url('/samba/dominio/usuarios/excluir?username=' . urlencode($u['username'])) ?>"
                               class="btn btn-sm btn-outline-danger" title="Excluir"
                               onclick="return confirm('Excluir o usuário \'<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>\'? Essa ação não pode ser desfeita.')">
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
$titulo = 'Samba - Domínio - Usuários';
require __DIR__ . '/../../layouts/main.php';
