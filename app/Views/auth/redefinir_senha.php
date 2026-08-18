<?php
$mensagem = $_SESSION['flash_msg'] ?? null;
$tipoMensagem = $_SESSION['flash_tipo'] ?? 'error';
unset($_SESSION['flash_msg'], $_SESSION['flash_tipo']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Redefinir senha - RD Intranet</title>
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
        <h5 class="text-center mb-3">Criar nova senha</h5>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipoMensagem === 'success' ? 'success' : 'danger' ?> py-2">
                <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php endif; ?>

        <?php if (!$valido): ?>
            <div class="alert alert-danger">Link inválido ou expirado. Solicite a redefinição novamente.</div>
            <div class="text-center mt-3">
                <a href="<?= url('/login/esqueci') ?>" class="auth-link-esqueci">Solicitar novo link</a>
            </div>
        <?php else: ?>
            <form method="post" action="<?= url('/login/redefinir') ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <div class="mb-3">
                    <label class="form-label">Nova senha</label>
                    <input type="password" name="senha" id="rsSenha" class="form-control" required autofocus>
                    <div id="rsSenhaChecklist" class="mt-2"></div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Confirmar nova senha</label>
                    <input type="password" name="confirmacao" id="rsConfirmacao" class="form-control" required>
                    <div id="rsSenhaMatch"></div>
                </div>

                <button type="submit" class="btn btn-primary w-100 auth-btn">
                    <i class="bi bi-check-lg"></i> Redefinir senha
                </button>
            </form>

            <script src="<?= url('/assets/js/senha-ui.js') ?>"></script>
            <script>
            RdSenhaUI.aplicar({
                campoSenha: 'rsSenha',
                campoConfirmacao: 'rsConfirmacao',
                checklistContainer: 'rsSenhaChecklist',
                matchContainer: 'rsSenhaMatch',
                politica: <?= json_encode($politica) ?>,
                dadosObvios: <?= json_encode($dadosObvios) ?>
            });
            </script>
        <?php endif; ?>

        <div class="text-center mt-4">
            <a href="<?= url('/login') ?>" class="auth-voltar"><i class="bi bi-arrow-left"></i> Voltar pro login</a>
        </div>
    </div>
</div>

</body>
</html>
