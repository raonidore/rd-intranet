<?php
ob_start();

use App\Components\Alert;
use App\Components\Badge;
?>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-globe2 me-1"></i> DNS Dinâmico (DDNS)</h4>
        <small class="text-muted">Mantém hostnames de No-IP, DynDNS, Cloudflare, DuckDNS e FreeDNS com o IP público atual do servidor.</small>
    </div>
    <div class="d-flex gap-2">
        <?php if (!empty($contas)): ?>
        <button type="button" class="btn btn-outline-primary" id="botaoAtualizarTodas">
            <i class="bi bi-arrow-repeat"></i> Atualizar todas agora
        </button>
        <?php endif; ?>
        <a href="<?= url('/infraestrutura/ddns/novo') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Nova conta
        </a>
    </div>
</div>

<?php if (!$atualizacaoAutomaticaAtiva): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div class="small text-muted">
                <i class="bi bi-info-circle"></i> Não há atualização automática configurada — o IP só é reenviado aos
                provedores quando "Atualizar agora" é clicado.
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="botaoAutomatica">
                Ativar atualização automática
            </button>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-success mb-4">
        <i class="bi bi-check-circle"></i> Atualização automática ativa (verifica a cada 15 minutos).
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($contas)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-globe2 display-6"></i>
                <p class="mt-2 mb-0">Nenhuma conta de DNS dinâmico configurada.</p>
            </div>
        <?php else: ?>
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Apelido</th>
                        <th>Provedor</th>
                        <th>Hostname</th>
                        <th>Status</th>
                        <th>Último IP</th>
                        <th>Verificado em</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contas as $c): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($c['apelido']) ?>
                                <?php if ((int)$c['ativo'] !== 1): ?>
                                    <?= Badge::make('Desativada', 'secondary') ?>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($provedoresLabel[$c['provedor']] ?? $c['provedor']) ?></td>
                            <td class="font-monospace small"><?= htmlspecialchars($c['hostname']) ?></td>
                            <td>
                                <?php if ($c['ultimo_sucesso'] === null): ?>
                                    <?= Badge::make('Nunca atualizado', 'secondary') ?>
                                <?php elseif ((int)$c['ultimo_sucesso'] === 1): ?>
                                    <?= Badge::make('OK', 'success') ?>
                                <?php else: ?>
                                    <?= Badge::make('Falha', 'danger') ?>
                                <?php endif; ?>
                                <?php if ($c['ultima_mensagem']): ?>
                                    <div class="small text-muted"><?= htmlspecialchars($c['ultima_mensagem']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="font-monospace small"><?= htmlspecialchars($c['ultimo_ip'] ?? '—') ?></td>
                            <td class="small"><?= $c['ultima_verificacao_em'] ? htmlspecialchars(data_br($c['ultima_verificacao_em'])) : '—' ?></td>
                            <td class="text-end">
                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary botao-atualizar-agora" data-id="<?= (int)$c['id'] ?>" title="Atualizar agora">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                    <a href="<?= url('/infraestrutura/ddns/historico?id=' . $c['id']) ?>"
                                       class="btn btn-sm btn-outline-secondary" title="Ver histórico">
                                        <i class="bi bi-journal-text"></i>
                                    </a>
                                </div>
                                <div class="btn-group" role="group">
                                    <a href="<?= url('/infraestrutura/ddns/editar?id=' . $c['id']) ?>"
                                       class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if ((int)$c['ativo'] === 1): ?>
                                        <a href="<?= url('/infraestrutura/ddns/desativar?id=' . $c['id']) ?>"
                                           class="btn btn-sm btn-outline-warning" title="Desativar">
                                            <i class="bi bi-toggle2-on"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= url('/infraestrutura/ddns/ativar?id=' . $c['id']) ?>"
                                           class="btn btn-sm btn-outline-success" title="Ativar">
                                            <i class="bi bi-toggle2-off"></i>
                                        </a>
                                    <?php endif; ?>
                                    <form method="post" action="<?= url('/infraestrutura/ddns/excluir') ?>" class="d-inline"
                                          onsubmit="return confirm('Excluir a conta \'<?= htmlspecialchars(addslashes($c['apelido'])) ?>\'?');">
                                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
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
        atualizarAgora: <?= json_encode(url('/infraestrutura/ddns/atualizar-agora')) ?>,
        atualizarTodas: <?= json_encode(url('/infraestrutura/ddns/atualizar-todas')) ?>,
        automatica: <?= json_encode(url('/infraestrutura/ddns/automatica')) ?>,
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

    document.querySelectorAll('.botao-atualizar-agora').forEach(function (botao) {
        botao.addEventListener('click', function () {
            const dados = new URLSearchParams();
            dados.set('id', botao.dataset.id);
            executar(URLS.atualizarAgora, 'Atualizando conta', dados);
        });
    });

    const botaoAtualizarTodas = document.getElementById('botaoAtualizarTodas');
    if (botaoAtualizarTodas) {
        botaoAtualizarTodas.addEventListener('click', function () {
            executar(URLS.atualizarTodas, 'Atualizando todas as contas');
        });
    }

    const botaoAutomatica = document.getElementById('botaoAutomatica');
    if (botaoAutomatica) {
        botaoAutomatica.addEventListener('click', function () {
            executar(URLS.automatica, 'Ativando atualização automática');
        });
    }
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - DNS Dinâmico';

require __DIR__ . '/../layouts/main.php';
