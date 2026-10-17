<?php
use App\Components\Alert;
use App\Components\Badge;
use App\Services\EntraService;

ob_start();

$faltando = $permissoes === null ? [] : array_keys(array_diff_key(EntraService::PERMISSOES, array_flip($permissoes)));
$diasSecret = $secretVence ? (int)floor((strtotime($secretVence) - time()) / 86400) : null;
?>

<style>
.guia-entra h6 { margin-top: 1.2rem; }
.guia-entra ol > li { margin-bottom: .75rem; }
.guia-entra .table { font-size: .82rem; }
.guia-entra code { font-size: .8rem; }
</style>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-microsoft me-1" style="color:#0078D4"></i> Microsoft Entra ID / Microsoft 365</h4>
    <small class="text-muted">
        <a href="<?= url('/administracao/integracoes') ?>"><i class="bi bi-arrow-left"></i> Integrações</a>
    </small>
</div>

<?= Alert::flash() ?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <strong><i class="bi bi-key"></i> App Registration do tenant</strong>
                    <?= $configurado ? Badge::make('Configurado', 'success') : Badge::make('Não configurado', 'secondary') ?>
                </div>

                <form method="post" action="<?= url('/administracao/integracoes/entra/salvar') ?>" class="row g-3">
                    <div class="col-12">
                        <label class="form-label">ID do diretório (locatário) -- Tenant ID</label>
                        <input type="text" name="tenant_id" class="form-control font-monospace" required
                               value="<?= htmlspecialchars($tenantIdAtual) ?>" placeholder="11111111-2222-3333-4444-555555555555">
                    </div>
                    <div class="col-12">
                        <label class="form-label">ID do aplicativo (cliente) -- Client ID</label>
                        <input type="text" name="client_id" class="form-control font-monospace" required
                               value="<?= htmlspecialchars($clientIdAtual) ?>" placeholder="66666666-7777-8888-9999-000000000000">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Valor do segredo do cliente -- Client Secret</label>
                        <input type="password" name="client_secret" class="form-control font-monospace" autocomplete="new-password"
                               placeholder="<?= $configurado ? '••••••••  (deixe em branco para manter)' : 'coluna "Valor" do segredo, não o "ID do segredo"' ?>">
                        <div class="form-text">Guardado cifrado. Cole a coluna <strong>Valor</strong> (ex.: <code>AbC8Q~...</code>), não o "ID do segredo".</div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
                        <?php if ($configurado): ?>
                            <button type="button" class="btn btn-outline-danger" id="botaoRemoverConfigEntra"><i class="bi bi-trash"></i> Remover</button>
                        <?php endif; ?>
                    </div>
                </form>
                <form method="post" action="<?= url('/administracao/integracoes/entra/remover') ?>" id="formRemoverConfigEntra" class="d-none"></form>
            </div>
        </div>

        <?php if ($configurado): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body small">
                    <strong><i class="bi bi-clipboard-check"></i> Conferência</strong>
                    <?php if ($permissoes === null): ?>
                        <div class="alert alert-danger small mt-2 mb-0">
                            <i class="bi bi-x-circle"></i> Não autenticou no tenant. Confira os 3 campos -- o erro mais comum é colar o
                            <em>ID do segredo</em> em vez do <em>Valor</em>, ou o segredo ter vencido.
                        </div>
                    <?php else: ?>
                        <div class="mt-2"><i class="bi bi-check-circle text-success"></i> Autenticação OK -- <?= count($permissoes) ?> permissão(ões) concedida(s).</div>
                        <?php if ($diasSecret !== null): ?>
                            <div class="<?= $diasSecret < 30 ? 'text-danger fw-semibold' : ($diasSecret < 90 ? 'text-warning' : '') ?>">
                                <i class="bi bi-calendar-event"></i> Segredo vence em <?= date('d/m/Y', strtotime($secretVence)) ?> (<?= $diasSecret ?> dias).
                                <?php if ($diasSecret < 90): ?>Gere um novo antes (passo 5) -- quando vencer, o módulo para.<?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($faltando): ?>
                            <div class="text-warning mt-1"><i class="bi bi-exclamation-triangle"></i> Faltam permissões (veja a tabela do passo 3).</div>
                        <?php else: ?>
                            <div><i class="bi bi-check-circle text-success"></i> Todas as permissões recomendadas concedidas.</div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm guia-entra">
            <div class="card-body small">
                <strong class="fs-6"><i class="bi bi-list-ol"></i> Passo a passo no tenant do cliente</strong>
                <p class="text-muted mt-1 mb-0">Feito uma vez por tenant, com uma conta de <strong>Administrador global</strong>. Os links abrem direto na tela certa.
                    Se o cliente tem várias unidades no mesmo tenant, tudo aqui vale para todas -- só os grupos são por unidade.</p>

                <h6><span class="badge text-bg-primary">Parte 1</span> App Registration (a chave que este portal usa)</h6>
                <ol class="text-muted">
                    <li><a href="https://entra.microsoft.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade" target="_blank">Registros de aplicativo</a>
                        &rarr; <strong>Novo registro</strong>. Nome: <code>RD.Intranet</code>; tipo de conta: <em>Contas somente neste diretório organizacional</em>;
                        sem URI de redirecionamento &rarr; <strong>Registrar</strong>.</li>
                    <li>Na <strong>Visão geral</strong> do app, copie o <strong>ID do aplicativo (cliente)</strong> e o <strong>ID do diretório (locatário)</strong>
                        para os campos ao lado.</li>
                    <li><strong>Permissões de API &rarr; Adicionar uma permissão &rarr; Microsoft Graph &rarr; Permissões de aplicativo</strong> (não "Delegadas").
                        Procure e marque cada uma:
                        <div class="table-responsive mt-2">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-light"><tr><th>Permissão</th><th>Para quê</th><?php if ($permissoes !== null): ?><th></th><?php endif; ?></tr></thead>
                                <tbody>
                                    <?php foreach (EntraService::PERMISSOES as $nome => [$paraQue, $essencial]): ?>
                                        <tr>
                                            <td class="text-nowrap"><code><?= $nome ?></code><?= $essencial ? ' <span class="badge text-bg-dark">essencial</span>' : '' ?></td>
                                            <td><?= htmlspecialchars($paraQue) ?></td>
                                            <?php if ($permissoes !== null): ?>
                                                <td class="text-center"><?= in_array($nome, $permissoes, true)
                                                    ? '<i class="bi bi-check-circle-fill text-success" title="Concedida"></i>'
                                                    : '<i class="bi bi-x-circle text-danger" title="Falta"></i>' ?></td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-1">Não adicione outras além dessas -- auditoria gosta de ver cada integração só com o que usa.</div>
                    </li>
                    <li>Ainda em Permissões de API, clique em <strong>Conceder consentimento do administrador para [empresa]</strong> e confirme.
                        Sem isso nenhuma permissão vale -- todas devem ficar com "Concedido para...".</li>
                    <li><strong>Certificados e segredos &rarr; Segredos do cliente &rarr; Novo segredo do cliente</strong>, validade de <strong>24 meses</strong>.
                        Copie a coluna <strong>Valor</strong> na hora (ela some depois) e cole ao lado. Anote a data de vencimento -- a Conferência ao lado avisa quando estiver perto.</li>
                </ol>

                <h6><span class="badge text-bg-primary">Parte 2</span> Preparar o tenant para o login nas máquinas</h6>
                <ol class="text-muted" start="6">
                    <li><a href="https://entra.microsoft.com/#view/Microsoft_AAD_Devices/DevicesMenuBlade/~/DeviceSettings/menuId~/null" target="_blank">Dispositivos &rarr; Configurações de dispositivo</a>:
                        <table class="table table-sm table-bordered mt-2 mb-0">
                            <tr><td>Os usuários podem ingressar dispositivos no Microsoft Entra</td><td><strong>Tudo</strong></td></tr>
                            <tr><td>Exigir autenticação multifator para registrar ou ingressar</td><td><strong>Não</strong> (facilita o ingresso das máquinas)</td></tr>
                            <tr><td>Número máximo de dispositivos por usuário</td><td>50</td></tr>
                            <tr><td>A função Administrador global é adicionada como administrador local</td><td><strong>Sim</strong> (a TI sempre entra)</td></tr>
                            <tr><td>O registro do usuário é adicionado como administrador local</td><td><strong>Nenhum</strong> (quem ingressa não vira admin)</td></tr>
                            <tr><td>Habilitar a Solução de Senha de Administrador Local (LAPS)</td><td><strong>Sim</strong></td></tr>
                            <tr><td>Restringir usuários de recuperar as chaves do BitLocker</td><td><strong>Sim</strong></td></tr>
                        </table>
                        &rarr; <strong>Salvar</strong>.</li>
                    <li><a href="https://intune.microsoft.com/#view/Microsoft_Intune_DeviceSettings/DevicesEnrollmentMenu/~/windowsEnrollment" target="_blank">Intune &rarr; Registro do Windows</a>
                        &rarr; <strong>Registro Automático</strong>:
                        <table class="table table-sm table-bordered mt-2 mb-0">
                            <tr><td>Escopo do usuário do MDM</td><td><strong>Tudo</strong> (sem isso a máquina não aparece no Intune)</td></tr>
                            <tr><td>Desabilitar o registro de MDM ao adicionar uma conta corporativa</td><td><strong>Sim</strong> (PC pessoal com Teams/Outlook não vira gerenciado)</td></tr>
                            <tr><td>Escopo do usuário do WIP</td><td>Nenhum</td></tr>
                        </table>
                        &rarr; <strong>Salvar</strong>.</li>
                    <li>Mesma página do Intune &rarr; <strong>Windows Hello para Empresas</strong> &rarr; <em>Configurar Windows Hello para Empresas</em> =
                        <strong>Desabilitado</strong> &rarr; Salvar. ("Não configurado" deixa o Windows pedir PIN e celular no primeiro login de cada pessoa em cada máquina;
                        os campos de PIN que aparecem podem ser ignorados.)</li>
                    <li><a href="https://admin.cloud.microsoft/?#/Settings/Services/:/Settings/L1/Reports" target="_blank">Admin center do M365 &rarr; Configurações da organização &rarr; Relatórios</a>:
                        desmarque <em>Exibir nomes de usuários, grupos e sites ocultos</em> -- senão os relatórios de uso vêm com nomes embaralhados.</li>
                </ol>

                <h6><span class="badge text-bg-primary">Parte 3</span> Aqui no portal</h6>
                <ol class="text-muted mb-0" start="10">
                    <li><a href="<?= url('/entra/grupos') ?>">Entra &rarr; Grupos</a>: um grupo por unidade (ex.: <em>NTO - Acesso às Máquinas</em>) com quem pode entrar nas máquinas dela.</li>
                    <li>Em cada máquina: Configurações &rarr; Contas &rarr; Acesso corporativo ou de estudante &rarr; <strong>Conectar</strong> &rarr;
                        <em>Ingressar este dispositivo no Microsoft Entra ID</em>, com a conta da TI (se houver uma conta @empresa já conectada ali, <em>Desconectar</em> antes). Reiniciar.</li>
                    <li><a href="<?= url('/entra/acesso-maquinas') ?>">Entra &rarr; Acesso às Máquinas</a>: aplique o grupo da unidade nas máquinas que já entraram no Entra e desative as contas locais antigas.</li>
                    <li><a href="<?= url('/seguranca/trilha-acesso') ?>">Segurança &rarr; Trilha de acesso</a>: quem entrou em qual máquina e quando, com exportação em PDF/Excel para auditoria.</li>
                </ol>
            </div>
        </div>
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
$titulo = 'Microsoft Entra ID / Microsoft 365';

require __DIR__ . '/../layouts/main.php';
