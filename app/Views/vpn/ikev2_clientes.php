<?php
ob_start();

use App\Components\Alert;
use App\Components\Badge;

$config = $status['config'];
$instalado = (bool)($config['instalado'] ?? false);
$pkiOk = (bool)($config['pki_inicializada'] ?? false);
?>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-people me-1"></i> IKEv2/IPsec - Clientes</h4>
        <small class="text-muted"><a href="<?= url('/vpn') ?>"><i class="bi bi-arrow-left"></i> Dashboard VPN</a></small>
    </div>
    <?php if ($instalado && $pkiOk): ?>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary" id="botaoBaixarCa">
            <i class="bi bi-download"></i> Certificado da CA
        </button>
        <button type="button" class="btn btn-primary" id="botaoNovoCliente">
            <i class="bi bi-plus-lg"></i> Novo cliente
        </button>
    </div>
    <?php endif; ?>
</div>

<?php if (!$instalado || !$pkiOk): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i> Instale o strongSwan e inicialize a PKI primeiro em
        <a href="<?= url('/vpn/ikev2/servidor') ?>">VPN &gt; IKEv2 &gt; Servidor</a>.
    </div>
<?php else: ?>

<div class="alert alert-info small">
    <i class="bi bi-info-circle"></i> O IKEv2 não usa arquivo de configuração — o cliente é configurado direto no
    app de VPN nativo do sistema (iOS/Android/Windows), com <strong>usuário</strong>, <strong>senha</strong> e o
    <strong>endereço público</strong> do servidor. Se o dispositivo não confiar automaticamente na CA, baixe o
    certificado acima e instale no dispositivo antes.
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($status['clientes'])): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-people display-6"></i>
                <p class="mt-2 mb-0">Nenhum cliente cadastrado ainda.</p>
            </div>
        <?php else: ?>
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Status</th>
                        <th>Endereço real</th>
                        <th>Config entregue</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($status['clientes'] as $c): ?>
                        <?php if ((int)$c['ativo'] !== 1) continue; ?>
                        <tr>
                            <td><?= htmlspecialchars($c['nome']) ?></td>
                            <td><?= $c['online'] ? Badge::make('Online', 'success') : Badge::make('Offline', 'secondary') ?></td>
                            <td class="font-monospace small"><?= htmlspecialchars($c['endereco_real'] ?? '—') ?></td>
                            <td><?= (int)$c['config_entregue'] === 1 ? Badge::make('Sim', 'success') : Badge::make('Não', 'warning') ?></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger botao-revogar" data-id="<?= (int)$c['id'] ?>" data-nome="<?= htmlspecialchars($c['nome']) ?>">
                                    <i class="bi bi-x-circle"></i> Revogar
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<div class="modal fade" id="modalNovoCliente" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo cliente</h5>
            </div>
            <div class="modal-body">
                <label class="form-label">Usuário</label>
                <input type="text" class="form-control mb-3" id="campoNomeCliente" placeholder="Ex: joao">
                <label class="form-label">Senha</label>
                <input type="text" class="form-control" id="campoSenhaCliente" placeholder="Deixe em branco para gerar automaticamente">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="botaoConfirmarNovoCliente">Criar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalConfigCliente" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Dados do cliente</h5>
            </div>
            <div class="modal-body" id="corpoConfigCliente"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="botaoFecharConfigCliente" data-bs-dismiss="modal">
                    Já anotei — fechar
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCa" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Certificado da CA</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <textarea class="form-control font-monospace" rows="14" readonly id="textoCa"></textarea>
                <div class="d-flex gap-2 mt-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="botaoBaixarCaArquivo"><i class="bi bi-download"></i> Baixar .pem</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAcao" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAcaoTitulo">Processando</h5>
            </div>
            <div class="modal-body" id="modalAcaoCorpo">
                <div class="text-center text-muted py-3"><i class="bi bi-hourglass-split"></i> Aguarde...</div>
            </div>
            <div class="modal-footer" id="modalAcaoRodape" style="display:none">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="location.reload()">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const URLS = {
        novoCliente: <?= json_encode(url('/vpn/ikev2/clientes/novo')) ?>,
        marcarEntregue: <?= json_encode(url('/vpn/ikev2/clientes/entregue')) ?>,
        revogar: <?= json_encode(url('/vpn/ikev2/clientes/revogar')) ?>,
        baixarCa: <?= json_encode(url('/vpn/ikev2/ca')) ?>,
    };
    let clienteIdPendente = null;
    const endpoint = <?= json_encode($config['endpoint_publico'] ?? '') ?>;

    async function executar(url, titulo, corpoPost) {
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAcao'));
        const corpo = document.getElementById('modalAcaoCorpo');
        const rodape = document.getElementById('modalAcaoRodape');

        document.getElementById('modalAcaoTitulo').textContent = titulo;
        corpo.innerHTML = '<div class="text-center text-muted py-3"><i class="bi bi-hourglass-split"></i> Aguarde...</div>';
        rodape.style.display = 'none';
        modal.show();

        try {
            const res = await fetch(url, { method: 'POST', body: corpoPost });
            const dados = await res.json();

            const cor = dados.success ? 'success' : 'danger';
            const icone = dados.success ? 'check-circle' : 'x-circle';
            corpo.innerHTML = '<div class="alert alert-' + cor + '"><i class="bi bi-' + icone + '"></i> ' +
                String(dados.message || '').replace(/</g, '&lt;') + '</div>';
        } catch (e) {
            corpo.innerHTML = '<div class="alert alert-danger mb-0">Erro ao comunicar com o servidor.</div>';
        } finally {
            rodape.style.display = '';
        }
    }

    const botaoNovoCliente = document.getElementById('botaoNovoCliente');
    if (botaoNovoCliente) {
        botaoNovoCliente.addEventListener('click', function () {
            document.getElementById('campoNomeCliente').value = '';
            document.getElementById('campoSenhaCliente').value = '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNovoCliente')).show();
        });
    }

    const botaoConfirmarNovoCliente = document.getElementById('botaoConfirmarNovoCliente');
    if (botaoConfirmarNovoCliente) {
        botaoConfirmarNovoCliente.addEventListener('click', async function () {
            const nome = document.getElementById('campoNomeCliente').value.trim();
            if (!nome) { alert('Informe um usuário.'); return; }

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNovoCliente')).hide();

            const dados = new URLSearchParams();
            dados.set('nome', nome);
            dados.set('senha', document.getElementById('campoSenhaCliente').value);

            try {
                const res = await fetch(URLS.novoCliente, { method: 'POST', body: dados });
                const resultado = await res.json();

                if (!resultado.success) {
                    alert(resultado.message || 'Falha ao criar cliente.');
                    return;
                }

                clienteIdPendente = resultado.cliente_id;

                const corpo = document.getElementById('corpoConfigCliente');
                corpo.innerHTML =
                    '<div class="alert alert-warning small"><i class="bi bi-exclamation-triangle"></i> A senha só aparece esta vez — anote agora.</div>' +
                    '<label class="form-label small">Servidor</label>' +
                    '<input type="text" class="form-control mb-2" readonly value="' + endpoint + '">' +
                    '<label class="form-label small">Usuário</label>' +
                    '<input type="text" class="form-control mb-2" readonly value="' + resultado.usuario + '">' +
                    '<label class="form-label small">Senha</label>' +
                    '<input type="text" class="form-control" readonly value="' + resultado.senha + '">';

                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalConfigCliente')).show();

                const dadosEntregue = new URLSearchParams();
                dadosEntregue.set('id', clienteIdPendente);
                await fetch(URLS.marcarEntregue, { method: 'POST', body: dadosEntregue });
            } catch (e) {
                alert('Erro ao comunicar com o servidor.');
            }
        });
    }

    document.getElementById('botaoFecharConfigCliente').addEventListener('click', function () {
        location.reload();
    });

    const botaoBaixarCa = document.getElementById('botaoBaixarCa');
    if (botaoBaixarCa) {
        botaoBaixarCa.addEventListener('click', async function () {
            try {
                const res = await fetch(URLS.baixarCa);
                const dados = await res.json();
                if (!dados.success) {
                    alert(dados.message || 'Falha ao obter o certificado.');
                    return;
                }
                document.getElementById('textoCa').value = dados.ca;
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCa')).show();

                document.getElementById('botaoBaixarCaArquivo').onclick = function () {
                    const blob = new Blob([dados.ca], { type: 'application/x-pem-file' });
                    const a = document.createElement('a');
                    a.href = URL.createObjectURL(blob);
                    a.download = 'rd-intranet-ca.pem';
                    a.click();
                };
            } catch (e) {
                alert('Erro ao comunicar com o servidor.');
            }
        });
    }

    document.querySelectorAll('.botao-revogar').forEach(function (botao) {
        botao.addEventListener('click', function () {
            if (!confirm('Revogar o cliente "' + botao.dataset.nome + '"? Ele perde acesso imediatamente.')) return;

            const dados = new URLSearchParams();
            dados.set('id', botao.dataset.id);
            executar(URLS.revogar, 'Revogando cliente', dados);
        });
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'VPN - IKEv2 - Clientes';

require __DIR__ . '/../layouts/main.php';
