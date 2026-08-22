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
    <button type="button" class="btn btn-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#modalIniciarAtendimento">
        <i class="bi bi-plus-lg"></i> Iniciar Atendimento
    </button>
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

<?php
$conteudo = ob_get_clean();
$titulo = 'WhatsApp - Atendimentos';

require __DIR__ . '/../layouts/main.php';
