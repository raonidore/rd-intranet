<?php

use App\Components\Alert;
use App\Components\Badge;

ob_start();

$curto = fn(?string $sha) => $sha ? substr($sha, 0, 7) : '—';

$blocoCommit = function (?array $commit): string {
    if (!$commit) {
        return '<div class="text-muted">—</div>';
    }

    return '<div>' . htmlspecialchars($commit['assunto']) . '</div>'
        . '<div class="small text-muted font-monospace">' . htmlspecialchars(substr($commit['hash'], 0, 7))
        . ' · ' . htmlspecialchars($commit['data']) . '</div>';
};
?>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-cloud-arrow-down me-1"></i> Atualizações do Sistema</h4>
        <small class="text-muted">Busca e aplica atualizações direto do repositório (branch <code>main</code>).</small>
    </div>
    <button type="button" class="btn btn-outline-primary" id="botaoVerificar">
        <i class="bi bi-arrow-repeat"></i> Verificar agora
    </button>
</div>

<?php if ($ultimoErro): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($ultimoErro) ?>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="text-muted small mb-1">Versão em execução</div>
                <?= $blocoCommit($commitLocal) ?>
            </div>
            <div class="col-md-4">
                <div class="text-muted small mb-1">Última versão no repositório</div>
                <?= $blocoCommit($commitRemoto) ?>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Última verificação</div>
                <div><?= $verificadoEm ? htmlspecialchars($verificadoEm) : 'Nunca verificado' ?></div>
            </div>
        </div>

        <?php if (!$checagemDiariaAtiva): ?>
            <hr>
            <div class="d-flex justify-content-between align-items-center">
                <div class="small text-muted">
                    <i class="bi bi-info-circle"></i> Não há verificação automática diária configurada.
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="botaoChecagemDiaria">
                    Ativar verificação diária
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$passosPendentes = array_filter($passosManuais, fn($p) => $p['status'] === 'pendente');
$passosResolvidos = array_filter($passosManuais, fn($p) => $p['status'] !== 'pendente');

$blocoPasso = function (array $p): string {
    ob_start();
    ?>
    <div class="list-group-item">
        <div class="d-flex justify-content-between align-items-start gap-3">
            <div class="flex-grow-1">
                <div class="fw-semibold"><?= htmlspecialchars($p['titulo']) ?></div>
                <div class="small text-muted mb-2"><?= htmlspecialchars($p['descricao']) ?></div>
                <code class="d-inline-block bg-light p-2 rounded small"><?= htmlspecialchars($p['comando']) ?></code>
            </div>
            <div class="text-end" style="min-width: 220px;">
                <?php if ($p['status'] === 'auto'): ?>
                    <?= Badge::make('Detectado automaticamente', 'success') ?>
                <?php elseif ($p['status'] === 'manual'): ?>
                    <?= Badge::make('Confirmado manualmente', 'success') ?>
                    <div class="small text-muted mt-1">
                        em <?= htmlspecialchars($p['confirmado_em']) ?><?= $p['confirmado_por_nome'] ? ' por ' . htmlspecialchars($p['confirmado_por_nome']) : '' ?>
                    </div>
                    <form method="post" action="<?= url('/administracao/atualizacoes/passos-manuais/desconfirmar') ?>" class="mt-1"
                          onsubmit="return confirm('Desfazer a confirmação deste passo?');">
                        <input type="hidden" name="chave" value="<?= htmlspecialchars($p['chave']) ?>">
                        <button type="submit" class="btn btn-sm btn-link text-muted p-0">desfazer</button>
                    </form>
                <?php else: ?>
                    <?= Badge::make('Pendente', 'warning') ?>
                    <form method="post" action="<?= url('/administracao/atualizacoes/passos-manuais/confirmar') ?>" class="mt-2"
                          onsubmit="return confirm('Confirma que já rodou este comando como root neste servidor?');">
                        <input type="hidden" name="chave" value="<?= htmlspecialchars($p['chave']) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-success">Marcar como feito</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
};
?>
<?php if (!empty($passosPendentes)): ?>
    <div class="card border-0 shadow-sm mb-4 border-start border-4 border-warning">
        <div class="card-header bg-white">
            <strong><i class="bi bi-exclamation-triangle text-warning"></i> Ações manuais pendentes (requerem root/SSH)</strong>
            <div class="small text-muted mt-1">
                O update via web não consegue rodar estes passos sozinho (não dá pra um processo se autoconceder mais
                acesso). Rode o comando neste servidor e depois confirme aqui.
            </div>
        </div>
        <div class="list-group list-group-flush">
            <?php foreach ($passosPendentes as $p): ?>
                <?= $blocoPasso($p) ?>
            <?php endforeach; ?>
        </div>
    </div>
<?php elseif (!empty($passosManuais)): ?>
    <div class="alert alert-success d-flex justify-content-between align-items-center mb-4">
        <div><i class="bi bi-check-circle"></i> Nenhuma ação manual pendente neste servidor.</div>
    </div>
<?php endif; ?>

<?php if (!empty($passosResolvidos)): ?>
    <details class="mb-4">
        <summary class="text-muted small" style="cursor: pointer;">
            Ver passos manuais já resolvidos neste servidor (<?= count($passosResolvidos) ?>)
        </summary>
        <div class="card border-0 shadow-sm mt-2">
            <div class="list-group list-group-flush">
                <?php foreach ($passosResolvidos as $p): ?>
                    <?= $blocoPasso($p) ?>
                <?php endforeach; ?>
            </div>
        </div>
    </details>
<?php endif; ?>

<?php if (empty($commitsPendentes)): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle"></i> Nenhuma atualização pendente.
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong><i class="bi bi-cloud-download"></i> <?= count($commitsPendentes) ?> atualização(ões) disponível(is)</strong>
            <button type="button" class="btn btn-primary" id="botaoAplicar">
                <i class="bi bi-download"></i> Atualizar agora
            </button>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Commit</th>
                        <th>Autor</th>
                        <th>Data</th>
                        <th>Descrição</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($commitsPendentes as $c): ?>
                        <tr>
                            <td class="font-monospace"><?= htmlspecialchars($curto($c['hash'])) ?></td>
                            <td><?= htmlspecialchars($c['autor']) ?></td>
                            <td class="small"><?= htmlspecialchars($c['data']) ?></td>
                            <td><?= htmlspecialchars($c['assunto']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong><i class="bi bi-clock-history"></i> Histórico</strong>
        <?php if ($podeReverter): ?>
            <button type="button" class="btn btn-sm btn-outline-danger" id="botaoReverter">
                <i class="bi bi-arrow-counterclockwise"></i> Reverter última atualização
            </button>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (empty($historico)): ?>
            <div class="text-center text-muted py-4">Nenhuma atualização aplicada ainda.</div>
        <?php else: ?>
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Quando</th>
                        <th>Tipo</th>
                        <th>Commit</th>
                        <th>Usuário</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historico as $h): ?>
                        <tr>
                            <td class="small"><?= htmlspecialchars($h['criado_em']) ?></td>
                            <td><?= $h['tipo'] === 'aplicar' ? 'Atualização' : 'Reversão' ?></td>
                            <td class="font-monospace small">
                                <?= htmlspecialchars($curto($h['commit_antes'])) ?> → <?= htmlspecialchars($curto($h['commit_depois'])) ?>
                            </td>
                            <td><?= htmlspecialchars($h['usuario_nome'] ?? 'Sistema') ?></td>
                            <td><?= (int)$h['sucesso'] === 1 ? Badge::make('Sucesso', 'success') : Badge::make('Falha', 'danger') ?></td>
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
                <div class="text-center text-muted py-3"><i class="bi bi-hourglass-split"></i> Aguarde, pode levar até alguns minutos...</div>
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
        verificar: <?= json_encode(url('/administracao/atualizacoes/verificar')) ?>,
        aplicar: <?= json_encode(url('/administracao/atualizacoes/aplicar')) ?>,
        reverter: <?= json_encode(url('/administracao/atualizacoes/reverter')) ?>,
        checagemDiaria: <?= json_encode(url('/administracao/atualizacoes/checagem-diaria')) ?>,
    };

    async function executar(url, titulo) {
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAcao'));
        const corpo = document.getElementById('modalAcaoCorpo');
        const rodape = document.getElementById('modalAcaoRodape');

        document.getElementById('modalAcaoTitulo').textContent = titulo;
        corpo.innerHTML = '<div class="text-center text-muted py-3"><i class="bi bi-hourglass-split"></i> Aguarde, pode levar até alguns minutos...</div>';
        rodape.style.display = 'none';
        modal.show();

        try {
            const res = await fetch(url, { method: 'POST' });
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

    document.getElementById('botaoVerificar').addEventListener('click', function () {
        executar(URLS.verificar, 'Verificando atualizações');
    });

    const botaoAplicar = document.getElementById('botaoAplicar');
    if (botaoAplicar) {
        botaoAplicar.addEventListener('click', function () {
            if (!confirm('Aplicar a atualização agora? O sistema pode ficar indisponível por alguns segundos.')) return;
            executar(URLS.aplicar, 'Atualizando');
        });
    }

    const botaoReverter = document.getElementById('botaoReverter');
    if (botaoReverter) {
        botaoReverter.addEventListener('click', function () {
            if (!confirm('Reverter para a versão anterior à última atualização aplicada?')) return;
            executar(URLS.reverter, 'Revertendo');
        });
    }

    const botaoChecagem = document.getElementById('botaoChecagemDiaria');
    if (botaoChecagem) {
        botaoChecagem.addEventListener('click', function () {
            executar(URLS.checagemDiaria, 'Ativando verificação diária');
        });
    }
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Administração - Atualizações do Sistema';

require __DIR__ . '/../layouts/main.php';
