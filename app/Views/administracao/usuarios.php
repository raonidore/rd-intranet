<?php

use App\Components\Alert;
use App\Components\Avatar;
use App\Components\Badge;

ob_start();

$rotulosPerfil = [
    'admin' => ['Administrador', 'danger'],
    'ti' => ['TI', 'primary'],
    'consulta' => ['Consulta', 'secondary'],
];
?>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-1">
                <i class="bi bi-people"></i> Usuários do Sistema
            </h5>
            <small class="text-muted">
                Crie usuários e escolha quais módulos cada um pode acessar.
            </small>
        </div>

        <a href="<?= url('/administracao/usuarios/novo') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Novo usuário
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Usuário</th>
                    <th>Login</th>
                    <th>Perfil</th>
                    <th>Status</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <?php [$rotulo, $cor] = $rotulosPerfil[$u['perfil']] ?? [$u['perfil'], 'secondary']; ?>
                    <tr>
                        <td class="d-flex align-items-center gap-2">
                            <?= Avatar::initials($u['nome']) ?>
                            <?= htmlspecialchars($u['nome']) ?>
                        </td>
                        <td><?= htmlspecialchars($u['login']) ?></td>
                        <td><?= Badge::make($rotulo, $cor) ?></td>
                        <td><?= (int)$u['ativo'] === 1 ? Badge::make('Ativo', 'success') : Badge::make('Desativado', 'danger') ?></td>
                        <td class="text-end">
                            <div class="btn-group" role="group">
                                <a href="<?= url('/administracao/usuarios/editar?id=' . $u['id']) ?>"
                                   class="btn btn-sm btn-outline-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="<?= url('/administracao/usuarios/senha?id=' . $u['id']) ?>"
                                   class="btn btn-sm btn-outline-secondary" title="Redefinir senha">
                                    <i class="bi bi-key"></i>
                                </a>
                                <?php if ((int)$u['ativo'] === 1): ?>
                                    <a href="<?= url('/administracao/usuarios/desativar?id=' . $u['id']) ?>"
                                       class="btn btn-sm btn-outline-warning" title="Desativar">
                                        <i class="bi bi-lock"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="<?= url('/administracao/usuarios/ativar?id=' . $u['id']) ?>"
                                       class="btn btn-sm btn-outline-success" title="Ativar">
                                        <i class="bi bi-unlock"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="<?= url('/administracao/usuarios/excluir?id=' . $u['id']) ?>"
                                   class="btn btn-sm btn-outline-danger" title="Excluir">
                                    <i class="bi bi-trash"></i>
                                </a>
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
$titulo = 'Administração de Usuários';

require __DIR__ . '/../layouts/main.php';
