<?php
ob_start();

use App\Components\Alert;
use App\Components\Badge;
?>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-box-arrow-up-right me-1"></i> WireGuard - Conexões de Saída</h4>
        <small class="text-muted">
            <a href="<?= url('/vpn') ?>"><i class="bi bi-arrow-left"></i> Dashboard VPN</a> ·
            Este servidor conectando como <strong>peer</strong> a um WireGuard existente (outra empresa, provedor, outro servidor).
        </small>
    </div>
    <a href="<?= url('/vpn/wireguard/saida/novo') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Nova conexão
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($conexoes)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-box-arrow-up-right display-6"></i>
                <p class="mt-2 mb-0">Nenhuma conexão de saída configurada.</p>
            </div>
        <?php else: ?>
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nome (interface)</th>
                        <th>Status</th>
                        <th>Ativa no boot</th>
                        <th>Criada em</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($conexoes as $c): ?>
                        <tr>
                            <td class="font-monospace"><?= htmlspecialchars($c['nome']) ?></td>
                            <td><?= $c['ativo'] ? Badge::make('Conectado', 'success') : Badge::make('Desconectado', 'secondary') ?></td>
                            <td>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input botao-toggle-boot" type="checkbox" role="switch"
                                           data-id="<?= (int)$c['id'] ?>" <?= (int)$c['ativo_no_boot'] === 1 ? 'checked' : '' ?>>
                                </div>
                            </td>
                            <td class="small"><?= htmlspecialchars(data_br($c['criado_em'])) ?></td>
                            <td class="text-end">
                                <div class="btn-group" role="group">
                                    <?php if ($c['ativo']): ?>
                                        <button type="button" class="btn btn-sm btn-outline-warning botao-desconectar" data-id="<?= (int)$c['id'] ?>">
                                            <i class="bi bi-plug"></i> Desconectar
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-outline-success botao-conectar" data-id="<?= (int)$c['id'] ?>">
                                            <i class="bi bi-play-fill"></i> Conectar
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger botao-remover" data-id="<?= (int)$c['id'] ?>" data-nome="<?= htmlspecialchars($c['nome']) ?>">
                                        <i class="bi bi-trash"></i>
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
        conectar: <?= json_encode(url('/vpn/wireguard/saida/conectar')) ?>,
        desconectar: <?= json_encode(url('/vpn/wireguard/saida/desconectar')) ?>,
        boot: <?= json_encode(url('/vpn/wireguard/saida/boot')) ?>,
        remover: <?= json_encode(url('/vpn/wireguard/saida/remover')) ?>,
    };

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

    document.querySelectorAll('.botao-conectar').forEach(function (botao) {
        botao.addEventListener('click', function () {
            const dados = new URLSearchParams();
            dados.set('id', botao.dataset.id);
            executar(URLS.conectar, 'Conectando', dados);
        });
    });

    document.querySelectorAll('.botao-desconectar').forEach(function (botao) {
        botao.addEventListener('click', function () {
            const dados = new URLSearchParams();
            dados.set('id', botao.dataset.id);
            executar(URLS.desconectar, 'Desconectando', dados);
        });
    });

    document.querySelectorAll('.botao-toggle-boot').forEach(function (checkbox) {
        checkbox.addEventListener('change', async function () {
            const dados = new URLSearchParams();
            dados.set('id', checkbox.dataset.id);
            dados.set('ativo', checkbox.checked ? '1' : '0');
            try {
                await fetch(URLS.boot, { method: 'POST', body: dados });
            } catch (e) {}
        });
    });

    document.querySelectorAll('.botao-remover').forEach(function (botao) {
        botao.addEventListener('click', function () {
            if (!confirm('Remover a conexão "' + botao.dataset.nome + '"?')) return;

            const dados = new URLSearchParams();
            dados.set('id', botao.dataset.id);
            executar(URLS.remover, 'Removendo conexão', dados);
        });
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'VPN - WireGuard - Conexões de Saída';

require __DIR__ . '/../layouts/main.php';
