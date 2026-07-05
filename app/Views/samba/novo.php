<?php

use App\Components\Alert;

ob_start();
?>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">Novo usuário Samba</h5>
        <small class="text-muted">Cria o usuário no Linux, Samba e no cadastro da RD Intranet.</small>
    </div>

    <div class="card-body">
        <form method="post" action="<?= url('/samba/usuarios/novo') ?>" id="formNovoUsuario">
            <div class="mb-3">
                <label class="form-label">Nome completo</label>
                <input type="text" name="nome" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Login</label>
                <input type="text" name="login" class="form-control" required placeholder="ex: luisaoliveira">
            </div>

            <div class="mb-3">
                <label class="form-label">Grupo</label>
                <select name="grupo" id="grupo_select" class="form-select" onchange="rdAlternarNovoGrupo(this)" required>
                    <option value="" disabled selected>Selecione...</option>
                    <?php foreach ($grupos as $grupo): ?>
                        <option value="<?= htmlspecialchars($grupo) ?>"><?= htmlspecialchars($grupo) ?></option>
                    <?php endforeach; ?>
                    <option value="__novo__">+ Novo grupo (digitar)</option>
                </select>

                <div id="bloco_novo_grupo" class="mt-2" style="display:none">
                    <input type="text" id="grupo_texto" class="form-control" placeholder="Nome do novo grupo">
                </div>

                <small class="text-muted">Grupo Linux do usuário. Escolha um já existente na lista ou "+ Novo grupo" para criar um (o grupo é criado automaticamente no sistema).</small>
            </div>

            <div class="mb-3">
                <label class="form-label">Acesso SSH</label>
                <select name="ssh" class="form-select" required>
                    <option value="nao">Não</option>
                    <option value="sim">Sim</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Senha inicial</label>
                <input type="password" name="senha" class="form-control" required minlength="8">
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Criar usuário
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

document.getElementById('formNovoUsuario').addEventListener('submit', function (e) {
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
$titulo = 'Novo Usuário Samba';

require __DIR__ . '/../layouts/main.php';
