<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-envelope me-1"></i> E-mail (SMTP)</h4>
    <small class="text-muted d-block mb-1">
        <a href="<?= url('/administracao/integracoes') ?>"><i class="bi bi-arrow-left"></i> Integrações</a>
    </small>
    <small class="text-muted">
        Usado pelo módulo Backup em Nuvem para enviar relatório diário e alerta de falha
        (ativados por destino em <a href="<?= url('/backup/configuracao') ?>">Backup &gt; Configuração</a>).
    </small>
</div>

<div class="card border-0 shadow-sm" style="max-width:640px">
    <div class="card-body">
        <form method="post" action="<?= url('/administracao/email/salvar') ?>" id="formEmail">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Servidor SMTP</label>
                    <input type="text" name="host" class="form-control" required
                           value="<?= htmlspecialchars($host) ?>" placeholder="smtp.gmail.com">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Porta</label>
                    <input type="number" name="porta" class="form-control" required value="<?= (int)$porta ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Usuário</label>
                    <input type="text" name="usuario" class="form-control" required
                           value="<?= htmlspecialchars($usuario) ?>" placeholder="backup@suaempresa.com.br">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Senha</label>
                    <input type="password" name="senha" class="form-control"
                           placeholder="<?= $configurado ? '•••••••• (deixe em branco para manter)' : 'senha ou senha de app' ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Criptografia</label>
                    <select name="criptografia" class="form-select">
                        <option value="tls" <?= $criptografia === 'tls' ? 'selected' : '' ?>>STARTTLS</option>
                        <option value="ssl" <?= $criptografia === 'ssl' ? 'selected' : '' ?>>SSL/TLS</option>
                        <option value="nenhuma" <?= $criptografia === 'nenhuma' ? 'selected' : '' ?>>Nenhuma</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nome do remetente</label>
                    <input type="text" name="remetente_nome" class="form-control"
                           value="<?= htmlspecialchars($remetenteNome) ?>" placeholder="RD Intranet">
                </div>
                <div class="col-md-4">
                    <label class="form-label">E-mail do remetente</label>
                    <input type="email" name="remetente_email" class="form-control" required
                           value="<?= htmlspecialchars($remetenteEmail) ?>" placeholder="backup@suaempresa.com.br">
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-4">
                <div>
                    <?= $configurado
                        ? '<span class="badge text-bg-success">Configurado</span>'
                        : '<span class="badge text-bg-secondary">Não configurado</span>' ?>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" id="botaoTestarEmail" <?= $configurado ? '' : 'disabled title="Salve a configuração antes de testar"' ?>>
                        <i class="bi bi-send"></i> Enviar e-mail de teste
                    </button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
                </div>
            </div>
        </form>

        <div class="alert alert-info small mt-3 mb-0" id="resultadoTeste" style="display:none"></div>
    </div>
</div>

<script>
(function () {
    const botao = document.getElementById('botaoTestarEmail');
    if (!botao) return;

    botao.addEventListener('click', async function () {
        const paraEmail = prompt('Enviar e-mail de teste para qual endereço?', <?= json_encode($remetenteEmail) ?>);
        if (!paraEmail) return;

        const resultadoBox = document.getElementById('resultadoTeste');
        botao.disabled = true;
        resultadoBox.style.display = '';
        resultadoBox.className = 'alert alert-info small mt-3 mb-0';
        resultadoBox.textContent = 'Enviando...';

        try {
            const dados = new URLSearchParams();
            dados.set('para', paraEmail);

            const res = await fetch(<?= json_encode(url('/administracao/email/testar')) ?>, { method: 'POST', body: dados });
            const resposta = await res.json();

            resultadoBox.className = 'alert small mt-3 mb-0 ' + (resposta.success ? 'alert-success' : 'alert-danger');
            resultadoBox.textContent = resposta.message;
        } catch (e) {
            resultadoBox.className = 'alert alert-danger small mt-3 mb-0';
            resultadoBox.textContent = 'Erro de rede ao testar o e-mail.';
        } finally {
            botao.disabled = false;
        }
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Sistema - E-mail';

require __DIR__ . '/../layouts/main.php';
