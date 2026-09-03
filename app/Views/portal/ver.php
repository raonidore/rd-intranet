<?php
use App\Services\ChamadoService;

$mensagem = $_SESSION['flash_msg'] ?? null;
$tipoMensagem = $_SESSION['flash_tipo'] ?? 'error';
unset($_SESSION['flash_msg'], $_SESSION['flash_tipo']);

$corPrioridade = ['baixa' => 'secondary', 'media' => 'primary', 'alta' => 'warning', 'urgente' => 'danger'];
$corStatus = ['fila' => 'secondary', 'em_atendimento' => 'primary', 'aguardando_cliente' => 'warning', 'resolvido' => 'success', 'fechado' => 'dark'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Chamado #<?= htmlspecialchars($chamado['numero_controle'] ?? $chamado['id']) ?></title>
    <link rel="icon" href="<?= url('/favicon.ico') ?>" sizes="any">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>

<?php require __DIR__ . '/_topo.php'; ?>

<div class="portal-container">
    <small class="text-muted"><a href="<?= url('/portal/chamados/meus') ?>"><i class="bi bi-arrow-left"></i> Meus chamados</a></small>

    <div class="mb-3 mt-1">
        <h4 class="mb-1">
            <span class="font-monospace text-muted">#<?= htmlspecialchars($chamado['numero_controle'] ?? $chamado['id']) ?></span>
            <?= htmlspecialchars($chamado['titulo']) ?>
        </h4>
        <span class="badge text-bg-<?= $corStatus[$chamado['status']] ?? 'secondary' ?>"><?= htmlspecialchars(ChamadoService::STATUS[$chamado['status']]) ?></span>
        <span class="badge text-bg-<?= $corPrioridade[$chamado['prioridade']] ?? 'secondary' ?>"><?= htmlspecialchars(ChamadoService::PRIORIDADES[$chamado['prioridade']]) ?></span>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipoMensagem === 'success' ? 'success' : 'danger' ?>"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="text-muted small mb-1">Descrição</div>
            <div style="white-space:pre-wrap"><?= htmlspecialchars($chamado['descricao']) ?></div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><strong>Conversa</strong></div>
        <div class="card-body">
            <?php if (empty($comentarios)): ?>
                <p class="text-muted small mb-0">Nenhuma resposta ainda -- assim que a equipe responder, aparece aqui.</p>
            <?php else: ?>
                <?php foreach ($comentarios as $c): ?>
                    <div class="mb-3 pb-3 border-bottom">
                        <div class="d-flex justify-content-between">
                            <strong><?= $c['usuario_nome'] ? htmlspecialchars($c['usuario_nome']) . ' (equipe)' : htmlspecialchars($chamado['solicitante_nome']) ?></strong>
                            <small class="text-muted"><?= data_br($c['criado_em'], 'd/m/Y H:i') ?></small>
                        </div>
                        <div class="mt-1" style="white-space:pre-wrap"><?= htmlspecialchars($c['conteudo']) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!$somenteLeitura): ?>
                <form method="post" action="<?= url('/portal/chamados/responder') ?>" class="mt-3">
                    <input type="hidden" name="id" value="<?= (int)$chamado['id'] ?>">
                    <textarea name="conteudo" class="form-control mb-2" rows="3" required placeholder="Escreva sua resposta..."></textarea>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Enviar</button>
                </form>
            <?php else: ?>
                <div class="alert alert-secondary mb-0 mt-3">Esse chamado já foi encerrado.</div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($somenteLeitura): ?>
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body">
                <?php if ($avaliacao): ?>
                    <div class="text-muted small mb-1">Sua avaliação</div>
                    <div class="fs-4 mb-1">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="bi bi-star<?= $i <= (int)$avaliacao['nota'] ? '-fill text-warning' : ' text-muted' ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <?php if ($avaliacao['resolvido'] !== null): ?>
                        <div class="small text-muted mb-1">Problema resolvido: <?= $avaliacao['resolvido'] ? 'Sim' : 'Não' ?></div>
                    <?php endif; ?>
                    <?php if (!empty($avaliacao['comentario'])): ?>
                        <div class="small mt-2" style="white-space:pre-wrap"><?= htmlspecialchars($avaliacao['comentario']) ?></div>
                    <?php endif; ?>
                    <div class="small text-muted mt-2">Obrigado pelo retorno!</div>
                <?php else: ?>
                    <strong class="d-block mb-2">Como foi o atendimento?</strong>
                    <form method="post" action="<?= url('/portal/chamados/avaliar') ?>">
                        <input type="hidden" name="id" value="<?= (int)$chamado['id'] ?>">
                        <div class="mb-3 fs-3 portal-estrelas">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <label class="portal-estrela">
                                    <input type="radio" name="nota" value="<?= $i ?>" required class="d-none">
                                    <i class="bi bi-star"></i>
                                </label>
                            <?php endfor; ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">O problema foi resolvido?</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="resolvido" value="1" id="resolvidoSim">
                                    <label class="form-check-label" for="resolvidoSim">Sim</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="resolvido" value="0" id="resolvidoNao">
                                    <label class="form-check-label" for="resolvidoNao">Não</label>
                                </div>
                            </div>
                        </div>
                        <textarea name="comentario" class="form-control mb-2" rows="2" placeholder="Algum comentário? (opcional)"></textarea>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Enviar avaliação</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('.portal-estrelas .portal-estrela input').forEach(function (input) {
    input.addEventListener('change', function () {
        const nota = parseInt(this.value, 10);
        document.querySelectorAll('.portal-estrelas .portal-estrela').forEach(function (label, idx) {
            const icone = label.querySelector('i');
            icone.className = (idx + 1) <= nota ? 'bi bi-star-fill text-warning' : 'bi bi-star';
        });
    });
});
</script>
<style>
.portal-estrela { cursor: pointer; margin-right: 4px; }
</style>

</body>
</html>
