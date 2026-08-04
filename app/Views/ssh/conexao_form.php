<?php

use App\Components\Alert;

ob_start();

$editando = $conexao !== null;
$acao = $editando ? url('/ssh/conexoes/editar') : url('/ssh/conexoes/novo');
?>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <i class="bi bi-hdd-network"></i>
            <?= $editando ? 'Editar conexão SSH' : 'Nova conexão SSH' ?>
        </h5>
    </div>

    <div class="card-body">
        <form method="post" action="<?= $acao ?>">
            <?php if ($editando): ?>
                <input type="hidden" name="id" value="<?= (int)$conexao['id'] ?>">
            <?php endif; ?>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Nome</label>
                    <input type="text" name="nome" class="form-control" required
                           placeholder="Ex: Cliente XPTO - Servidor de arquivos"
                           value="<?= htmlspecialchars($conexao['nome'] ?? '') ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Host</label>
                    <input type="text" name="host" class="form-control" required
                           placeholder="Ex: 192.168.1.10 ou servidor.cliente.com"
                           value="<?= htmlspecialchars($conexao['host'] ?? '') ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Porta</label>
                    <input type="number" name="porta" class="form-control" min="1" max="65535"
                           value="<?= (int)($conexao['porta'] ?? 22) ?>">
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Usuário</label>
                    <input type="text" name="usuario" class="form-control" required
                           placeholder="Ex: root, ti, ubuntu"
                           value="<?= htmlspecialchars($conexao['usuario'] ?? '') ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Observações (opcional)</label>
                    <input type="text" name="observacoes" class="form-control"
                           placeholder="Ex: atrás de NAT, acessar via VPN de Saída"
                           value="<?= htmlspecialchars($conexao['observacoes'] ?? '') ?>">
                </div>
            </div>

            <?php if (!$editando): ?>
                <hr>

                <div class="mb-3">
                    <label class="form-label d-block">Tipo de autenticação</label>
                    <div class="btn-group" role="group">
                        <input type="radio" class="btn-check" name="tipo_autenticacao" id="tipoSenha" value="senha" checked>
                        <label class="btn btn-outline-primary" for="tipoSenha"><i class="bi bi-key"></i> Senha</label>

                        <input type="radio" class="btn-check" name="tipo_autenticacao" id="tipoChave" value="chave_privada">
                        <label class="btn btn-outline-primary" for="tipoChave"><i class="bi bi-file-earmark-lock"></i> Chave privada</label>

                        <input type="radio" class="btn-check" name="tipo_autenticacao" id="tipoPerguntar" value="perguntar">
                        <label class="btn btn-outline-primary" for="tipoPerguntar"><i class="bi bi-question-circle"></i> Perguntar na hora</label>
                    </div>
                </div>

                <div id="grupoSenha" class="mb-3">
                    <label class="form-label">Senha</label>
                    <input type="password" name="senha" class="form-control" autocomplete="new-password">
                </div>

                <div id="grupoChave" class="d-none">
                    <div class="mb-3">
                        <label class="form-label">Chave privada (formato PEM)</label>
                        <textarea name="chave_privada" class="form-control font-monospace" rows="8"
                                  placeholder="-----BEGIN OPENSSH PRIVATE KEY-----&#10;...&#10;-----END OPENSSH PRIVATE KEY-----"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Senha da chave (passphrase) -- se houver</label>
                        <input type="password" name="chave_privada_senha" class="form-control" autocomplete="new-password">
                    </div>
                </div>

                <div id="grupoPerguntar" class="d-none">
                    <small class="text-muted d-block mb-3">
                        Nenhuma credencial fica salva neste servidor. A cada vez que você clicar em
                        "Conectar", vai aparecer um campo pra digitar a senha na hora.
                    </small>
                </div>
            <?php else: ?>
                <small class="text-muted d-block mb-3">
                    Pra trocar a credencial desta conexão, use "Redefinir credencial" na listagem.
                </small>
            <?php endif; ?>

            <div class="d-flex justify-content-between mt-3">
                <a href="<?= url('/ssh/conexoes') ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (!$editando): ?>
<script>
(function () {
    var radios = document.querySelectorAll('input[name="tipo_autenticacao"]');
    var grupoSenha = document.getElementById('grupoSenha');
    var grupoChave = document.getElementById('grupoChave');
    var grupoPerguntar = document.getElementById('grupoPerguntar');

    function atualizar() {
        var chave = document.getElementById('tipoChave').checked;
        var perguntar = document.getElementById('tipoPerguntar').checked;
        grupoSenha.classList.toggle('d-none', chave || perguntar);
        grupoChave.classList.toggle('d-none', !chave);
        grupoPerguntar.classList.toggle('d-none', !perguntar);
    }

    radios.forEach(function (r) { r.addEventListener('change', atualizar); });
    atualizar();
})();
</script>
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = $editando ? 'Editar Conexão SSH' : 'Nova Conexão SSH';

require __DIR__ . '/../layouts/main.php';
