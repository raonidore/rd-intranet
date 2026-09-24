<?php

use App\Components\Alert;
use App\Components\Badge;

ob_start();

/** Uma tabela de itens, reaproveitada pro bloco Pessoal e pra cada cofre de equipe. */
function renderTabelaSegredos(array $itens, bool $ehDonoOuPodeEditar, bool $podeExcluir, bool $mostrarDono): void
{
?>
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Categoria</th>
                <th>Login</th>
                <?php if ($mostrarDono): ?><th>Adicionado por</th><?php endif; ?>
                <th class="text-end">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($itens)): ?>
                <tr>
                    <td colspan="<?= $mostrarDono ? 5 : 4 ?>" class="text-center text-muted py-4">Nenhum segredo cadastrado.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($itens as $s): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($s['nome']) ?>
                        <?php if (!empty($s['url_host'])): ?>
                            <br><small class="text-muted"><?= htmlspecialchars($s['url_host']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($s['categoria']) ?></td>
                    <td><?= htmlspecialchars($s['usuario_login'] ?? '') ?></td>
                    <?php if ($mostrarDono): ?><td><?= htmlspecialchars($s['dono_nome'] ?? '--') ?></td><?php endif; ?>
                    <td class="text-end">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-success botao-revelar"
                                    data-id="<?= $s['id'] ?>" data-nome="<?= htmlspecialchars($s['nome']) ?>" title="Ver senha">
                                <i class="bi bi-eye"></i>
                            </button>
                            <?php if ($ehDonoOuPodeEditar): ?>
                                <a href="<?= url('/seguranca/cofre-senhas/editar?id=' . $s['id']) ?>"
                                   class="btn btn-sm btn-outline-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            <?php endif; ?>
                            <?php if ($podeExcluir): ?>
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
<?php
}
?>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-1">
                <i class="bi bi-key-fill"></i> Cofre de Senhas
            </h5>
            <small class="text-muted">
                Guarde senhas de sites, wifi, painéis e outros acessos. As senhas ficam encriptadas neste servidor;
                toda visualização fica registrada na Auditoria.
            </small>
        </div>

        <a href="<?= url('/seguranca/cofre-senhas/novo') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Novo segredo
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <i class="bi bi-person-fill"></i> Meu Cofre Pessoal
        <small class="text-muted">-- só você vê estes segredos</small>
    </div>
    <div class="card-body p-0">
        <?php renderTabelaSegredos($pessoal, true, true, false); ?>
    </div>
</div>

<?php foreach ($cofres as $cofre): ?>
    <?php
        $ehEditor = isset($cofresQueEdita[$cofre['id']]);
        $itens = $itensPorCofre[$cofre['id']] ?? [];
    ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div>
                <i class="bi bi-people-fill"></i> <?= htmlspecialchars($cofre['nome']) ?>
                <?php if (!empty($cofre['descricao'])): ?>
                    <small class="text-muted">-- <?= htmlspecialchars($cofre['descricao']) ?></small>
                <?php endif; ?>
            </div>
            <?= $ehEditor ? Badge::make('Você pode editar', 'info') : Badge::make('Somente visualização', 'secondary') ?>
        </div>
        <div class="card-body p-0">
            <?php renderTabelaSegredos($itens, $ehEditor, $ehEditor, true); ?>
        </div>
    </div>
<?php endforeach; ?>

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
