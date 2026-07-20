<?php
ob_start();

use App\Components\Alert;
use App\Components\Badge;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-laptop me-1"></i> Microsoft Entra - Dispositivos (Intune)</h4>
    <small class="text-muted"><a href="<?= url('/entra/dashboard') ?>"><i class="bi bi-arrow-left"></i> Dashboard</a></small>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong><i class="bi bi-question-circle"></i> Como entrar uma máquina no domínio</strong>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#ajudaEntrarDominio">
            Ver passo a passo
        </button>
    </div>
    <div class="collapse" id="ajudaEntrarDominio">
        <div class="card-body">
            <p class="small text-muted">
                <strong>Entrar no domínio</strong> (identidade) e <strong>virar gerenciado pelo Intune</strong>
                (dispositivo) são coisas diferentes -- os dois passos abaixo cobrem cada uma.
            </p>

            <strong class="small">1. Uma vez por tenant (pré-requisito, feito no portal do Entra)</strong>
            <p class="small text-muted mb-1">
                Sem isso, nenhuma máquina vira gerenciada automaticamente -- nem entrando manualmente, nem pelo
                botão "Forçar inscrição agora" desta tela.
            </p>
            <ol class="small text-muted">
                <li>Acesse o <a href="https://entra.microsoft.com" target="_blank">portal do Entra</a> &gt;
                    <strong>Identity &gt; Mobility (MDM and MAM) &gt; Microsoft Intune</strong>.</li>
                <li>Em <strong>"MDM user scope"</strong>, troque de <code>None</code> (padrão) pra <code>All</code>
                    (todo mundo) ou <code>Some</code> (mirando um grupo específico).</li>
            </ol>

            <strong class="small">2. Em cada máquina (uma vez por máquina, ou via pacote de provisionamento acima)</strong>
            <ol class="small text-muted mb-0">
                <li><kbd>Win</kbd> + <kbd>I</kbd> &gt; <strong>Contas &gt; Acesso corporativo ou de estudante &gt; Conectar</strong>.</li>
                <li>Não digite o e-mail direto na caixa principal -- isso só adiciona uma "conta de trabalho", sem
                    entrar no domínio de verdade. Clique no <strong>link pequeno embaixo da caixa</strong>
                    ("Ingressar neste dispositivo no Microsoft Entra ID" / "Join this device to Microsoft Entra ID").</li>
                <li>Faça login com o <strong>UPN completo</strong> de um usuário já criado (ex:
                    <code>usuario@enzilab.net</code>) + senha (+ MFA se estiver habilitado). Não precisa digitar o
                    domínio em nenhum outro lugar -- o Windows descobre o tenant sozinho a partir do que vem depois
                    do <code>@</code>.</li>
                <li>Reinicie quando pedir. Pra confirmar que funcionou: <strong>Contas &gt; Acesso corporativo</strong>
                    deve mostrar "Conectado a [tenant] Entra ID", ou rode <code>dsregcmd /status</code> no cmd e
                    procure por <code>AzureAdJoined: YES</code>.</li>
            </ol>
        </div>
    </div>
</div>

<?php if (!$configurado): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-plug display-6 text-muted d-block mb-3"></i>
            <p class="text-muted mb-3">Módulo ainda não configurado.</p>
            <a href="<?= url('/entra/configuracao') ?>" class="btn btn-primary"><i class="bi bi-gear"></i> Configurar</a>
        </div>
    </div>
<?php else: ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><strong>Inscrição de máquinas no Intune</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <p class="small text-muted">
                        Máquina já entrou no domínio Entra (manualmente ou pelo pacote ao lado) mas ainda não
                        apareceu no Intune? Força a inscrição na hora, sem esperar o ciclo automático.
                    </p>
                    <form method="post" action="<?= url('/entra/dispositivos/forcar-enrollment') ?>" id="formForcarEnrollment">
                        <div class="border rounded p-2 mb-2" style="max-height:220px; overflow-y:auto">
                            <?php if (empty($computadores)): ?>
                                <p class="text-muted small mb-0">Nenhum computador com o agente instalado.</p>
                            <?php else: ?>
                                <?php foreach ($computadores as $c): ?>
                                    <div class="form-check">
                                        <input class="form-check-input campo-ativo-enrollment" type="checkbox" name="ativos[]" value="<?= (int)$c['id'] ?>" id="enroll-ativo-<?= (int)$c['id'] ?>">
                                        <label class="form-check-label small" for="enroll-ativo-<?= (int)$c['id'] ?>">
                                            <?= htmlspecialchars($c['nome']) ?>
                                            <span class="text-muted font-monospace">(<?= htmlspecialchars($c['codigo_patrimonio']) ?>)</span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="btn btn-sm btn-outline-primary" id="botaoForcarEnrollment">
                            <i class="bi bi-arrow-repeat"></i> Forçar inscrição agora
                        </button>
                    </form>
                </div>

                <div class="col-md-6">
                    <p class="small text-muted">
                        Máquina nova, sem domínio nenhum configurado? Envia e instala em segundo plano um pacote de
                        provisionamento (<code>.ppkg</code>) que junta "entrar no domínio" + "inscrever no Intune"
                        numa tacada só. O pacote é gerado <strong>fora do portal</strong>, uma vez por tenant, via
                        Windows Configuration Designer (assistente "Provision Entra ID Bulk join", com MFA) — não
                        existe API suportada pra automatizar essa parte.
                    </p>

                    <?php if ($provisioningInfo): ?>
                        <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
                            <span class="small">
                                <i class="bi bi-file-earmark-binary"></i> <?= htmlspecialchars($provisioningInfo['nome']) ?>
                                <span class="text-muted">(enviado em <?= htmlspecialchars($provisioningInfo['enviado_em']) ?>)</span>
                            </span>
                            <button type="button" class="btn btn-sm btn-outline-danger" id="botaoRemoverPpkg"><i class="bi bi-trash"></i></button>
                        </div>
                        <form method="post" action="<?= url('/entra/provisionamento/remover') ?>" id="formRemoverPpkg" class="d-none"></form>
                    <?php else: ?>
                        <form method="post" action="<?= url('/entra/provisionamento/upload') ?>" enctype="multipart/form-data" class="d-flex gap-2 mb-2">
                            <input type="file" name="pacote" accept=".ppkg" class="form-control form-control-sm" required>
                            <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="bi bi-upload"></i> Enviar</button>
                        </form>
                    <?php endif; ?>

                    <form method="post" action="<?= url('/entra/provisionamento/enviar') ?>" id="formAplicarPpkg">
                        <div class="border rounded p-2 mb-2" style="max-height:220px; overflow-y:auto">
                            <?php if (empty($computadores)): ?>
                                <p class="text-muted small mb-0">Nenhum computador com o agente instalado.</p>
                            <?php else: ?>
                                <?php foreach ($computadores as $c): ?>
                                    <div class="form-check">
                                        <input class="form-check-input campo-ativo-ppkg" type="checkbox" name="ativos[]" value="<?= (int)$c['id'] ?>" id="ppkg-ativo-<?= (int)$c['id'] ?>">
                                        <label class="form-check-label small" for="ppkg-ativo-<?= (int)$c['id'] ?>">
                                            <?= htmlspecialchars($c['nome']) ?>
                                            <span class="text-muted font-monospace">(<?= htmlspecialchars($c['codigo_patrimonio']) ?>)</span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary" id="botaoAplicarPpkg" <?= $provisioningConfigurado ? '' : 'disabled' ?>>
                            <i class="bi bi-box-arrow-in-down"></i> Aplicar em lote
                        </button>
                        <?php if (!$provisioningConfigurado): ?>
                            <span class="text-muted small">envie o pacote acima primeiro</span>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if ($faltaPermissaoIntune): ?>
        <div class="alert alert-warning">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <strong><i class="bi bi-exclamation-triangle"></i> Permissão do Intune ainda não liberada nesse tenant.</strong>
                    <p class="small mb-0 mt-1">
                        O App Registration existe e funciona pra usuários/licenças, mas ainda não tem as permissões
                        de dispositivo -- comum quando o Entra foi configurado antes dessa tela existir.
                    </p>
                </div>
                <button class="btn btn-sm btn-outline-warning text-nowrap" type="button" data-bs-toggle="collapse" data-bs-target="#comoResolverPermissaoIntune">
                    <i class="bi bi-question-circle"></i> Como resolver
                </button>
            </div>
            <div class="collapse mt-2" id="comoResolverPermissaoIntune">
                <hr>
                <ol class="small mb-2">
                    <li>Vá no <a href="https://entra.microsoft.com" target="_blank">portal do Entra</a> &gt; Identity &gt; Applications &gt; App registrations &gt; ache o app do RD Intranet.</li>
                    <li><strong>API permissions &gt; Add a permission &gt; Microsoft Graph &gt; Application permissions</strong> e adicione
                        <code>DeviceManagementManagedDevices.Read.All</code> e <code>DeviceManagementManagedDevices.ReadWrite.All</code>.</li>
                    <li>Clique em <strong>"Grant admin consent for [tenant]"</strong> -- sem isso a permissão fica pendente e continua dando esse erro.</li>
                </ol>
                <a href="<?= url('/entra/configuracao') ?>" class="small"><i class="bi bi-arrow-right-short"></i> Ver o passo a passo completo em Configuração</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Dispositivos gerenciados pelo Intune</strong>
            <span class="text-muted small"><?= count($dispositivosIntune) ?> dispositivo(s)</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($dispositivosIntune)): ?>
                <p class="text-muted p-3 mb-0">Nenhum dispositivo inscrito no Intune ainda.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Usuário</th>
                                <th>SO</th>
                                <th>Conformidade</th>
                                <th>Último sync</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dispositivosIntune as $d): ?>
                                <?php
                                    $deviceId = $d['id'];
                                    $nome = $d['deviceName'] ?? '(sem nome)';
                                    $conforme = ($d['complianceState'] ?? '') === 'compliant';
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($nome) ?></td>
                                    <td class="small"><?= htmlspecialchars($d['userPrincipalName'] ?? '—') ?></td>
                                    <td class="small"><?= htmlspecialchars(($d['operatingSystem'] ?? '') . ' ' . ($d['osVersion'] ?? '')) ?></td>
                                    <td><?= $conforme ? Badge::make('Conforme', 'success') : Badge::make($d['complianceState'] ?? 'desconhecido', 'secondary') ?></td>
                                    <td class="small text-muted"><?= htmlspecialchars(data_br($d['lastSyncDateTime'] ?? null, 'd/m/Y H:i')) ?></td>
                                    <td class="text-end">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">Ações</button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <form method="post" action="<?= url('/entra/dispositivos/sincronizar') ?>" class="form-acao-dispositivo" data-confirmacao="Sincronizar este dispositivo agora?">
                                                        <input type="hidden" name="device_id" value="<?= htmlspecialchars($deviceId) ?>">
                                                        <input type="hidden" name="nome" value="<?= htmlspecialchars($nome) ?>">
                                                        <button type="submit" class="dropdown-item"><i class="bi bi-arrow-repeat"></i> Sincronizar</button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form method="post" action="<?= url('/entra/dispositivos/reiniciar') ?>" class="form-acao-dispositivo" data-confirmacao="Reiniciar este dispositivo? O usuário perde o trabalho não salvo.">
                                                        <input type="hidden" name="device_id" value="<?= htmlspecialchars($deviceId) ?>">
                                                        <input type="hidden" name="nome" value="<?= htmlspecialchars($nome) ?>">
                                                        <button type="submit" class="dropdown-item"><i class="bi bi-bootstrap-reboot"></i> Reiniciar</button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form method="post" action="<?= url('/entra/dispositivos/bloquear') ?>" class="form-acao-dispositivo" data-confirmacao="Bloquear a tela deste dispositivo?">
                                                        <input type="hidden" name="device_id" value="<?= htmlspecialchars($deviceId) ?>">
                                                        <input type="hidden" name="nome" value="<?= htmlspecialchars($nome) ?>">
                                                        <button type="submit" class="dropdown-item"><i class="bi bi-lock"></i> Bloquear tela</button>
                                                    </form>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="post" action="<?= url('/entra/dispositivos/retirar') ?>" class="form-acao-dispositivo" data-confirmacao="Retirar este dispositivo do Intune? Remove dados e apps corporativos da máquina (a máquina em si continua funcionando, só deixa de ser gerenciada). Essa ação não pode ser desfeita por aqui.">
                                                        <input type="hidden" name="device_id" value="<?= htmlspecialchars($deviceId) ?>">
                                                        <input type="hidden" name="nome" value="<?= htmlspecialchars($nome) ?>">
                                                        <button type="submit" class="dropdown-item text-danger"><i class="bi bi-eject"></i> Retirar do Intune</button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

<script>
(function () {
    function algumMarcado(seletor) {
        return Array.from(document.querySelectorAll(seletor)).some(function (c) { return c.checked; });
    }

    const formForcar = document.getElementById('formForcarEnrollment');
    if (formForcar) {
        formForcar.addEventListener('submit', function (e) {
            if (!algumMarcado('.campo-ativo-enrollment')) {
                e.preventDefault();
                alert('Selecione ao menos uma máquina.');
                return;
            }
            if (!confirm('Forçar a inscrição no Intune nas máquinas selecionadas?')) {
                e.preventDefault();
            }
        });
    }

    const formPpkg = document.getElementById('formAplicarPpkg');
    if (formPpkg) {
        formPpkg.addEventListener('submit', function (e) {
            if (!algumMarcado('.campo-ativo-ppkg')) {
                e.preventDefault();
                alert('Selecione ao menos uma máquina.');
                return;
            }
            if (!confirm('Aplicar o pacote de provisionamento nas máquinas selecionadas? Elas vão entrar no domínio da empresa e ficar gerenciadas pelo Intune.')) {
                e.preventDefault();
            }
        });
    }

    const botaoRemoverPpkg = document.getElementById('botaoRemoverPpkg');
    if (botaoRemoverPpkg) {
        botaoRemoverPpkg.addEventListener('click', function () {
            if (confirm('Remover o pacote de provisionamento salvo? Máquinas novas deixam de poder ser inscritas em lote até um novo envio.')) {
                document.getElementById('formRemoverPpkg').submit();
            }
        });
    }

    document.querySelectorAll('.form-acao-dispositivo').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!confirm(form.dataset.confirmacao || 'Confirma essa ação?')) {
                e.preventDefault();
            }
        });
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Microsoft Entra - Dispositivos';

require __DIR__ . '/../layouts/main.php';
