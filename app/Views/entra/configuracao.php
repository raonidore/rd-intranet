<?php
ob_start();

use App\Components\Alert;
use App\Components\Badge;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-microsoft me-1"></i> Microsoft Entra - Configuração</h4>
    <small class="text-muted"><a href="<?= url('/entra/dashboard') ?>"><i class="bi bi-arrow-left"></i> Dashboard</a></small>
</div>

<div class="card border-0 shadow-sm mb-3" style="max-width:720px">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <strong>Status</strong>
            <?= $configurado ? Badge::make('Configurado', 'success') : Badge::make('Não configurado', 'secondary') ?>
        </div>

        <form method="post" action="<?= url('/entra/configuracao/salvar') ?>" class="row g-3">
            <div class="col-12">
                <label class="form-label">Tenant ID</label>
                <input type="text" name="tenant_id" class="form-control font-monospace" required
                       value="<?= htmlspecialchars($tenantIdAtual) ?>" placeholder="ex: 11111111-2222-3333-4444-555555555555">
            </div>
            <div class="col-12">
                <label class="form-label">Client ID (Application ID)</label>
                <input type="text" name="client_id" class="form-control font-monospace" required
                       value="<?= htmlspecialchars($clientIdAtual) ?>" placeholder="ex: 66666666-7777-8888-9999-000000000000">
            </div>
            <div class="col-12">
                <label class="form-label">Client Secret</label>
                <input type="password" name="client_secret" class="form-control font-monospace"
                       placeholder="<?= $configurado ? '••••••••  (deixe em branco pra manter)' : 'valor do secret gerado no Entra' ?>">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
                <?php if ($configurado): ?>
                    <button type="button" class="btn btn-outline-danger" id="botaoRemoverConfigEntra"><i class="bi bi-trash"></i> Remover configuração</button>
                <?php endif; ?>
            </div>
        </form>
        <form method="post" action="<?= url('/entra/configuracao/remover') ?>" id="formRemoverConfigEntra" class="d-none"></form>
    </div>
</div>

<div class="card border-0 shadow-sm" style="max-width:720px">
    <div class="card-body">
        <strong><i class="bi bi-list-ol"></i> Como criar o App Registration no Entra do cliente</strong>
        <p class="text-muted small mt-2 mb-2">
            Feito uma vez por tenant, direto no <a href="https://entra.microsoft.com" target="_blank">portal do Entra</a>
            (não expõe nada pro cliente final -- essa credencial fica só aqui, cifrada).
        </p>
        <ol class="small text-muted mb-0">
            <li class="mb-2"><strong>Identity &gt; Applications &gt; App registrations &gt; New registration.</strong>
                Dê um nome (ex: "RD Intranet"), deixe "Accounts in this organizational directory only".</li>
            <li class="mb-2">Anote o <strong>Application (client) ID</strong> e o <strong>Directory (tenant) ID</strong>
                mostrados na tela de visão geral -- são o Client ID e Tenant ID acima.</li>
            <li class="mb-2"><strong>API permissions &gt; Add a permission &gt; Microsoft Graph &gt; Application permissions</strong>
                (não "Delegated") e adicione: <code>User.ReadWrite.All</code>, <code>Directory.Read.All</code> e
                <code>Organization.Read.All</code>. Pra usar a tela de <strong>Dispositivos (Intune)</strong>, adicione
                também <code>DeviceManagementManagedDevices.Read.All</code> e
                <code>DeviceManagementManagedDevices.ReadWrite.All</code> (só necessário se for usar aquela tela).</li>
            <li class="mb-2">Clique em <strong>"Grant admin consent for [tenant]"</strong> -- sem isso as permissões
                ficam pendentes e nenhuma chamada funciona.</li>
            <li class="mb-0"><strong>Certificates &amp; secrets &gt; Client secrets &gt; New client secret.</strong>
                Copie o <em>valor</em> gerado na hora (some depois de sair da tela) e cole no campo Client Secret acima.</li>
        </ol>
    </div>
</div>

<script>
(function () {
    const botao = document.getElementById('botaoRemoverConfigEntra');
    if (!botao) return;

    botao.addEventListener('click', function () {
        if (confirm('Remover a configuração do tenant? O módulo Entra para de funcionar até ser configurado de novo.')) {
            document.getElementById('formRemoverConfigEntra').submit();
        }
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Microsoft Entra - Configuração';

require __DIR__ . '/../layouts/main.php';
