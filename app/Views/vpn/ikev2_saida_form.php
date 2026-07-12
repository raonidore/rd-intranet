<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-box-arrow-up-right me-1"></i> Nova conexão de saída (IKEv2)</h4>
    <small class="text-muted"><a href="<?= url('/vpn/ikev2/saida') ?>"><i class="bi bi-arrow-left"></i> Voltar</a></small>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form id="formNovaConexao">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Nome</label>
                    <input type="text" name="nome" id="campoNome" class="form-control" required placeholder="Ex: matriz-sp">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Servidor remoto (host/IP)</label>
                    <input type="text" name="servidor_remoto" id="campoServidor" class="form-control" required placeholder="vpn.exemplo.com">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Subnet remota</label>
                    <input type="text" name="subnet_remota" id="campoSubnet" class="form-control" value="0.0.0.0/0">
                    <div class="form-text">Rede acessível do outro lado do túnel.</div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Tipo de autenticação</label>
                <select name="tipo_auth" id="campoTipoAuth" class="form-select" style="max-width:320px">
                    <option value="psk">PSK (chave pré-compartilhada)</option>
                    <option value="eap">EAP usuário/senha (o servidor remoto também é um IKEv2 EAP)</option>
                </select>
            </div>

            <div id="camposPsk" class="mb-3">
                <label class="form-label">Chave pré-compartilhada (PSK)</label>
                <input type="text" name="psk" class="form-control font-monospace" style="max-width:480px">
            </div>

            <div id="camposEap" class="mb-3" style="display:none">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Usuário</label>
                        <input type="text" name="usuario_eap" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Senha</label>
                        <input type="text" name="senha" class="form-control">
                    </div>
                </div>
                <div class="mt-3">
                    <label class="form-label">Certificado da CA do servidor remoto</label>
                    <textarea name="ca_remota" class="form-control font-monospace" rows="8" placeholder="-----BEGIN CERTIFICATE-----&#10;...&#10;-----END CERTIFICATE-----"></textarea>
                    <div class="form-text">Necessário pra este servidor confiar na identidade do servidor remoto durante a autenticação EAP. Peça ao administrador remoto.</div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" id="botaoSalvar">
                <i class="bi bi-check-lg"></i> Salvar
            </button>
        </form>
    </div>
</div>

<div class="modal fade" id="modalAcao" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Processando</h5>
            </div>
            <div class="modal-body" id="modalAcaoCorpo">
                <div class="text-center text-muted py-3"><i class="bi bi-hourglass-split"></i> Aguarde...</div>
            </div>
            <div class="modal-footer" id="modalAcaoRodape" style="display:none">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="location.reload()">Fechar</button>
                <a href="<?= url('/vpn/ikev2/saida') ?>" class="btn btn-primary" id="botaoVoltarLista" style="display:none">Voltar para a lista</a>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const tipoAuth = document.getElementById('campoTipoAuth');
    const camposPsk = document.getElementById('camposPsk');
    const camposEap = document.getElementById('camposEap');

    tipoAuth.addEventListener('change', function () {
        const ehEap = tipoAuth.value === 'eap';
        camposPsk.style.display = ehEap ? 'none' : '';
        camposEap.style.display = ehEap ? '' : 'none';
    });

    document.getElementById('formNovaConexao').addEventListener('submit', async function (e) {
        e.preventDefault();

        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAcao'));
        const corpo = document.getElementById('modalAcaoCorpo');
        const rodape = document.getElementById('modalAcaoRodape');
        const botaoVoltar = document.getElementById('botaoVoltarLista');

        corpo.innerHTML = '<div class="text-center text-muted py-3"><i class="bi bi-hourglass-split"></i> Aguarde...</div>';
        rodape.style.display = 'none';
        botaoVoltar.style.display = 'none';
        modal.show();

        const dados = new URLSearchParams(new FormData(e.target));

        try {
            const res = await fetch(<?= json_encode(url('/vpn/ikev2/saida/novo')) ?>, { method: 'POST', body: dados });
            const resultado = await res.json();

            const cor = resultado.success ? 'success' : 'danger';
            const icone = resultado.success ? 'check-circle' : 'x-circle';
            corpo.innerHTML = '<div class="alert alert-' + cor + '"><i class="bi bi-' + icone + '"></i> ' +
                String(resultado.message || '').replace(/</g, '&lt;') + '</div>';

            if (resultado.success) {
                botaoVoltar.style.display = '';
            }
        } catch (e) {
            corpo.innerHTML = '<div class="alert alert-danger mb-0">Erro ao comunicar com o servidor.</div>';
        } finally {
            rodape.style.display = '';
        }
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'VPN - IKEv2 - Nova Conexão de Saída';

require __DIR__ . '/../layouts/main.php';
