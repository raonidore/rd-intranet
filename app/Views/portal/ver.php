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
    <title>Chamado #<?= (int)$chamado['id'] ?></title>
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
            <span class="font-monospace text-muted">#<?= (int)$chamado['id'] ?></span>
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
</div>

</body>
</html>
