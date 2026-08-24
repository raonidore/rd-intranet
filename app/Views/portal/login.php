<?php
$mensagem = $_SESSION['flash_msg'] ?? null;
$tipoMensagem = $_SESSION['flash_tipo'] ?? 'error';
unset($_SESSION['flash_msg'], $_SESSION['flash_tipo']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Meus Chamados</title>
    <link rel="icon" href="<?= url('/favicon.ico') ?>" sizes="any">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php require __DIR__ . '/../auth/_auth_estilo.php'; ?>
</head>

<body class="auth-body d-flex align-items-center justify-content-center">

<div class="auth-card card border-0">
    <div class="card-body p-4 p-sm-5">
        <div class="text-center mb-4">
            <i class="bi bi-ticket-perforated" style="font-size:42px; color:#2563eb"></i>
            <h5 class="mt-2 mb-0">Meus Chamados</h5>
            <p class="text-muted small mb-0">Acompanhe o andamento do seu chamado de suporte</p>
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipoMensagem === 'success' ? 'success' : 'danger' ?> py-2">
                <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php endif; ?>

        <?php if (!$emailDisponivel): ?>
            <div class="alert alert-secondary py-2 mb-0">O acesso ao portal está temporariamente indisponível. Entre em contato com quem abriu seu chamado.</div>
        <?php else: ?>
            <form method="post" action="<?= url('/portal/chamados/entrar') ?>">
                <div class="mb-4">
                    <label class="form-label">E-mail informado na abertura do chamado</label>
                    <div class="input-group auth-input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" class="form-control" required autofocus>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 auth-btn">
                    Receber link de acesso <i class="bi bi-arrow-right ms-1"></i>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
