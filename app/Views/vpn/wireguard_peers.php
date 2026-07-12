<?php
ob_start();

use App\Components\Alert;
use App\Components\Badge;

$config = $status['config'];
$instalado = (bool)($config['instalado'] ?? false);
?>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-people me-1"></i> WireGuard - Peers</h4>
        <small class="text-muted"><a href="<?= url('/vpn') ?>"><i class="bi bi-arrow-left"></i> Dashboard VPN</a></small>
    </div>
    <?php if ($instalado): ?>
    <button type="button" class="btn btn-primary" id="botaoNovoPeer" <?= empty($config['chave_privada']) ? 'disabled' : '' ?>>
        <i class="bi bi-plus-lg"></i> Novo peer
    </button>
    <?php endif; ?>
</div>

<?php if (!$instalado): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i> Instale e configure o WireGuard primeiro em
        <a href="<?= url('/vpn/wireguard/servidor') ?>">VPN &gt; WireGuard &gt; Servidor</a>.
    </div>
<?php elseif (empty($config['chave_privada'])): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i> Salve a configuração do servidor primeiro em
        <a href="<?= url('/vpn/wireguard/servidor') ?>">VPN &gt; WireGuard &gt; Servidor</a>.
    </div>
<?php else: ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($status['peers'])): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-people display-6"></i>
                <p class="mt-2 mb-0">Nenhum peer cadastrado ainda.</p>
            </div>
        <?php else: ?>
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>IP</th>
                        <th>Status</th>
                        <th>Último handshake</th>
                        <th>Config entregue</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($status['peers'] as $p): ?>
                        <?php if ((int)$p['ativo'] !== 1) continue; ?>
                        <tr>
                            <td><?= htmlspecialchars($p['nome']) ?></td>
                            <td class="font-monospace small"><?= htmlspecialchars($p['ip_atribuido']) ?></td>
                            <td><?= $p['online'] ? Badge::make('Online', 'success') : Badge::make('Offline', 'secondary') ?></td>
                            <td class="small"><?= $p['ultimo_handshake'] ? htmlspecialchars(data_br($p['ultimo_handshake'])) : '—' ?></td>
                            <td><?= (int)$p['config_entregue'] === 1 ? Badge::make('Sim', 'success') : Badge::make('Não', 'warning') ?></td>
                            <td class="text-end">
                                <form method="post" action="<?= url('/vpn/wireguard/peers/revogar') ?>" class="d-inline"
                                      onsubmit="return confirm('Revogar o peer \'<?= htmlspecialchars(addslashes($p['nome'])) ?>\'? Ele perde acesso imediatamente.');">
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                    <button type="button" class="btn btn-sm btn-outline-danger botao-revogar" data-id="<?= (int)$p['id'] ?>" data-nome="<?= htmlspecialchars($p['nome']) ?>">
                                        <i class="bi bi-x-circle"></i> Revogar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<div class="modal fade" id="modalNovoPeer" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo peer</h5>
            </div>
            <div class="modal-body">
                <label class="form-label">Nome</label>
                <input type="text" class="form-control" id="campoNomePeer" placeholder="Ex: Notebook do João">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="botaoConfirmarNovoPeer">Criar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalConfigPeer" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Config do peer</h5>
            </div>
            <div class="modal-body" id="corpoConfigPeer"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="botaoFecharConfigPeer" data-bs-dismiss="modal">
                    Já salvei/entreguei esta config — fechar
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
        novoPeer: <?= json_encode(url('/vpn/wireguard/peers/novo')) ?>,
        marcarEntregue: <?= json_encode(url('/vpn/wireguard/peers/entregue')) ?>,
        revogar: <?= json_encode(url('/vpn/wireguard/peers/revogar')) ?>,
    };
    let peerIdPendente = null;

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

    const botaoNovoPeer = document.getElementById('botaoNovoPeer');
    if (botaoNovoPeer) {
        botaoNovoPeer.addEventListener('click', function () {
            document.getElementById('campoNomePeer').value = '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNovoPeer')).show();
        });
    }

    const botaoConfirmarNovoPeer = document.getElementById('botaoConfirmarNovoPeer');
    if (botaoConfirmarNovoPeer) {
        botaoConfirmarNovoPeer.addEventListener('click', async function () {
            const nome = document.getElementById('campoNomePeer').value.trim();
            if (!nome) { alert('Informe um nome.'); return; }

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNovoPeer')).hide();

            const dados = new URLSearchParams();
            dados.set('nome', nome);

            try {
                const res = await fetch(URLS.novoPeer, { method: 'POST', body: dados });
                const resultado = await res.json();

                if (!resultado.success) {
                    alert(resultado.message || 'Falha ao criar peer.');
                    return;
                }

                peerIdPendente = resultado.peer_id;

                const corpo = document.getElementById('corpoConfigPeer');
                corpo.innerHTML =
                    '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> ' +
                    'Esta é a única vez que esta configuração (com a chave privada) aparece. Salve agora ou escaneie o QR code.</div>' +
                    '<div class="text-center mb-3">' +
                    (resultado.qr_base64 ? '<img src="data:image/png;base64,' + resultado.qr_base64 + '" style="max-width:260px" alt="QR code do peer">' : '<div class="text-muted">QR code indisponível (qrencode não instalado?)</div>') +
                    '</div>' +
                    '<label class="form-label">Arquivo de configuração (.conf)</label>' +
                    '<textarea class="form-control font-monospace" rows="10" readonly id="textoConfigPeer">' + resultado.config_texto.replace(/</g, '&lt;') + '</textarea>' +
                    '<div class="d-flex gap-2 mt-2">' +
                    '<button type="button" class="btn btn-sm btn-outline-secondary" id="botaoCopiarConfig"><i class="bi bi-clipboard"></i> Copiar</button>' +
                    '<button type="button" class="btn btn-sm btn-outline-secondary" id="botaoBaixarConfig"><i class="bi bi-download"></i> Baixar .conf</button>' +
                    '</div>';

                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalConfigPeer')).show();

                document.getElementById('botaoCopiarConfig').addEventListener('click', function () {
                    navigator.clipboard.writeText(resultado.config_texto);
                });
                document.getElementById('botaoBaixarConfig').addEventListener('click', function () {
                    const blob = new Blob([resultado.config_texto], { type: 'text/plain' });
                    const a = document.createElement('a');
                    a.href = URL.createObjectURL(blob);
                    a.download = 'peer.conf';
                    a.click();
                });
            } catch (e) {
                alert('Erro ao comunicar com o servidor.');
            }
        });
    }

    const botaoFecharConfigPeer = document.getElementById('botaoFecharConfigPeer');
    if (botaoFecharConfigPeer) {
        botaoFecharConfigPeer.addEventListener('click', async function () {
            if (peerIdPendente) {
                const dados = new URLSearchParams();
                dados.set('id', peerIdPendente);
                await fetch(URLS.marcarEntregue, { method: 'POST', body: dados });
            }
            location.reload();
        });
    }

    document.querySelectorAll('.botao-revogar').forEach(function (botao) {
        botao.addEventListener('click', function () {
            if (!confirm('Revogar o peer "' + botao.dataset.nome + '"? Ele perde acesso imediatamente.')) return;

            const dados = new URLSearchParams();
            dados.set('id', botao.dataset.id);
            executar(URLS.revogar, 'Revogando peer', dados);
        });
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'VPN - WireGuard - Peers';

require __DIR__ . '/../layouts/main.php';
