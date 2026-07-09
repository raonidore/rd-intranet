<?php

use App\Components\Alert;

ob_start();
?>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-upload"></i> Importar certificado próprio</h5>
        <small class="text-muted">Certificado comprado ou emitido pela CA da sua empresa.</small>
    </div>

    <div class="card-body">
        <div class="alert alert-info small">
            <i class="bi bi-info-circle"></i>
            O certificado e a chave são validados (precisam corresponder entre si) antes de serem instalados —
            se não baterem, nada é alterado. A chave privada precisa estar sem senha (não protegida por passphrase).
        </div>

        <form method="post" action="<?= url('/infraestrutura/certificado/importar') ?>" enctype="multipart/form-data"
              onsubmit="return confirm('Instalar este certificado e ativar HTTPS com ele?');">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Certificado (.crt / .pem)</label>
                    <input type="file" name="certificado" class="form-control" required accept=".crt,.pem,.cer">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Chave privada (.key)</label>
                    <input type="file" name="chave" class="form-control" required accept=".key,.pem">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Cadeia intermediária (opcional)</label>
                    <input type="file" name="cadeia" class="form-control" accept=".crt,.pem,.ca-bundle">
                    <small class="text-muted">Se a sua CA forneceu um arquivo separado de intermediárias.</small>
                </div>
            </div>

            <div class="d-flex justify-content-between mt-3">
                <a href="<?= url('/infraestrutura/certificado') ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Instalar e ativar HTTPS
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Importar Certificado';

require __DIR__ . '/../layouts/main.php';
