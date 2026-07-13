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

<?php
$conteudo = ob_get_clean();
$titulo = 'Dados da Empresa';

require __DIR__ . '/../layouts/main.php';
