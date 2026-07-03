<?php
ob_start();

function iniciais($nome) {
    $partes = preg_split('/\s+/', trim($nome));
    $ini = '';

    foreach ($partes as $p) {
        if ($p !== '') {
            $ini .= mb_strtoupper(mb_substr($p, 0, 1));
        }
        if (mb_strlen($ini) >= 2) break;
    }

    return $ini ?: '?';
}

function nomeDepartamento($dep) {
    return match ($dep) {
        'ti' => 'TI',
        'financeiro' => 'Financeiro',
        'cobranca' => 'Cobrança',
        default => 'Indefinido'
    };
}

function badgeDepartamento($dep) {
    return match ($dep) {
        'ti' => 'primary',
        'financeiro' => 'success',
        'cobranca' => 'warning',
        default => 'secondary'
    };
}
?>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <small class="text-muted">Total de usuários</small>
                <h3><?= $total ?></h3>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <small class="text-muted">Ativos</small>
                <h3><?= $ativos ?></h3>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <small class="text-muted">Com SSH</small>
                <h3><?= $sshTotal ?></h3>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <small class="text-muted">Compartilhamentos</small>
                <h3>3</h3>
            </div>
        </div>
    </div>
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

        <a href="/rd.intranet/samba_usuario_novo.php" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Novo usuário
        </a>
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
                                <div class="avatar"><?= htmlspecialchars(iniciais($u['nome'])) ?></div>
                                <div>
                                    <strong><?= htmlspecialchars($u['nome']) ?></strong><br>
                                    <small class="text-muted">UID <?= htmlspecialchars($u['uid_linux'] ?? '-') ?></small>
                                </div>
                            </div>
                        </td>

                        <td><?= htmlspecialchars($u['login']) ?></td>

                        <td>
                            <span class="badge text-bg-<?= badgeDepartamento($u['departamento']) ?>">
                                <?= htmlspecialchars(nomeDepartamento($u['departamento'])) ?>
                            </span>
                        </td>

                        <td>
                            <?= (int)$u['ssh'] === 1
                                ? '<span class="badge text-bg-success">Sim</span>'
                                : '<span class="badge text-bg-secondary">Não</span>' ?>
                        </td>

                        <td>
                            <?= $u['status'] === 'ativo'
                                ? '<span class="badge text-bg-success">Ativo</span>'
                                : '<span class="badge text-bg-danger">Desativado</span>' ?>
                        </td>

                        <td class="text-end">
                            <a href="#" class="btn btn-sm btn-outline-primary" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="#" class="btn btn-sm btn-outline-secondary" title="Alterar senha">
                                <i class="bi bi-key"></i>
                            </a>
                            <a href="#" class="btn btn-sm btn-outline-warning" title="Desativar">
                                <i class="bi bi-lock"></i>
                            </a>
                            <a href="#" class="btn btn-sm btn-outline-danger" title="Excluir">
                                <i class="bi bi-trash"></i>
                            </a>
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
