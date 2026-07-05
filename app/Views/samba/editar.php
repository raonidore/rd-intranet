<?php

use App\Components\Avatar;
use App\Components\Badge;

ob_start();
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">Editar usuário Samba</h5>
        <small class="text-muted">Atualize nome, departamento e acesso SSH.</small>
    </div>

    <div class="card-body">
        <div class="d-flex align-items-center gap-3 mb-4">
            <?= Avatar::initials($usuarioSamba['nome']) ?>

            <div>
                <strong><?= htmlspecialchars($usuarioSamba['nome']) ?></strong><br>
                <small class="text-muted"><?= htmlspecialchars($usuarioSamba['login']) ?></small><br>
                <?= Badge::status($usuarioSamba['status']) ?>
            </div>
        </div>

        <form method="post" action="<?= url('/samba/usuarios/editar') ?>" id="formEditarUsuario">
            <input type="hidden" name="id" value="<?= htmlspecialchars($usuarioSamba['id']) ?>">

            <div class="mb-3">
                <label class="form-label">Nome completo</label>
                <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($usuarioSamba['nome']) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Login</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($usuarioSamba['login']) ?>" disabled>
                <small class="text-muted">O login não será alterado para evitar inconsistências no Linux/Samba.</small>
            </div>

            <div class="mb-3">
                <label class="form-label">Grupo</label>
                <select name="departamento" id="grupo_select" class="form-select" onchange="rdAlternarNovoGrupo(this)">
                    <?php $grupoAtual = $usuarioSamba['departamento']; ?>
                    <?php foreach ($grupos as $grupo): ?>
                        <option value="<?= htmlspecialchars($grupo) ?>" <?= $grupoAtual === $grupo ? 'selected' : '' ?>>
                            <?= htmlspecialchars($grupo) ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="__novo__" <?= !in_array($grupoAtual, $grupos, true) ? 'selected' : '' ?>>
                        + Novo grupo (digitar)
                    </option>
                </select>

                <div id="bloco_novo_grupo" class="mt-2" style="<?= in_array($grupoAtual, $grupos, true) ? 'display:none' : '' ?>">
                    <input type="text" id="grupo_texto" class="form-control" placeholder="Nome do novo grupo"
                           value="<?= !in_array($grupoAtual, $grupos, true) ? htmlspecialchars($grupoAtual) : '' ?>">
                </div>

                <small class="text-muted">Grupo Linux do usuário. Escolha um já existente na lista ou "+ Novo grupo" para criar um (o grupo é criado automaticamente no sistema).</small>
            </div>

            <div class="mb-3">
                <label class="form-label">Acesso SSH</label>
                <select name="ssh" class="form-select" required>
                    <option value="0" <?= (int)$usuarioSamba['ssh'] === 0 ? 'selected' : '' ?>>Não</option>
                    <option value="1" <?= (int)$usuarioSamba['ssh'] === 1 ? 'selected' : '' ?>>Sim</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> Salvar alterações
            </button>

            <a href="<?= url('/samba/usuarios') ?>" class="btn btn-secondary">
                Voltar
            </a>
        </form>
    </div>
</div>

<script>
function rdAlternarNovoGrupo(select) {
    document.getElementById('bloco_novo_grupo').style.display = select.value === '__novo__' ? '' : 'none';
}

document.getElementById('formEditarUsuario').addEventListener('submit', function (e) {
    const select = document.getElementById('grupo_select');

    if (select.value === '__novo__') {
        const digitado = document.getElementById('grupo_texto').value.trim().toLowerCase();

        if (!digitado) {
            e.preventDefault();
            alert('Digite o nome do novo grupo.');
            return;
        }

        select.add(new Option(digitado, digitado, true, true));
    }
});
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Editar usuário Samba';

require __DIR__ . '/../layouts/main.php';
