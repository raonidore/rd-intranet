<?php

use App\Components\Alert;

ob_start();
?>

<?= Alert::flash() ?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-key"></i> Redefinir credencial</h5>
                <small class="text-muted"><?= htmlspecialchars($conexao['nome']) ?> (<?= htmlspecialchars($conexao['usuario']) ?>@<?= htmlspecialchars($conexao['host']) ?>)</small>
            </div>

            <div class="card-body">
                <form method="post" action="<?= url('/ssh/conexoes/credencial') ?>">
                    <input type="hidden" name="id" value="<?= (int)$conexao['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label d-block">Tipo de autenticação</label>
                        <div class="btn-group" role="group">
                            <input type="radio" class="btn-check" name="tipo_autenticacao" id="tipoSenha" value="senha"
                                   <?= $conexao['tipo_autenticacao'] === 'senha' ? 'checked' : '' ?>>
                            <label class="btn btn-outline-primary" for="tipoSenha"><i class="bi bi-key"></i> Senha</label>

                            <input type="radio" class="btn-check" name="tipo_autenticacao" id="tipoChave" value="chave_privada"
                                   <?= $conexao['tipo_autenticacao'] === 'chave_privada' ? 'checked' : '' ?>>
                            <label class="btn btn-outline-primary" for="tipoChave"><i class="bi bi-file-earmark-lock"></i> Chave privada</label>
                        </div>
                    </div>

                    <div id="grupoSenha">
                        <label class="form-label">Nova senha</label>
                        <input type="password" name="senha" class="form-control" autocomplete="new-password">
                    </div>

                    <div id="grupoChave" class="d-none">
                        <div class="mb-3">
                            <label class="form-label">Nova chave privada (formato PEM)</label>
                            <textarea name="chave_privada" class="form-control font-monospace" rows="8"
                                      placeholder="-----BEGIN OPENSSH PRIVATE KEY-----&#10;...&#10;-----END OPENSSH PRIVATE KEY-----"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Senha da chave (passphrase) -- se houver</label>
                            <input type="password" name="chave_privada_senha" class="form-control" autocomplete="new-password">
                        </div>
                    </div>

                    <small class="text-muted d-block mb-3">
                        Deixe os campos em branco pra manter a credencial atual e só trocar outra coisa
                        (ex: só a passphrase).
                    </small>

                    <div class="d-flex justify-content-between">
                        <a href="<?= url('/ssh/conexoes') ?>" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Salvar nova credencial
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var radios = document.querySelectorAll('input[name="tipo_autenticacao"]');
    var grupoSenha = document.getElementById('grupoSenha');
    var grupoChave = document.getElementById('grupoChave');

    function atualizar() {
        var chave = document.getElementById('tipoChave').checked;
        grupoSenha.classList.toggle('d-none', chave);
        grupoChave.classList.toggle('d-none', !chave);
    }

    radios.forEach(function (r) { r.addEventListener('change', atualizar); });
    atualizar();
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Redefinir Credencial SSH';

require __DIR__ . '/../layouts/main.php';
