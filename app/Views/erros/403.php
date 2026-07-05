<?php
ob_start();
?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-5">
                <i class="bi bi-shield-lock text-danger" style="font-size:3rem"></i>
                <h4 class="mt-3">Acesso negado</h4>
                <p class="text-muted">
                    Seu usuário não tem permissão para acessar este módulo.
                    Fale com um administrador se precisar de acesso.
                </p>
                <a href="<?= url('/dashboard') ?>" class="btn btn-primary">
                    <i class="bi bi-house"></i> Voltar ao início
                </a>
            </div>
        </div>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Acesso negado';
require __DIR__ . '/../layouts/main.php';
