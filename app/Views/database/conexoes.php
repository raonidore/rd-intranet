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
                <i class="bi bi-database"></i> Conexões MySQL/MariaDB
            </h5>
            <small class="text-muted">
                Servidores de banco de dados de clientes. As credenciais ficam encriptadas no servidor.
            </small>
        </div>

        <a href="<?= url('/banco-dados/conexoes/novo') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Nova conexão
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Host</th>
                    <th>Porta</th>
                    <th>Usuário</th>
                    <th>Banco padrão</th>
                    <th>Status</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($conexoes)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Nenhuma conexão cadastrada.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($conexoes as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['nome']) ?></td>
                        <td><code><?= htmlspecialchars($c['host']) ?></code></td>
                        <td><?= (int)$c['porta'] ?></td>
                        <td><?= htmlspecialchars($c['usuario']) ?></td>
                        <td><?= $c['banco_padrao'] ? htmlspecialchars($c['banco_padrao']) : '<span class="text-muted">-</span>' ?></td>
                        <td><?= (int)$c['ativo'] === 1 ? Badge::make('Ativo', 'success') : Badge::make('Desativado', 'danger') ?></td>
                        <td class="text-end">
                            <div class="btn-group" role="group">
                                <a href="<?= url('/banco-dados/console?conexao=' . $c['id']) ?>"
                                   class="btn btn-sm btn-outline-success" title="Abrir console">
                                    <i class="bi bi-terminal"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-info botao-testar" data-id="<?= $c['id'] ?>" title="Testar conexão">
                                    <i class="bi bi-plug"></i>
                                </button>
                                <a href="<?= url('/banco-dados/conexoes/editar?id=' . $c['id']) ?>"
                                   class="btn btn-sm btn-outline-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="<?= url('/banco-dados/conexoes/senha?id=' . $c['id']) ?>"
                                   class="btn btn-sm btn-outline-secondary" title="Redefinir credencial">
                                    <i class="bi bi-key"></i>
                                </a>
                                <?php if ((int)$c['ativo'] === 1): ?>
                                    <a href="<?= url('/banco-dados/conexoes/desativar?id=' . $c['id']) ?>"
                                       class="btn btn-sm btn-outline-warning" title="Desativar">
                                        <i class="bi bi-lock"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="<?= url('/banco-dados/conexoes/ativar?id=' . $c['id']) ?>"
                                       class="btn btn-sm btn-outline-success" title="Ativar">
                                        <i class="bi bi-unlock"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="<?= url('/banco-dados/conexoes/excluir?id=' . $c['id']) ?>"
                                   class="btn btn-sm btn-outline-danger" title="Excluir">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalTeste" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Teste de conexão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalTesteCorpo">
                <div class="text-center text-muted"><i class="bi bi-hourglass-split"></i> Testando...</div>
            </div>
        </div>
    </div>
</div>

<script>
const baseUrl = <?= json_encode(url('/banco-dados/conexoes/testar')) ?>;

document.querySelectorAll('.botao-testar').forEach(function (botao) {
    botao.addEventListener('click', function () {
        const modalTeste = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalTeste'));
        const id = botao.dataset.id;
        const corpo = document.getElementById('modalTesteCorpo');
        corpo.innerHTML = '<div class="text-center text-muted"><i class="bi bi-hourglass-split"></i> Testando...</div>';
        modalTeste.show();

        fetch(baseUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + encodeURIComponent(id)
        })
            .then(function (r) { return r.json(); })
            .then(function (dados) {
                const cor = dados.success ? 'success' : 'danger';
                const icone = dados.success ? 'check-circle' : 'x-circle';
                corpo.innerHTML = '<div class="alert alert-' + cor + ' mb-0"><i class="bi bi-' + icone + '"></i> ' + dados.mensagem + '</div>';
            })
            .catch(function () {
                corpo.innerHTML = '<div class="alert alert-danger mb-0">Erro ao testar conexão.</div>';
            });
    });
});
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Banco de Dados - Conexões';

require __DIR__ . '/../layouts/main.php';
