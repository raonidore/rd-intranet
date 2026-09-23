<?php

use App\Components\Alert;
use App\Components\Badge;

ob_start();
?>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-1">
                <i class="bi bi-key-fill"></i> Cofre de Senhas
            </h5>
            <small class="text-muted">
                Guarde senhas de sites, wifi, painéis e outros acessos que a equipe precisa consultar depois.
                As senhas ficam encriptadas neste servidor; toda visualização fica registrada na Auditoria.
            </small>
        </div>

        <a href="<?= url('/seguranca/cofre-senhas/novo') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Novo segredo
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Categoria</th>
                    <th>Login</th>
                    <th>Privacidade</th>
                    <th>Dono</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($segredos)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Nenhum segredo cadastrado.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($segredos as $s): ?>
                    <?php $ehDono = (int)$s['usuario_id_dono'] === (int)$usuarioIdAtual; ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($s['nome']) ?>
                            <?php if (!empty($s['url_host'])): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($s['url_host']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($s['categoria']) ?></td>
                        <td><?= htmlspecialchars($s['usuario_login'] ?? '') ?></td>
                        <td><?= (int)$s['privado'] === 1 ? Badge::make('Privado', 'warning') : Badge::make('Compartilhado', 'info') ?></td>
                        <td><?= $ehDono ? '<em>Você</em>' : htmlspecialchars($s['dono_nome'] ?? '--') ?></td>
                        <td class="text-end">
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-sm btn-outline-success botao-revelar"
                                        data-id="<?= $s['id'] ?>" data-nome="<?= htmlspecialchars($s['nome']) ?>" title="Ver senha">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <?php if ($ehDono): ?>
                                    <a href="<?= url('/seguranca/cofre-senhas/editar?id=' . $s['id']) ?>"
                                       class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="<?= url('/seguranca/cofre-senhas/excluir?id=' . $s['id']) ?>"
                                       class="btn btn-sm btn-outline-danger" title="Excluir">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalRevelar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tituloRevelar">Ver senha</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="corpoRevelar">
                <div class="text-center text-muted"><i class="bi bi-hourglass-split"></i> Carregando...</div>
            </div>
        </div>
    </div>
</div>

<script>
const baseUrlRevelar = <?= json_encode(url('/seguranca/cofre-senhas/revelar')) ?>;

document.querySelectorAll('.botao-revelar').forEach(function (botao) {
    botao.addEventListener('click', function () {
        const modalRevelar = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRevelar'));
        const corpo = document.getElementById('corpoRevelar');
        document.getElementById('tituloRevelar').textContent = 'Ver senha -- ' + botao.dataset.nome;
        corpo.innerHTML = '<div class="text-center text-muted"><i class="bi bi-hourglass-split"></i> Carregando...</div>';
        modalRevelar.show();

        fetch(baseUrlRevelar, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + encodeURIComponent(botao.dataset.id)
        })
            .then(function (r) { return r.json(); })
            .then(function (dados) {
                if (!dados.success) {
                    corpo.innerHTML = '<div class="alert alert-danger mb-0">' + dados.message + '</div>';
                    return;
                }

                corpo.innerHTML = '';
                const grupo = document.createElement('div');
                grupo.className = 'input-group';
                const campo = document.createElement('input');
                campo.type = 'text';
                campo.className = 'form-control font-monospace';
                campo.readOnly = true;
                campo.value = dados.senha;
                const botaoCopiar = document.createElement('button');
                botaoCopiar.type = 'button';
                botaoCopiar.className = 'btn btn-outline-secondary';
                botaoCopiar.innerHTML = '<i class="bi bi-clipboard"></i> Copiar';
                botaoCopiar.addEventListener('click', function () {
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(dados.senha).then(function () {
                            botaoCopiar.innerHTML = '<i class="bi bi-check-lg"></i> Copiado';
                            setTimeout(function () { botaoCopiar.innerHTML = '<i class="bi bi-clipboard"></i> Copiar'; }, 1500);
                        }).catch(function () { /* sem permissão -- ignora */ });
                    }
                });
                grupo.appendChild(campo);
                grupo.appendChild(botaoCopiar);
                corpo.appendChild(grupo);
            })
            .catch(function () {
                corpo.innerHTML = '<div class="alert alert-danger mb-0">Erro ao buscar a senha.</div>';
            });
    });
});

document.getElementById('modalRevelar').addEventListener('hidden.bs.modal', function () {
    document.getElementById('corpoRevelar').innerHTML = '<div class="text-center text-muted"><i class="bi bi-hourglass-split"></i> Carregando...</div>';
});
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Cofre de Senhas';

require __DIR__ . '/../layouts/main.php';
