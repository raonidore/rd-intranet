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
                                <?php foreach ($g['compartilhamentos'] as $nome): ?>
                                    <span class="badge text-bg-secondary"><?= htmlspecialchars($nome) ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (empty($g['usuarios'])): ?>
                                <span class="text-muted">Nenhum</span>
                            <?php else: ?>
                                <?php foreach ($g['usuarios'] as $u): ?>
                                    <span class="badge text-bg-light border" title="<?= htmlspecialchars($u['login']) ?>">
                                        <?= htmlspecialchars($u['nome']) ?>
                                    </span>
                                <?php endforeach; ?>
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
