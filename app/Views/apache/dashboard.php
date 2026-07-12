<?php

use App\Components\Alert;

ob_start();

$servicoOk = $status['servico_status'] === 'active';
$configOk = $status['configtest'] === 'OK';
?>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm mb-4" style="background:linear-gradient(135deg,#111827,#1f2937);color:#fff;">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h4 class="mb-1"><i class="bi bi-server"></i> Apache</h4>
            <small class="text-white-50"><?= htmlspecialchars($status['versao']) ?></small>
        </div>
        <div class="text-end">
            <span class="badge bg-<?= $servicoOk ? 'success' : 'danger' ?>">
                <?= $servicoOk ? 'Ativo' : 'Inativo' ?>
            </span>
            <span class="badge bg-<?= $configOk ? 'success' : 'danger' ?>">
                configtest: <?= $configOk ? 'OK' : 'ERRO' ?>
            </span>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted">Sites</div>
                <h2><?= (int)$status['sites_habilitados'] ?> / <?= (int)$status['sites_disponiveis'] ?></h2>
                <small>habilitados / disponíveis</small>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted">Módulos</div>
                <h2><?= (int)$status['modulos_habilitados'] ?> / <?= (int)$status['modulos_disponiveis'] ?></h2>
                <small>habilitados / disponíveis</small>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted">HTTPS</div>
                <h2>
                    <?= ($status['ssl_modulo'] && $status['ssl_porta']) ? 'Sim' : 'Não' ?>
                </h2>
                <small><?= $status['ssl_modulo'] ? 'mod_ssl carregado' : 'mod_ssl não carregado' ?></small>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted">Serviço</div>
                <h2 class="text-<?= $servicoOk ? 'success' : 'danger' ?>">
                    <?= htmlspecialchars($status['servico_status']) ?>
                </h2>
                <small>inicialização: <?= htmlspecialchars($status['servico_enabled']) ?></small>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <strong><i class="bi bi-journal-text"></i> Logs</strong>
            </div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <?php foreach ($status['logs'] as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['nome']) ?></td>
                            <td class="text-muted"><?= htmlspecialchars($log['tamanho']) ?></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-secondary botao-ver-log" data-nome="<?= htmlspecialchars($log['nome']) ?>">
                                    <i class="bi bi-eye"></i> Ver
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <strong><i class="bi bi-compass"></i> Atalhos</strong>
            </div>
            <div class="card-body d-flex flex-column gap-2">
                <a href="<?= url('/apache/sites') ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-globe"></i> Gerenciar Sites
                </a>
                <a href="<?= url('/apache/modulos') ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-puzzle"></i> Gerenciar Módulos
                </a>
                <a href="<?= url('/apache/configuracao') ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-sliders"></i> Config. Global
                </a>
                <a href="<?= url('/infraestrutura/servicos') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-hdd-network"></i> Reiniciar / Recarregar serviço
                </a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalVerLog" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalVerLogTitulo">Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre class="bg-dark text-light p-3 rounded mb-0" style="font-size:12px; white-space:pre-wrap;" id="modalVerLogConteudo"></pre>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    document.querySelectorAll('.botao-ver-log').forEach(function (botao) {
        botao.addEventListener('click', async function () {
            const nome = botao.dataset.nome;
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalVerLog'));
            const corpo = document.getElementById('modalVerLogConteudo');

            document.getElementById('modalVerLogTitulo').textContent = nome;
            corpo.textContent = 'Carregando...';
            modal.show();

            try {
                const res = await fetch(<?= json_encode(url('/apache/dashboard/log')) ?> + '?nome=' + encodeURIComponent(nome));
                const dados = await res.json();
                corpo.textContent = dados.conteudo || '(vazio)';
            } catch (e) {
                corpo.textContent = 'Erro ao carregar o log.';
            }
        });
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Dashboard Apache';

require __DIR__ . '/../layouts/main.php';
