<?php
$mensagem = $_SESSION['flash_msg'] ?? null;
$tipoMensagem = $_SESSION['flash_tipo'] ?? 'error';
unset($_SESSION['flash_msg'], $_SESSION['flash_tipo']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Esqueci minha senha - RD Intranet</title>
    <link rel="icon" href="<?= url('/favicon.ico') ?>" sizes="any">
    <link rel="icon" href="<?= url('/assets/img/favicon.png') ?>" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php require __DIR__ . '/_auth_estilo.php'; ?>
</head>

<body class="auth-body d-flex align-items-center justify-content-center">

<div class="auth-card card border-0">
    <div class="card-body p-4 p-sm-5">
        <div class="text-center mb-4">
            <img src="<?= $logoSistemaConfigurada ? url('/administracao/empresa/logo-sistema') : url('/assets/img/logord.png') ?>" alt="RD Intranet" class="auth-logo">
        </div>
        <h5 class="text-center mb-1">Esqueci minha senha</h5>
        <p class="text-muted text-center small mb-4">Informe seu usuário ou e-mail cadastrado. Se encontrarmos uma conta correspondente, enviamos um link de redefinição.</p>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipoMensagem === 'success' ? 'success' : 'danger' ?> py-2">
                <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= url('/login/esqueci') ?>">
            <div class="mb-4">
                <label class="form-label">Usuário ou e-mail</label>
                <div class="input-group auth-input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" name="login_ou_email" class="form-control" required autofocus>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 auth-btn">
                <i class="bi bi-send"></i> Enviar link de redefinição
            </button>
        </form>

        <div class="text-center mt-4">
            <a href="<?= url('/login') ?>" class="auth-voltar"><i class="bi bi-arrow-left"></i> Voltar pro login</a>
        </div>
    </div>
</div>

</body>
</html>
