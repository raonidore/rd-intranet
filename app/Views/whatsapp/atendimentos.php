<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-4 d-flex justify-content-between align-items-start">
    <div>
        <h4 class="mb-1"><i class="bi bi-chat-dots me-1"></i> WhatsApp - Atendimentos</h4>
        <small class="text-muted">Suas conversas em andamento. Novos atendimentos aparecem em <a href="<?= url('/whatsapp/fila') ?>">Fila</a>.</small>
    </div>
    <div class="d-flex gap-2">
        <button type="button" id="btnSomWpp" class="btn btn-outline-secondary" title="Ativar som para mensagens novas">
            <i class="bi bi-volume-mute"></i>
        </button>
        <button type="button" class="btn btn-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#modalIniciarAtendimento">
            <i class="bi bi-plus-lg"></i> Iniciar Atendimento
        </button>
    </div>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $aba === 'andamento' ? 'active' : '' ?>" href="<?= url('/whatsapp/atendimentos?aba=andamento') ?>">Em andamento</a>
    </li>
    <?php if ($podeVerEncerrados): ?>
    <li class="nav-item">
        <a class="nav-link <?= $aba === 'encerrados' ? 'active' : '' ?>" href="<?= url('/whatsapp/atendimentos?aba=encerrados') ?>">Encerrados</a>
    </li>
    <?php endif; ?>
</ul>

<?php if ($aba === 'encerrados'): ?>

    <?php if (empty($encerrados)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted py-4">
                <i class="bi bi-check2-circle" style="font-size:2rem;"></i>
                <p class="mb-0 mt-2">Nenhum atendimento encerrado ainda.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($encerrados as $item): ?>
            <a href="<?= url('/whatsapp/atendimentos/ver?id=' . (int)$item['id']) ?>" class="text-decoration-none text-reset">
            <div class="card border-0 shadow-sm mb-2">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div style="min-width:0">
                        <strong><?= htmlspecialchars($item['contato_nome'] ?: '(sem nome)') ?></strong>
                        <span class="text-muted small ms-1"><?= htmlspecialchars(telefone_br($item['numero'])) ?></span>
                        <?php if ($item['setor_nome']): ?>
                            <span class="badge text-bg-light border ms-1"><?= htmlspecialchars($item['setor_nome']) ?></span>
                        <?php endif; ?>
                        <div class="text-muted small text-truncate" style="max-width:480px">
                            <?= $item['ultima_mensagem'] !== null ? htmlspecialchars($item['ultima_mensagem']) : '(sem mensagens)' ?>
                        </div>
                    </div>
                    <small class="text-muted text-nowrap">Encerrado em <?= data_br($item['encerrado_em']) ?></small>
                </div>
            </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>

<?php else: ?>

    <?php if (empty($atendimentos)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted py-4">
                <i class="bi bi-chat" style="font-size:2rem;"></i>
                <p class="mb-0 mt-2">Você não tem nenhum atendimento em andamento.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($atendimentos as $item): ?>
            <a href="<?= url('/whatsapp/atendimentos/ver?id=' . (int)$item['id']) ?>" class="text-decoration-none text-reset">
                <div class="card border-0 shadow-sm mb-2">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?= htmlspecialchars($item['contato_nome'] ?: '(sem nome)') ?></strong>
                            <span class="text-muted small ms-1"><?= htmlspecialchars(telefone_br($item['numero'])) ?></span>
                            <div class="text-muted small text-truncate" style="max-width:480px">
                                <?= $item['ultima_mensagem'] !== null ? htmlspecialchars($item['ultima_mensagem']) : '(sem mensagens)' ?>
                            </div>
                        </div>
                        <small class="text-muted text-nowrap"><?= data_br($item['ultima_mensagem_em']) ?></small>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>

<?php endif; ?>

<div class="modal fade" id="modalIniciarAtendimento" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?= url('/whatsapp/atendimentos/iniciar') ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Iniciar Atendimento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        Manda a primeira mensagem pra um número que ainda não entrou em contato -- o atendimento já abre
                        direto com você, sem passar pelo bot ou pela fila.
                    </p>
                    <div class="mb-3">
                        <label class="form-label">Telefone (com DDD)</label>
                        <input type="text" name="telefone" class="form-control" placeholder="(83) 99104-3598" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nome <span class="text-muted">(opcional)</span></label>
                        <input type="text" name="nome" class="form-control" placeholder="Nome do cliente">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Primeira mensagem</label>
                        <textarea name="mensagem" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Iniciar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const CHAVE_SOM = 'wppSomAtivo';
    const btnSom = document.getElementById('btnSomWpp');
    const icone = btnSom.querySelector('i');

    function somAtivo() {
        return localStorage.getItem(CHAVE_SOM) === '1';
    }

    function atualizarIcone() {
        icone.className = somAtivo() ? 'bi bi-volume-up-fill' : 'bi bi-volume-mute';
        btnSom.classList.toggle('btn-primary', somAtivo());
        btnSom.classList.toggle('btn-outline-secondary', !somAtivo());
        btnSom.title = somAtivo() ? 'Som ativado -- clique pra desativar' : 'Ativar som para mensagens novas';
    }

    btnSom.addEventListener('click', () => {
        localStorage.setItem(CHAVE_SOM, somAtivo() ? '0' : '1');
        atualizarIcone();
    });

    atualizarIcone();

    function tocarBipWpp() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            [880, 1108].forEach((freq, i) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.type = 'sine';
                osc.frequency.value = freq;
                const inicio = ctx.currentTime + i * 0.14;
                gain.gain.setValueAtTime(0.001, inicio);
                gain.gain.exponentialRampToValueAtTime(0.18, inicio + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.001, inicio + 0.28);
                osc.start(inicio);
                osc.stop(inicio + 0.3);
            });
        } catch (e) {
            // navegador sem suporte a Web Audio -- ignora, sem quebrar o resto
        }
    }

    let ultimaContagem = null;
    const badgeMenu = document.getElementById('rdWppBadgeAtendimentos');

    async function verificarNovasMensagens() {
        try {
            const resp = await fetch('<?= url('/whatsapp/atendimentos/contador') ?>');
            const dados = await resp.json();
            if (!dados.success) {
                return;
            }

            if (ultimaContagem !== null && dados.aguardando > ultimaContagem && somAtivo()) {
                tocarBipWpp();
            }
            ultimaContagem = dados.aguardando;

            if (badgeMenu) {
                if (dados.aguardando > 0) {
                    badgeMenu.textContent = dados.aguardando;
                    badgeMenu.style.display = '';
                } else {
                    badgeMenu.style.display = 'none';
                }
            }
        } catch (e) {
            // rede instável -- tenta de novo no próximo ciclo
        }
    }

    verificarNovasMensagens();
    setInterval(verificarNovasMensagens, 15000);
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'WhatsApp - Atendimentos';

require __DIR__ . '/../layouts/main.php';
