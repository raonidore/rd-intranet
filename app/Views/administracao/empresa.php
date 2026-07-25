<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-building me-1"></i> Dados da Empresa</h4>
    <small class="text-muted">Usados no código de patrimônio dos ativos (ex: <code>SIGLA-PC-000001</code>) e no rodapé das etiquetas impressas.</small>
</div>

<div class="card border-0 shadow-sm" style="max-width:560px">
    <div class="card-body">
        <form method="post" action="<?= url('/administracao/empresa/salvar') ?>">
            <div class="mb-3">
                <label class="form-label">Nome da empresa</label>
                <input type="text" name="nome" class="form-control" required value="<?= htmlspecialchars($nome) ?>" placeholder="Ex: RD Tecnologia">
            </div>

            <div class="mb-3">
                <label class="form-label">Sigla (usada no código dos ativos)</label>
                <input type="text" name="sigla" class="form-control font-monospace text-uppercase" required
                       maxlength="6" style="max-width:160px" value="<?= htmlspecialchars($sigla) ?>" placeholder="RD">
                <div class="form-text">2 a 6 letras, sem números ou símbolos. Só afeta ativos cadastrados <strong>a partir de agora</strong> -- os já existentes mantêm o código original.</div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3" style="max-width:560px">
    <div class="card-body">
        <label class="form-label">Logo da empresa</label>
        <div class="form-text mb-2">Aparece pequena, abaixo de "RD Intranet / Painel Administrativo" no menu lateral. Opcional.</div>

        <?php if ($logoConfigurada): ?>
            <div class="d-flex justify-content-between align-items-center border rounded p-2">
                <img src="<?= url('/administracao/empresa/logo') ?>" alt="Logo da empresa" style="max-height:36px;max-width:180px">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="document.getElementById('formRemoverLogoEmpresa').submit()"><i class="bi bi-trash"></i></button>
            </div>
            <form method="post" action="<?= url('/administracao/empresa/logo/remover') ?>" id="formRemoverLogoEmpresa" class="d-none"></form>
        <?php else: ?>
            <form method="post" action="<?= url('/administracao/empresa/logo/upload') ?>" enctype="multipart/form-data" class="d-flex gap-2" id="formUploadLogoEmpresa">
                <input type="file" name="logo" id="inputLogoEmpresa" accept=".jpg,.jpeg,.png" class="form-control form-control-sm" required>
                <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="bi bi-upload"></i> Enviar</button>
            </form>
            <div class="form-text mt-1">Redimensionada automaticamente pro padrão <strong>320&times;120px</strong> (mantendo a proporção, sem cortar) antes de enviar. PNG com fundo transparente funciona melhor.</div>
        <?php endif; ?>
    </div>
</div>

<?php if (!$logoConfigurada): ?>
<script>
(function () {
    var LARGURA_MAX = 320;
    var ALTURA_MAX = 120;

    var input = document.getElementById('inputLogoEmpresa');
    if (!input || typeof HTMLCanvasElement === 'undefined') return;

    input.addEventListener('change', function () {
        var arquivo = input.files[0];
        if (!arquivo) return;

        var leitor = new FileReader();
        leitor.onload = function (eLeitor) {
            var imagem = new Image();
            imagem.onload = function () {
                var escala = Math.min(1, LARGURA_MAX / imagem.width, ALTURA_MAX / imagem.height);
                var largura = Math.max(1, Math.round(imagem.width * escala));
                var altura = Math.max(1, Math.round(imagem.height * escala));

                var canvas = document.createElement('canvas');
                canvas.width = largura;
                canvas.height = altura;
                canvas.getContext('2d').drawImage(imagem, 0, 0, largura, altura);

                canvas.toBlob(function (blob) {
                    if (!blob) return;

                    var redimensionada = new File([blob], 'logo.png', { type: 'image/png' });
                    var transferencia = new DataTransfer();
                    transferencia.items.add(redimensionada);
                    input.files = transferencia.files;
                }, 'image/png');
            };
            imagem.src = eLeitor.target.result;
        };
        leitor.readAsDataURL(arquivo);
    });
})();
</script>
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Dados da Empresa';

require __DIR__ . '/../layouts/main.php';
