<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-box-arrow-up-right me-1"></i> Nova conexão de saída</h4>
    <small class="text-muted"><a href="<?= url('/vpn/openvpn/saida') ?>"><i class="bi bi-arrow-left"></i> Voltar</a></small>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="alert alert-info small">
            <i class="bi bi-info-circle"></i> Cole aqui o conteúdo completo do arquivo <code>.ovpn</code> fornecido por quem
            administra o servidor remoto (matriz, provedor de VPN, etc). O arquivo é guardado criptografado.
        </div>
        <form id="formNovaConexao">
            <div class="mb-3">
                <label class="form-label">Nome</label>
                <input type="text" name="nome" id="campoNome" class="form-control" required placeholder="Ex: matriz-sp" style="max-width:320px">
                <div class="form-text">Só letras, números, "-" ou "_".</div>
            </div>
            <div class="mb-3">
                <label class="form-label">Conteúdo do arquivo .ovpn</label>
                <textarea name="conteudo_ovpn" id="campoConteudo" class="form-control font-monospace" rows="16" required
                          placeholder="client&#10;dev tun&#10;proto udp&#10;remote vpn.exemplo.com 1194&#10;...&#10;&lt;ca&gt;&#10;...&#10;&lt;/ca&gt;"></textarea>
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
                <a href="<?= url('/vpn/openvpn/saida') ?>" class="btn btn-primary" id="botaoVoltarLista" style="display:none">Voltar para a lista</a>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
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

        const dados = new URLSearchParams();
        dados.set('nome', document.getElementById('campoNome').value.trim());
        dados.set('conteudo_ovpn', document.getElementById('campoConteudo').value);

        try {
            const res = await fetch(<?= json_encode(url('/vpn/openvpn/saida/novo')) ?>, { method: 'POST', body: dados });
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
$titulo = 'VPN - OpenVPN - Nova Conexão de Saída';

require __DIR__ . '/../layouts/main.php';
