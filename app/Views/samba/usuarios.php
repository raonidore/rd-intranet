<?php

use App\Components\Alert;
use App\Components\Avatar;
use App\Components\Badge;
use App\Components\Button;
use App\Components\StatCard;

ob_start();
?>

<div class="row mb-4">
    <?= StatCard::make('Total de usuários', $total) ?>
    <?= StatCard::make('Ativos', $ativos) ?>
    <?= StatCard::make('Com SSH', $sshTotal) ?>
    <?= StatCard::make('Compartilhamentos', 3) ?>
</div>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0">Usuários cadastrados</h5>
            <small class="text-muted">Usuários Linux/Samba registrados na RD Intranet</small>
        </div>

        <?= Button::primary('Novo usuário', '/rd.intranet/samba_usuario_novo.php', 'plus-lg') ?>
    </div>

    <div class="card-body">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Usuário</th>
                    <th>Login</th>
                    <th>Departamento</th>
                    <th>SSH</th>
                    <th>Status</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <?= Avatar::initials($u['nome']) ?>
                                <div>
                                    <strong><?= htmlspecialchars($u['nome']) ?></strong><br>
                                    <small class="text-muted">UID <?= htmlspecialchars($u['uid_linux'] ?? '-') ?></small>
                                </div>
                            </div>
                        </td>

                        <td><?= htmlspecialchars($u['login']) ?></td>
                        <td><?= Badge::departamento($u['departamento']) ?></td>

                        <td>
                            <?= (int)$u['ssh'] === 1
                                ? Badge::make('Sim', 'success')
                                : Badge::make('Não', 'secondary') ?>
                        </td>

                        <td><?= Badge::status($u['status']) ?></td>

                        <td class="text-end">
                            <?= Button::outline('Editar', '#', 'pencil', 'primary') ?>
                            <?= Button::outline('Alterar senha', '#', 'key', 'secondary') ?>
                            <?= Button::outline('Desativar', '#', 'lock', 'warning') ?>
                            <?= Button::outline('Excluir', '#', 'trash', 'danger') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Usuários Samba';

require __DIR__ . '/../layouts/main.php';
