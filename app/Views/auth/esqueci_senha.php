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
</head>

<body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh;">

<div class="card border-0 shadow-sm" style="width:390px;">
    <div class="card-body p-4">
        <div class="text-center mb-3">
            <img src="<?= $logoSistemaConfigurada ? url('/administracao/empresa/logo-sistema') : url('/assets/img/logord.png') ?>" alt="RD Intranet" style="max-height:70px;max-width:100%;">
        </div>
        <h5 class="text-center mb-1">Esqueci minha senha</h5>
        <p class="text-muted text-center small mb-4">Informe seu usuário ou e-mail cadastrado. Se encontrarmos uma conta correspondente, enviamos um link de redefinição.</p>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipoMensagem === 'success' ? 'success' : 'danger' ?>">
                <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= url('/login/esqueci') ?>">
            <div class="mb-3">
                <label class="form-label">Usuário ou e-mail</label>
                <input type="text" name="login_ou_email" class="form-control" required autofocus>
            </div>

            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-send"></i> Enviar link de redefinição
            </button>
        </form>

        <div class="text-center mt-3">
            <a href="<?= url('/login') ?>" class="small"><i class="bi bi-arrow-left"></i> Voltar pro login</a>
        </div>
    </div>
</div>

</body>
</html>
