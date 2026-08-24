<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-building me-1"></i> Dados da Empresa</h4>
    <small class="text-muted">Usados no código de patrimônio dos ativos (ex: <code>SIGLA-UNIDADE-PC-000001</code>) e no rodapé das etiquetas impressas.</small>
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
        <strong class="d-block mb-1">Unidades Existentes</strong>
        <div class="form-text mb-3">
            Filiais/sites da empresa -- entram no código do patrimônio (<code>SIGLA-UNIDADE-TIPO-000001</code>) e, mais adiante, na
            abertura de chamados. Empresa com uma sede só precisa de 1 unidade cadastrada; quem tem várias filiais cadastra uma linha por filial.
        </div>

        <form method="post" action="<?= url('/administracao/empresa/unidade-novo') ?>" class="d-flex gap-2 mb-3">
            <input type="text" name="nome" class="form-control form-control-sm" placeholder="Ex: Filial Nordeste" required>
            <input type="text" name="sigla" class="form-control form-control-sm font-monospace text-uppercase" style="max-width:110px" maxlength="6" placeholder="NE" required>
            <button class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-plus-lg"></i> Adicionar</button>
        </form>

        <?php if (empty($unidades)): ?>
            <p class="text-muted small mb-0">Nenhuma unidade cadastrada ainda.</p>
        <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($unidades as $u): ?>
                    <li class="list-group-item px-0">
                        <div class="d-flex justify-content-between align-items-center linha-view-unidade">
                            <span>
                                <?= htmlspecialchars($u['nome']) ?>
                                <span class="badge text-bg-light border font-monospace ms-1"><?= htmlspecialchars($u['sigla']) ?></span>
                                <?php if ($u['padrao']): ?>
                                    <span class="badge text-bg-light border ms-1">Padrão</span>
                                <?php endif; ?>
                            </span>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-secondary botao-editar-unidade"><i class="bi bi-pencil"></i></button>
                                <?php if (!$u['padrao']): ?>
                                    <form method="post" action="<?= url('/administracao/empresa/unidade-excluir') ?>"
                                          onsubmit="return confirm('Excluir a unidade &quot;<?= htmlspecialchars(addslashes($u['nome'])) ?>&quot;?');">
                                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <form method="post" action="<?= url('/administracao/empresa/unidade-editar') ?>" class="linha-edit-unidade d-none gap-2 mt-1">
                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <input type="text" name="nome" class="form-control form-control-sm" value="<?= htmlspecialchars($u['nome']) ?>" required>
                            <input type="text" name="sigla" class="form-control form-control-sm font-monospace text-uppercase" style="max-width:110px" maxlength="6" value="<?= htmlspecialchars($u['sigla']) ?>" required>
                            <button class="btn btn-sm btn-primary text-nowrap">Salvar</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary botao-cancelar-edicao-unidade">Cancelar</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    document.querySelectorAll('.botao-editar-unidade').forEach(function (botao) {
        botao.addEventListener('click', function () {
            const li = botao.closest('li');
            li.querySelector('.linha-view-unidade').classList.add('d-none');
            const edicao = li.querySelector('.linha-edit-unidade');
            edicao.classList.remove('d-none');
            edicao.classList.add('d-flex');
            edicao.querySelector('input[name="nome"]').focus();
        });
    });

    document.querySelectorAll('.botao-cancelar-edicao-unidade').forEach(function (botao) {
        botao.addEventListener('click', function () {
            const li = botao.closest('li');
            const edicao = li.querySelector('.linha-edit-unidade');
            edicao.classList.add('d-none');
            edicao.classList.remove('d-flex');
            li.querySelector('.linha-view-unidade').classList.remove('d-none');
        });
    });
})();
</script>

<div class="card border-0 shadow-sm mt-3" style="max-width:560px">
    <div class="card-body">
        <label class="form-label">Logo do sistema</label>
        <div class="form-text mb-2">
            A imagem grande no topo do menu, acima de "Painel Administrativo" -- é a identidade visual do próprio RD Intranet
            (diferente da logo da empresa abaixo, que é do cliente). Opcional; sem enviar nada, fica a padrão.
            Redimensionada automaticamente pro padrão <strong>480&times;480px</strong> (mantendo a proporção, sem cortar).
        </div>

        <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
            <img src="<?= $logoSistemaConfigurada ? url('/administracao/empresa/logo-sistema') : url('/assets/img/logord.png') ?>" alt="Logo do sistema" style="max-height:60px;max-width:220px">
            <?php if ($logoSistemaConfigurada): ?>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="document.getElementById('formRemoverLogoSistema').submit()" title="Voltar pra padrão"><i class="bi bi-trash"></i></button>
            <?php endif; ?>
        </div>
        <?php if ($logoSistemaConfigurada): ?>
            <form method="post" action="<?= url('/administracao/empresa/logo-sistema/remover') ?>" id="formRemoverLogoSistema" class="d-none"></form>
        <?php endif; ?>

        <form method="post" action="<?= url('/administracao/empresa/logo-sistema/upload') ?>" enctype="multipart/form-data" class="d-flex gap-2" id="formUploadLogoSistema">
            <input type="file" name="logo" id="inputLogoSistema" accept=".jpg,.jpeg,.png" class="form-control form-control-sm" required>
            <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="bi bi-upload"></i> <?= $logoSistemaConfigurada ? 'Trocar' : 'Enviar' ?></button>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3" style="max-width:560px">
    <div class="card-body">
        <label class="form-label">Logo da empresa</label>
        <div class="form-text mb-2">
            Aparece pequena, abaixo de "RD Intranet / Painel Administrativo" no menu lateral. Opcional.
            Redimensionada automaticamente pro padrão <strong>320&times;120px</strong> (mantendo a proporção, sem cortar) antes de enviar -- PNG com fundo transparente funciona melhor.
        </div>

        <?php if ($logoConfigurada): ?>
            <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
                <img src="<?= url('/administracao/empresa/logo') ?>" alt="Logo da empresa" style="max-height:36px;max-width:180px">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="document.getElementById('formRemoverLogoEmpresa').submit()"><i class="bi bi-trash"></i></button>
            </div>
            <form method="post" action="<?= url('/administracao/empresa/logo/remover') ?>" id="formRemoverLogoEmpresa" class="d-none"></form>
        <?php endif; ?>

        <form method="post" action="<?= url('/administracao/empresa/logo/upload') ?>" enctype="multipart/form-data" class="d-flex gap-2" id="formUploadLogoEmpresa">
            <input type="file" name="logo" id="inputLogoEmpresa" accept=".jpg,.jpeg,.png" class="form-control form-control-sm" required>
            <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="bi bi-upload"></i> <?= $logoConfigurada ? 'Trocar' : 'Enviar' ?></button>
        </form>
    </div>
</div>

<script>
function kbConfigurarRedimensionamentoLogo(idInput, larguraMax, alturaMax) {
    var input = document.getElementById(idInput);
    if (!input || typeof HTMLCanvasElement === 'undefined') return;

    input.addEventListener('change', function () {
        var arquivo = input.files[0];
        if (!arquivo) return;

        var leitor = new FileReader();
        leitor.onload = function (eLeitor) {
            var imagem = new Image();
            imagem.onload = function () {
                var escala = Math.min(1, larguraMax / imagem.width, alturaMax / imagem.height);
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
}

kbConfigurarRedimensionamentoLogo('inputLogoSistema', 480, 480);
kbConfigurarRedimensionamentoLogo('inputLogoEmpresa', 320, 120);
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Dados da Empresa';

require __DIR__ . '/../layouts/main.php';
