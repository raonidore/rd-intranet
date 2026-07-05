<?php

use App\Components\Alert;
use App\Components\Avatar;
use App\Components\Badge;
use App\Components\Button;
use App\Components\StatCard;

ob_start();
?>

<style>
.user-card {
    border: 0;
    border-left: 5px solid #6c757d;
    transition: .2s;
}
.user-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(0,0,0,.08);
}
.user-card.ti { border-left-color: #0d6efd; }
.user-card.financeiro { border-left-color: #198754; }
.user-card.cobranca { border-left-color: #ffc107; }
.user-card.desativado { opacity: .65; background: #f8f9fa; }
.search-box { max-width: 520px; }
.action-btn { min-width: 36px; }
</style>

<div class="row mb-4">
    <?= StatCard::make('Total de usuários', $total) ?>
    <?= StatCard::make('Ativos', $ativos) ?>
    <?= StatCard::make('Com SSH', $sshTotal) ?>
    <?= StatCard::make('Compartilhamentos', 3) ?>
</div>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-1">
                <i class="bi bi-people"></i> Usuários Samba
            </h5>
            <small class="text-muted">
                Gerencie contas Linux/Samba, senha, SSH, status e permissões.
            </small>
        </div>

        <a href="<?= url('/samba/usuarios/novo') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Novo usuário
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-end gap-3 flex-wrap">
        <div class="search-box flex-grow-1">
            <label class="form-label">Pesquisar</label>
            <input type="text" id="pesquisaUsuarios" class="form-control" placeholder="Digite nome, login, departamento ou status...">
        </div>

        <div class="text-muted">
            <strong id="contadorUsuarios"><?= count($usuarios) ?></strong>
            usuário(s) encontrado(s)
        </div>
    </div>
</div>

<div class="row g-3" id="listaUsuarios">
    <?php foreach ($usuarios as $u): ?>
        <?php
            $status = $u['status'] ?? 'ativo';
            $departamento = $u['departamento'] ?? 'indefinido';
            $desativado = $status === 'desativado';
            $textoBusca = strtolower(($u['nome'] ?? '') . ' ' . ($u['login'] ?? '') . ' ' . $departamento . ' ' . $status);
        ?>

        <div class="col-12 user-item" data-busca="<?= htmlspecialchars($textoBusca) ?>">
            <div class="card user-card <?= htmlspecialchars($departamento) ?> <?= $desativado ? 'desativado' : '' ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">

                        <div class="d-flex align-items-center gap-3">
                            <?= Avatar::initials($u['nome']) ?>

                            <div>
                                <h5 class="mb-1 <?= $desativado ? 'text-muted' : '' ?>">
                                    <?= htmlspecialchars($u['nome']) ?>
                                </h5>

                                <div class="text-muted small">
                                    <span class="me-3">
                                        <i class="bi bi-person-badge"></i>
                                        <?= htmlspecialchars($u['login']) ?>
                                    </span>

                                    <span class="me-3">
                                        UID <?= htmlspecialchars($u['uid_linux'] ?? '-') ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="text-end">
                            <div class="mb-2">
                                <?= Badge::departamento($departamento) ?>

                                <?= (int)$u['ssh'] === 1
                                    ? Badge::make('SSH', 'success')
                                    : Badge::make('Sem SSH', 'secondary') ?>

                                <?= Badge::status($status) ?>
                            </div>

                            <div class="btn-group" role="group">

                                <a href="<?= url('/samba/usuarios/editar?id=' . $u['id']) ?>"
                                   class="btn btn-sm btn-outline-primary action-btn"
                                   title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <a href="<?= url('/samba/usuarios/senha?id=' . $u['id']) ?>"
                                   class="btn btn-sm btn-outline-secondary action-btn"
                                   title="Alterar senha">
                                    <i class="bi bi-key"></i>
                                </a>

                                <?php if ($desativado): ?>
                                    <a href="<?= url('/samba/usuarios/ativar?id=' . $u['id']) ?>"
                                       class="btn btn-sm btn-outline-success action-btn"
                                       title="Ativar">
                                        <i class="bi bi-unlock"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="<?= url('/samba/usuarios/desativar?id=' . $u['id']) ?>"
                                       class="btn btn-sm btn-outline-warning action-btn"
                                       title="Desativar">
                                        <i class="bi bi-lock"></i>
                                    </a>
                                <?php endif; ?>

                                <a href="<?= url('/samba/usuarios/excluir?id=' . $u['id']) ?>"
                                   class="btn btn-sm btn-outline-danger action-btn"
                                   title="Excluir">
                                    <i class="bi bi-trash"></i>
                                </a>

                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

    <?php endforeach; ?>
</div>

<script>
const pesquisa = document.getElementById('pesquisaUsuarios');
const contador = document.getElementById('contadorUsuarios');

pesquisa.addEventListener('input', function () {
    const termo = this.value.toLowerCase();
    const itens = document.querySelectorAll('.user-item');
    let visiveis = 0;

    itens.forEach(function (item) {
        const busca = item.getAttribute('data-busca');
        const mostrar = busca.includes(termo);

        item.style.display = mostrar ? '' : 'none';

        if (mostrar) {
            visiveis++;
        }
    });

    contador.textContent = visiveis;
});
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Usuários Samba';

require __DIR__ . '/../layouts/main.php';
