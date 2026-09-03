<?php
use App\Services\ChamadoService;

$mensagem = $_SESSION['flash_msg'] ?? null;
$tipoMensagem = $_SESSION['flash_tipo'] ?? 'error';
unset($_SESSION['flash_msg'], $_SESSION['flash_tipo']);

$corStatus = ['fila' => 'secondary', 'em_atendimento' => 'primary', 'aguardando_cliente' => 'warning', 'resolvido' => 'success', 'fechado' => 'dark'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Meus Chamados</title>
    <link rel="icon" href="<?= url('/favicon.ico') ?>" sizes="any">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>

<?php require __DIR__ . '/_topo.php'; ?>

<div class="portal-container">
    <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipoMensagem === 'success' ? 'success' : 'danger' ?>"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>

    <?php if (empty($chamados)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-ticket" style="font-size:2rem;"></i>
                <p class="mb-0 mt-2">Nenhum chamado registrado com esse e-mail ainda.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($chamados as $item): ?>
            <a href="<?= url('/portal/chamados/ver?id=' . (int)$item['id']) ?>" class="text-decoration-none text-reset">
                <div class="card border-0 shadow-sm mb-2">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div style="min-width:0">
                            <span class="font-monospace text-muted small">#<?= htmlspecialchars($item['numero_controle'] ?? $item['id']) ?></span>
                            <strong><?= htmlspecialchars($item['titulo']) ?></strong>
                            <span class="badge text-bg-<?= $corStatus[$item['status']] ?? 'secondary' ?>"><?= htmlspecialchars(ChamadoService::STATUS[$item['status']]) ?></span>
                            <div class="text-muted small"><?= htmlspecialchars($item['categoria_nome']) ?></div>
                        </div>
                        <small class="text-muted text-nowrap"><?= data_br($item['aberto_em']) ?></small>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
