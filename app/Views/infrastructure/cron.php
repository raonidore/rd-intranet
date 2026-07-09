<?php

use App\Components\Alert;
use App\Components\Badge;

ob_start();
?>

<style>
.tech-escopo {
    background: #0f172a;
    border-radius: 16px;
    border: 1px solid #1e293b;
    color: #e2e8f0;
    padding: 22px 26px;
}
.tech-escopo code {
    background: #1e293b;
    color: #7dd3fc;
    padding: 1px 6px;
    border-radius: 4px;
}
.tech-escopo .linha {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}
.tech-escopo .linha + .linha { margin-top: 10px; }
.tech-escopo .linha i { font-size: 18px; margin-top: 2px; flex-shrink: 0; }
.tech-escopo p { color: #cbd5e1; margin: 0; }
</style>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="mb-1"><i class="bi bi-clock-history"></i> Cron do Sistema</h5>
            <small class="text-muted">
                Jobs agendados no servidor (/etc/cron.d/rd-intranet), gerenciados por aqui.
            </small>
        </div>
        <a href="<?= url('/infraestrutura/cron/novo') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Novo job
        </a>
    </div>
</div>

<div class="tech-escopo mb-4">
    <div class="linha">
        <i class="bi bi-check-circle-fill text-success"></i>
        <p>
            Tudo criado <strong>por esta tela</strong> vai para <code>/etc/cron.d/rd-intranet</code> e roda de verdade no servidor —
            criar, editar, ativar/desativar e excluir sempre regeneram esse arquivo, e o cron do sistema aplica a mudança na hora.
        </p>
    </div>
    <div class="linha">
        <i class="bi bi-x-circle-fill text-danger"></i>
        <p>
            Esta tela <strong>não enxerga nem gerencia</strong> jobs que já existem fora desse arquivo específico —
            crontab pessoal de outros usuários, <code>/etc/crontab</code> ou outros arquivos em <code>/etc/cron.d/</code>.
            Só o que passa por aqui é gerenciado.
        </p>
    </div>
</div>

<?php if ($sync['erro']): ?>
    <div class="alert alert-warning">
        <strong><i class="bi bi-exclamation-triangle"></i> Cron do sistema fora de sincronia.</strong>
        Última tentativa de aplicar falhou: <?= htmlspecialchars($sync['erro']) ?>
    </div>
<?php elseif ($sync['em']): ?>
    <div class="text-muted small mb-3">
        <i class="bi bi-check-circle text-success"></i> Sincronizado com o sistema em <?= htmlspecialchars($sync['em']) ?>.
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Agendamento</th>
                    <th>Usuário</th>
                    <th>Comando</th>
                    <th>Status</th>
                    <th>Última execução</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($jobs)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Nenhum job de cron cadastrado.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($jobs as $job): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($job['nome']) ?>
                            <?php if ($job['descricao']): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($job['descricao']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><code><?= htmlspecialchars($job['expressao']) ?></code></td>
                        <td><?= htmlspecialchars($job['usuario_execucao']) ?></td>
                        <td><code class="text-truncate d-inline-block" style="max-width:280px" title="<?= htmlspecialchars($job['comando']) ?>"><?= htmlspecialchars($job['comando']) ?></code></td>
                        <td><?= (int)$job['ativo'] === 1 ? Badge::make('Ativo', 'success') : Badge::make('Desativado', 'secondary') ?></td>
                        <td>
                            <?php if ($job['ultima_execucao_em']): ?>
                                <?= htmlspecialchars($job['ultima_execucao_em']) ?>
                                <?= (int)$job['ultima_execucao_sucesso'] === 1 ? Badge::make('OK', 'success') : Badge::make('Falha', 'danger') ?>
                            <?php else: ?>
                                <span class="text-muted">Nunca executado</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="btn-group me-2" role="group">
                                <button type="button" class="btn btn-sm btn-outline-secondary botao-executar" data-id="<?= $job['id'] ?>" title="Executar agora (fora do agendamento, não afeta o cron do sistema)">
                                    <i class="bi bi-lightning-charge-fill"></i>
                                </button>
                                <a href="<?= url('/infraestrutura/cron/logs?id=' . $job['id']) ?>"
                                   class="btn btn-sm btn-outline-secondary" title="Ver log">
                                    <i class="bi bi-journal-text"></i>
                                </a>
                            </div>
                            <div class="btn-group" role="group">
                                <a href="<?= url('/infraestrutura/cron/editar?id=' . $job['id']) ?>"
                                   class="btn btn-sm btn-outline-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ((int)$job['ativo'] === 1): ?>
                                    <a href="<?= url('/infraestrutura/cron/desativar?id=' . $job['id']) ?>"
                                       class="btn btn-sm btn-outline-warning" title="Desativar (remove do agendamento do sistema)">
                                        <i class="bi bi-toggle2-on"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="<?= url('/infraestrutura/cron/ativar?id=' . $job['id']) ?>"
                                       class="btn btn-sm btn-outline-success" title="Ativar (volta pro agendamento do sistema)">
                                        <i class="bi bi-toggle2-off"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="<?= url('/infraestrutura/cron/excluir?id=' . $job['id']) ?>"
                                   class="btn btn-sm btn-outline-danger" title="Excluir">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalExecutar" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Execução manual</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalExecutarCorpo">
                <div class="text-center text-muted"><i class="bi bi-hourglass-split"></i> Executando...</div>
            </div>
        </div>
    </div>
</div>

<script>
const baseUrlExecutar = <?= json_encode(url('/infraestrutura/cron/executar')) ?>;

document.querySelectorAll('.botao-executar').forEach(function (botao) {
    botao.addEventListener('click', function () {
        const modalExecutar = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalExecutar'));
        const id = botao.dataset.id;
        const corpo = document.getElementById('modalExecutarCorpo');
        corpo.innerHTML = '<div class="text-center text-muted"><i class="bi bi-hourglass-split"></i> Executando...</div>';
        modalExecutar.show();

        fetch(baseUrlExecutar, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + encodeURIComponent(id)
        })
            .then(function (r) { return r.json(); })
            .then(function (dados) {
                const cor = dados.success ? 'success' : 'danger';
                const icone = dados.success ? 'check-circle' : 'x-circle';
                corpo.innerHTML =
                    '<div class="alert alert-' + cor + '"><i class="bi bi-' + icone + '"></i> ' +
                    (dados.success ? 'Executado com sucesso.' : 'Execução falhou.') + '</div>' +
                    '<pre class="bg-dark text-light p-3 rounded mb-0">' +
                    (dados.output || '(sem saída)').replace(/</g, '&lt;') + '</pre>';
            })
            .catch(function () {
                corpo.innerHTML = '<div class="alert alert-danger mb-0">Erro ao executar o job.</div>';
            });
    });
});
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - Cron';

require __DIR__ . '/../layouts/main.php';
