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
            <?php $ehDeColega = (int)($item['usuario_id'] ?? 0) !== (int)$usuarioId; ?>
            <a href="<?= url('/whatsapp/atendimentos/ver?id=' . (int)$item['id']) ?>" class="text-decoration-none text-reset">
                <div class="card border-0 shadow-sm mb-2">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?= htmlspecialchars($item['contato_nome'] ?: '(sem nome)') ?></strong>
                            <span class="text-muted small ms-1"><?= htmlspecialchars(telefone_br($item['numero'])) ?></span>
                            <?php if ($ehDeColega): ?>
                                <span class="badge text-bg-light border ms-1"><i class="bi bi-eye"></i> <?= htmlspecialchars($item['usuario_nome'] ?? '?') ?></span>
                            <?php endif; ?>
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
        const vaiLigar = !somAtivo();
        localStorage.setItem(CHAVE_SOM, vaiLigar ? '1' : '0');
        atualizarIcone();

        // Bip de teste na hora, dentro do próprio clique -- esse clique
        // é o gesto que destrava o áudio no navegador, então se o som
        // do computador estiver ok isso TEM que tocar. Se não tocar
        // aqui, o problema não é do nosso alerta -- é volume/saída de
        // áudio/aba silenciada no navegador.
        if (vaiLigar && typeof window.rdTocarBip === 'function') {
            window.rdTocarBip();
        }
    });

    atualizarIcone();
    // O alerta sonoro em si (o "bip") e a checagem periódica rodam em
    // main.php -- assim funcionam em qualquer tela do sistema, não só
    // aqui em Atendimentos, já que o usuário pode estar em Fila,
    // Chatbot etc. quando a mensagem chega. Esse botão só liga/desliga
    // a preferência (localStorage), lida por aquele script.
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'WhatsApp - Atendimentos';

require __DIR__ . '/../layouts/main.php';
