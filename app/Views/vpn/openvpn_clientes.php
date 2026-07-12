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
        <h4 class="mb-1"><i class="bi bi-people me-1"></i> OpenVPN - Clientes</h4>
        <small class="text-muted"><a href="<?= url('/vpn') ?>"><i class="bi bi-arrow-left"></i> Dashboard VPN</a></small>
    </div>
    <?php if ($instalado && $pkiOk): ?>
    <button type="button" class="btn btn-primary" id="botaoNovoCliente">
        <i class="bi bi-plus-lg"></i> Novo cliente
    </button>
    <?php endif; ?>
</div>

<?php if (!$instalado || !$pkiOk): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i> Instale o OpenVPN e inicialize a PKI primeiro em
        <a href="<?= url('/vpn/openvpn/servidor') ?>">VPN &gt; OpenVPN &gt; Servidor</a>.
    </div>
<?php else: ?>

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
                        <th>Nome</th>
                        <th>Status</th>
                        <th>Endereço real</th>
                        <th>Conectado desde</th>
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
                            <td class="small"><?= $c['conectado_desde'] ? htmlspecialchars(data_br($c['conectado_desde'])) : '—' ?></td>
                            <td><?= (int)$c['config_entregue'] === 1 ? Badge::make('Sim', 'success') : Badge::make('Não', 'warning') ?></td>
                            <td class="text-end">
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary botao-baixar" data-id="<?= (int)$c['id'] ?>" data-nome="<?= htmlspecialchars($c['nome']) ?>" title="Baixar config novamente">
                                        <i class="bi bi-download"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger botao-revogar" data-id="<?= (int)$c['id'] ?>" data-nome="<?= htmlspecialchars($c['nome']) ?>" title="Revogar">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                </div>
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
                <label class="form-label">Nome</label>
                <input type="text" class="form-control" id="campoNomeCliente" placeholder="Ex: notebook-joao">
                <div class="form-text">Só letras, números, "-" ou "_" (vira o Common Name do certificado).</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="botaoConfirmarNovoCliente">Criar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalConfigCliente" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Config do cliente (.ovpn)</h5>
            </div>
            <div class="modal-body" id="corpoConfigCliente"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="botaoFecharConfigCliente" data-bs-dismiss="modal">
                    Fechar
                </button>
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
        novoCliente: <?= json_encode(url('/vpn/openvpn/clientes/novo')) ?>,
        baixar: <?= json_encode(url('/vpn/openvpn/clientes/baixar')) ?>,
        marcarEntregue: <?= json_encode(url('/vpn/openvpn/clientes/entregue')) ?>,
        revogar: <?= json_encode(url('/vpn/openvpn/clientes/revogar')) ?>,
    };
    let clienteIdPendente = null;

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

    function mostrarConfig(configTexto, nomeArquivo) {
        const corpo = document.getElementById('corpoConfigCliente');
        corpo.innerHTML =
            '<label class="form-label">Arquivo de configuração (.ovpn)</label>' +
            '<textarea class="form-control font-monospace" rows="14" readonly id="textoConfigCliente">' + configTexto.replace(/</g, '&lt;') + '</textarea>' +
            '<div class="d-flex gap-2 mt-2">' +
            '<button type="button" class="btn btn-sm btn-outline-secondary" id="botaoCopiarConfig"><i class="bi bi-clipboard"></i> Copiar</button>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary" id="botaoBaixarConfig"><i class="bi bi-download"></i> Baixar .ovpn</button>' +
            '</div>';

        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalConfigCliente')).show();

        document.getElementById('botaoCopiarConfig').addEventListener('click', function () {
            navigator.clipboard.writeText(configTexto);
        });
        document.getElementById('botaoBaixarConfig').addEventListener('click', function () {
            const blob = new Blob([configTexto], { type: 'text/plain' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = nomeArquivo + '.ovpn';
            a.click();
        });
    }

    const botaoNovoCliente = document.getElementById('botaoNovoCliente');
    if (botaoNovoCliente) {
        botaoNovoCliente.addEventListener('click', function () {
            document.getElementById('campoNomeCliente').value = '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNovoCliente')).show();
        });
    }

    const botaoConfirmarNovoCliente = document.getElementById('botaoConfirmarNovoCliente');
    if (botaoConfirmarNovoCliente) {
        botaoConfirmarNovoCliente.addEventListener('click', async function () {
            const nome = document.getElementById('campoNomeCliente').value.trim();
            if (!nome) { alert('Informe um nome.'); return; }

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNovoCliente')).hide();

            const dados = new URLSearchParams();
            dados.set('nome', nome);

            try {
                const res = await fetch(URLS.novoCliente, { method: 'POST', body: dados });
                const resultado = await res.json();

                if (!resultado.success) {
                    alert(resultado.message || 'Falha ao criar cliente.');
                    return;
                }

                clienteIdPendente = resultado.cliente_id;
                mostrarConfig(resultado.config_texto, nome);

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

    document.querySelectorAll('.botao-baixar').forEach(function (botao) {
        botao.addEventListener('click', async function () {
            const dados = new URLSearchParams();
            dados.set('id', botao.dataset.id);
            try {
                const res = await fetch(URLS.baixar, { method: 'POST', body: dados });
                const resultado = await res.json();
                if (!resultado.success) {
                    alert(resultado.message || 'Falha ao baixar.');
                    return;
                }
                mostrarConfig(resultado.config_texto, botao.dataset.nome);
            } catch (e) {
                alert('Erro ao comunicar com o servidor.');
            }
        });
    });

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
$titulo = 'VPN - OpenVPN - Clientes';

require __DIR__ . '/../layouts/main.php';
