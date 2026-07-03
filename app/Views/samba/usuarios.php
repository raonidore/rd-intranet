<?php

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

<?php if (isset($_SESSION['flash_msg'])): ?>
    <div class="alert alert-<?= $_SESSION['flash_tipo'] === 'success' ? 'success' : 'danger' ?> shadow-sm">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <strong>
                    <?= $_SESSION['flash_tipo'] === 'success' ? 'Operação concluída com sucesso.' : 'Falha na operação.' ?>
                </strong><br>
                <?= htmlspecialchars($_SESSION['flash_msg']) ?>
            </div>

            <?php if (isset($_SESSION['flash_tecnico'])): ?>
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalDetalhes">
                    <i class="bi bi-terminal"></i> Detalhes técnicos
                </button>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

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

<?php if (isset($_SESSION['flash_tecnico'])): ?>
<div class="modal fade" id="modalDetalhes" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes técnicos da operação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <pre class="bg-dark text-light p-3 rounded"><?= htmlspecialchars($_SESSION['flash_tecnico']) ?></pre>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
unset($_SESSION['flash_msg'], $_SESSION['flash_tipo'], $_SESSION['flash_tecnico']);

$conteudo = ob_get_clean();
$titulo = 'Usuários Samba';

require __DIR__ . '/../layouts/main.php';
