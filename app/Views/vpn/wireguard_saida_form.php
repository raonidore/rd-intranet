<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-box-arrow-up-right me-1"></i> Nova conexão de saída (WireGuard)</h4>
    <small class="text-muted"><a href="<?= url('/vpn/wireguard/saida') ?>"><i class="bi bi-arrow-left"></i> Voltar</a></small>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <strong><i class="bi bi-key"></i> Não tem uma config pronta ainda?</strong>
    </div>
    <div class="card-body">
        <p class="small text-muted">
            Se o administrador do servidor remoto pediu <em>sua</em> chave pública para cadastrar este servidor como peer,
            gere um par de chaves aqui. A chave privada aparece só uma vez — cole ela no campo <code>PrivateKey</code> abaixo
            e mande a <strong>chave pública</strong> para o administrador remoto (junto com o IP que ele quer atribuir a este servidor).
        </p>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="botaoGerarChave">
            <i class="bi bi-key"></i> Gerar par de chaves
        </button>
        <div id="resultadoChave" class="mt-3" style="display:none"></div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="alert alert-info small">
            <i class="bi bi-info-circle"></i> Cole aqui o conteúdo completo do arquivo <code>.conf</code> do WireGuard —
            já pronto (fornecido pelo administrador remoto) ou montado por você com a chave gerada acima + os dados do
            peer remoto (<code>PublicKey</code>, <code>Endpoint</code>, <code>AllowedIPs</code> do lado deles).
        </div>
        <form id="formNovaConexao">
            <div class="mb-3">
                <label class="form-label">Nome da interface</label>
                <input type="text" name="nome" id="campoNome" class="form-control" required maxlength="15" placeholder="Ex: wg-matriz" style="max-width:320px">
                <div class="form-text">Até 15 caracteres, letras/números/"-"/"_". Precisa ser diferente da interface do modo servidor.</div>
            </div>
            <div class="mb-3">
                <label class="form-label">Conteúdo do arquivo .conf</label>
                <textarea name="conteudo_conf" id="campoConteudo" class="form-control font-monospace" rows="12" required
                          placeholder="[Interface]&#10;PrivateKey = ...&#10;Address = 10.0.0.2/32&#10;&#10;[Peer]&#10;PublicKey = ...&#10;Endpoint = vpn.exemplo.com:51820&#10;AllowedIPs = 0.0.0.0/0"></textarea>
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
                <a href="<?= url('/vpn/wireguard/saida') ?>" class="btn btn-primary" id="botaoVoltarLista" style="display:none">Voltar para a lista</a>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    document.getElementById('botaoGerarChave').addEventListener('click', async function () {
        const botao = this;
        botao.disabled = true;
        try {
            const res = await fetch(<?= json_encode(url('/vpn/wireguard/saida/gerar-chave')) ?>, { method: 'POST' });
            const dados = await res.json();

            const div = document.getElementById('resultadoChave');
            div.style.display = '';

            if (!dados.success) {
                div.innerHTML = '<div class="alert alert-danger mb-0">' + (dados.message || 'Falha ao gerar.') + '</div>';
                return;
            }

            div.innerHTML =
                '<div class="alert alert-warning small mb-2"><i class="bi bi-exclamation-triangle"></i> A chave privada não fica salva em nenhum lugar — copie agora.</div>' +
                '<label class="form-label small">Chave privada (cole no campo PrivateKey do .conf abaixo)</label>' +
                '<input type="text" class="form-control font-monospace mb-2" readonly value="' + dados.chave_privada + '">' +
                '<label class="form-label small">Chave pública (envie para o administrador remoto)</label>' +
                '<input type="text" class="form-control font-monospace" readonly value="' + dados.chave_publica + '">';
        } catch (e) {
            alert('Erro ao comunicar com o servidor.');
        } finally {
            botao.disabled = false;
        }
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

        const dados = new URLSearchParams();
        dados.set('nome', document.getElementById('campoNome').value.trim());
        dados.set('conteudo_conf', document.getElementById('campoConteudo').value);

        try {
            const res = await fetch(<?= json_encode(url('/vpn/wireguard/saida/novo')) ?>, { method: 'POST', body: dados });
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
$titulo = 'VPN - WireGuard - Nova Conexão de Saída';

require __DIR__ . '/../layouts/main.php';
