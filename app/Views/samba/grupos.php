<?php

use App\Components\Alert;

ob_start();
?>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h5 class="mb-1">
            <i class="bi bi-collection"></i> Grupos Samba
        </h5>
        <small class="text-muted">
            Grupos Linux usados por compartilhamentos e usuários Samba. O grupo Samba é sempre o mesmo
            grupo Linux do sistema operacional — não existe um grupo "só do Samba" separado.
        </small>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Grupo</th>
                    <th>Compartilhamentos</th>
                    <th>Usuários</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grupos as $g): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($g['nome']) ?></code></td>
                        <td>
                            <?php if (empty($g['compartilhamentos'])): ?>
                                <span class="text-muted">Nenhum</span>
                            <?php else: ?>
                                <?php foreach ($g['compartilhamentos'] as $c): ?>
                                    <a href="<?= url('/samba/compartilhamentos/editar?id=' . $c['id']) ?>"
                                       class="badge text-bg-secondary text-decoration-none">
                                        <?= htmlspecialchars($c['nome']) ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (empty($g['usuarios'])): ?>
                                <span class="text-muted">Nenhum</span>
                            <?php else: ?>
                                <?php foreach ($g['usuarios'] as $u): ?>
                                    <a href="<?= url('/samba/usuarios/editar?id=' . $u['id']) ?>"
                                       class="badge text-bg-light border text-decoration-none" title="<?= htmlspecialchars($u['login']) ?>">
                                        <?= htmlspecialchars($u['nome']) ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if (empty($g['compartilhamentos']) && empty($g['usuarios'])): ?>
                                <span class="text-muted">-</span>
                            <?php else: ?>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php if (!empty($g['compartilhamentos'])): ?>
                                            <li><h6 class="dropdown-header">Editar compartilhamento</h6></li>
                                            <?php foreach ($g['compartilhamentos'] as $c): ?>
                                                <li>
                                                    <a class="dropdown-item" href="<?= url('/samba/compartilhamentos/editar?id=' . $c['id']) ?>">
                                                        <i class="bi bi-folder2-open"></i> <?= htmlspecialchars($c['nome']) ?>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        <?php if (!empty($g['usuarios'])): ?>
                                            <?php if (!empty($g['compartilhamentos'])): ?><li><hr class="dropdown-divider"></li><?php endif; ?>
                                            <li><h6 class="dropdown-header">Editar usuário</h6></li>
                                            <?php foreach ($g['usuarios'] as $u): ?>
                                                <li>
                                                    <a class="dropdown-item" href="<?= url('/samba/usuarios/editar?id=' . $u['id']) ?>">
                                                        <i class="bi bi-person"></i> <?= htmlspecialchars($u['nome']) ?>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Grupos Samba';

require __DIR__ . '/../layouts/main.php';
