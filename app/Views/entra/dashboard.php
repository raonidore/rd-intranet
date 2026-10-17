<?php
ob_start();

use App\Components\Alert;
use App\Services\AcessoMaquinaService;
use App\Services\EntraService;

if ($configurado) {
    $licencas = array_sum(array_map(fn($s) => (int)($s['prepaidUnits']['enabled'] ?? 0), $skus));
    $licencasUsadas = array_sum(array_map(fn($s) => (int)($s['consumedUnits'] ?? 0), $skus));
    $noEntra = $dispositivos === null ? null : array_values(array_filter($dispositivos, fn($d) => ($d['trustType'] ?? '') === 'AzureAd'));
    $soRegistradas = $dispositivos === null ? [] : array_values(array_filter($dispositivos, fn($d) => ($d['trustType'] ?? '') === 'Workplace'));
    $incidentes = $avisos === null ? [] : array_values(array_filter($avisos, fn($a) => ($a['classification'] ?? '') === 'incident'));
    $diasSecret = $secretVence ? (int)floor((strtotime($secretVence) - time()) / 86400) : null;
    $faltando = $permissoes === null ? [] : array_keys(array_diff_key(EntraService::PERMISSOES, array_flip($permissoes)));
}

/** Card de número que leva pra uma tela. */
$kpi = function (string $href, string $icone, string $cor, string $valor, string $rotulo, string $sub = '') {
    ?>
    <div class="col-6 col-lg-3">
        <a href="<?= $href ?>" class="card border-0 shadow-sm h-100 text-decoration-none text-dark kpi-entra">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fs-3 fw-bold"><?= $valor ?></div>
                        <div class="small fw-semibold"><?= $rotulo ?></div>
                        <?php if ($sub !== ''): ?><div class="text-muted small"><?= $sub ?></div><?php endif; ?>
                    </div>
                    <i class="bi <?= $icone ?> fs-2 text-<?= $cor ?>"></i>
                </div>
            </div>
        </a>
    </div>
    <?php
};
?>

<style>
.kpi-entra { transition: transform .15s ease, box-shadow .15s ease; }
.kpi-entra:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,.08) !important; }
.atalho-entra { display:flex; align-items:center; gap:.6rem; padding:.6rem .8rem; border-radius:8px; text-decoration:none; color:inherit; }
.atalho-entra:hover { background: var(--bs-tertiary-bg, #f3f4f6); }
.atalho-entra i { font-size:1.3rem; }
</style>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-microsoft me-1"></i> Microsoft Entra</h4>
        <small class="text-muted">Usuários, licenças, máquinas e segurança do Microsoft 365 do cliente<?php if ($configurado): ?> -- dados de <?= date('H:i', $em) ?><?php endif; ?>.</small>
    </div>
    <div class="d-flex gap-2">
        <?php if ($configurado): ?>
            <a href="<?= url('/entra/dashboard') ?>?atualizar=1" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-clockwise"></i> Atualizar</a>
        <?php endif; ?>
        <?php if (\App\Services\PermissionService::ehAdmin()): ?>
            <a href="<?= url('/administracao/integracoes/entra') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-gear"></i> Configuração</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!$configurado): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-plug display-6 text-muted d-block mb-3"></i>
            <p class="text-muted mb-3">Módulo ainda não configurado -- é preciso um App Registration no tenant do cliente.</p>
            <a href="<?= url('/administracao/integracoes/entra') ?>" class="btn btn-primary"><i class="bi bi-gear"></i> Configurar em Integrações</a>
        </div>
    </div>
<?php else: ?>

    <?php if ($permissoes === null): ?>
        <div class="alert alert-danger"><i class="bi bi-x-circle"></i> O portal não está conseguindo autenticar no tenant -- confira em
            <a href="<?= url('/administracao/integracoes/entra') ?>">Integrações &gt; Microsoft Entra</a> (segredo vencido ou trocado).</div>
    <?php endif; ?>
    <?php if ($diasSecret !== null && $diasSecret < 60): ?>
        <div class="alert alert-<?= $diasSecret < 15 ? 'danger' : 'warning' ?>"><i class="bi bi-calendar-x"></i>
            O segredo do App Registration vence em <strong><?= $diasSecret ?> dia(s)</strong> (<?= date('d/m/Y', strtotime($secretVence)) ?>). Quando vencer, todo o módulo Entra para --
            gere um novo e atualize em <a href="<?= url('/administracao/integracoes/entra') ?>">Integrações</a>.</div>
    <?php endif; ?>
    <?php if ($incidentes): ?>
        <div class="alert alert-warning"><i class="bi bi-cloud-slash"></i> <strong>Microsoft com instabilidade agora:</strong>
            <?= htmlspecialchars(implode(' · ', array_map(fn($a) => $a['service'] . ': ' . $a['title'], $incidentes))) ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <?php
        $kpi(url('/entra/usuarios'), 'bi-people', 'primary', (string)$totalAtivos, 'Contas ativas', "{$totalUsuarios} no total");
        $kpi(url('/entra/usuarios'), 'bi-award', 'success', "{$licencasUsadas}/{$licencas}", 'Licenças em uso', ($licencas - $licencasUsadas) . ' livre(s)');
        $kpi(url('/entra/dispositivos'), 'bi-pc-display', 'info', $noEntra === null ? '--' : (string)count($noEntra), 'Máquinas no Entra', $soRegistradas ? count($soRegistradas) . ' só registrada(s)' : '');
        $kpi(url('/entra/grupos'), 'bi-diagram-2', 'secondary', (string)$totalGrupos, 'Grupos de segurança', 'quem acessa cada unidade');
        ?>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white"><strong><i class="bi bi-exclamation-diamond"></i> Pontos de atenção</strong></div>
                <div class="list-group list-group-flush small">
                    <?php if ($semMfa === null): ?>
                        <div class="list-group-item text-muted"><i class="bi bi-dash-circle"></i> MFA: sem permissão para consultar (AuditLog.Read.All).</div>
                    <?php elseif ($semMfa): ?>
                        <div class="list-group-item">
                            <i class="bi bi-shield-exclamation text-danger"></i> <strong><?= count($semMfa) ?> conta(s) sem MFA</strong> (verificação em duas etapas) --
                            a senha sozinha é a única proteção delas.
                            <div class="text-muted"><?= htmlspecialchars(implode(', ', array_column($semMfa, 'nome'))) ?></div>
                        </div>
                    <?php else: ?>
                        <div class="list-group-item"><i class="bi bi-check-circle text-success"></i> Todas as contas com MFA cadastrado.</div>
                    <?php endif; ?>

                    <?php if ($contasParadas === null): ?>
                        <div class="list-group-item text-muted"><i class="bi bi-dash-circle"></i> Último login: sem permissão para consultar (AuditLog.Read.All).</div>
                    <?php elseif ($contasParadas): ?>
                        <div class="list-group-item">
                            <i class="bi bi-hourglass-split text-warning"></i> <strong><?= count($contasParadas) ?> conta(s) com licença sem uso há 30+ dias</strong> --
                            licença que pode ir para outra pessoa, ou conta de quem saiu e deveria ser desativada.
                            <div class="text-muted"><?= htmlspecialchars(implode(', ', array_map(fn($c) => $c['nome'] . ' (' . ($c['ultimo'] ? date('d/m/Y', strtotime($c['ultimo'])) : 'nunca') . ')', $contasParadas))) ?></div>
                        </div>
                    <?php else: ?>
                        <div class="list-group-item"><i class="bi bi-check-circle text-success"></i> Todas as contas com licença usadas nos últimos 30 dias.</div>
                    <?php endif; ?>

                    <?php if ($soRegistradas): ?>
                        <div class="list-group-item">
                            <i class="bi bi-pc-display-horizontal text-warning"></i> <strong><?= count($soRegistradas) ?> máquina(s) só "registrada(s)"</strong>, não ingressada(s) no Entra --
                            alguém só conectou a conta da empresa no Windows; ainda aceitam conta local.
                            <div class="text-muted"><?= htmlspecialchars(implode(', ', array_column($soRegistradas, 'displayName'))) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($licencas > 0 && $licencas - $licencasUsadas > 0): ?>
                        <div class="list-group-item"><i class="bi bi-award text-info"></i> <?= $licencas - $licencasUsadas ?> licença(s) paga(s) sem uso -- dá para criar contas sem custo extra.</div>
                    <?php endif; ?>

                    <?php if ($faltando): ?>
                        <div class="list-group-item text-muted"><i class="bi bi-key"></i> Faltam <?= count($faltando) ?> permissão(ões) no App Registration
                            (<?= htmlspecialchars(implode(', ', $faltando)) ?>) -- veja em <a href="<?= url('/administracao/integracoes/entra') ?>">Integrações</a>.</div>
                    <?php endif; ?>

                    <?php if ($diasSecret !== null && $diasSecret >= 60): ?>
                        <div class="list-group-item text-muted"><i class="bi bi-calendar-check"></i> Segredo do App Registration válido até <?= date('d/m/Y', strtotime($secretVence)) ?>.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white"><strong><i class="bi bi-grid"></i> Atalhos</strong></div>
                <div class="card-body p-2">
                    <a class="atalho-entra" href="<?= url('/entra/usuarios') ?>"><i class="bi bi-people text-primary"></i><div><strong>Usuários</strong><div class="text-muted small">criar, resetar senha, licenças</div></div></a>
                    <a class="atalho-entra" href="<?= url('/entra/grupos') ?>"><i class="bi bi-diagram-2 text-secondary"></i><div><strong>Grupos</strong><div class="text-muted small">quem acessa cada unidade</div></div></a>
                    <a class="atalho-entra" href="<?= url('/entra/acesso-maquinas') ?>"><i class="bi bi-shield-lock text-danger"></i><div><strong>Acesso às Máquinas</strong><div class="text-muted small">travar login ao grupo da unidade</div></div></a>
                    <a class="atalho-entra" href="<?= url('/entra/dispositivos') ?>"><i class="bi bi-laptop text-info"></i><div><strong>Dispositivos (Intune)</strong><div class="text-muted small">sincronizar, reiniciar, bloquear</div></div></a>
                    <a class="atalho-entra" href="<?= url('/seguranca/trilha-acesso') ?>"><i class="bi bi-person-badge text-success"></i><div><strong>Trilha de acesso</strong><div class="text-muted small">quem entrou em qual máquina</div></div></a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between">
                    <strong><i class="bi bi-person-badge"></i> Últimos logins nas máquinas</strong>
                    <a href="<?= url('/seguranca/trilha-acesso') ?>" class="small">ver trilha completa</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($ultimosAcessos)): ?>
                        <p class="text-muted small p-3 mb-0">Nenhum login registrado nos últimos 30 dias (a trilha começa com o agente 1.0.71).</p>
                    <?php else: ?>
                        <table class="table table-sm small mb-0">
                            <?php foreach ($ultimosAcessos as $a): ?>
                                <tr>
                                    <td class="text-nowrap ps-3"><?= date('d/m H:i', strtotime($a['ocorrido_em'])) ?></td>
                                    <td><?= htmlspecialchars(AcessoMaquinaService::rotuloUsuario($a)) ?></td>
                                    <td><?= htmlspecialchars((string)$a['maquina_nome']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white"><strong><i class="bi bi-award"></i> Licenças do tenant</strong></div>
                <div class="card-body p-0">
                    <?php if (empty($skus)): ?>
                        <p class="text-muted p-3 mb-0 small">Nenhuma licença encontrada (ou sem permissão para consultar).</p>
                    <?php else: ?>
                        <table class="table table-sm small mb-0">
                            <thead><tr><th class="ps-3">Licença</th><th>Em uso</th><th>Total</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($skus as $sku): ?>
                                    <?php $usadas = (int)($sku['consumedUnits'] ?? 0); $total = (int)($sku['prepaidUnits']['enabled'] ?? 0); ?>
                                    <tr>
                                        <td class="ps-3"><?= htmlspecialchars(EntraService::nomeAmigavelSku($sku['skuPartNumber'] ?? '—')) ?></td>
                                        <td><?= $usadas ?></td>
                                        <td><?= $total ?></td>
                                        <td style="width:30%">
                                            <div class="progress" style="height:6px"><div class="progress-bar" style="width:<?= $total ? round($usadas / $total * 100) : 0 ?>%"></div></div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($avisos): ?>
        <details class="mt-3 small">
            <summary class="text-muted"><i class="bi bi-cloud"></i> <?= count($avisos) ?> aviso(s) em aberto da Microsoft sobre o Microsoft 365</summary>
            <ul class="text-muted mt-2">
                <?php foreach ($avisos as $a): ?>
                    <li><strong><?= htmlspecialchars($a['service']) ?></strong>: <?= htmlspecialchars($a['title']) ?>
                        <?= ($a['classification'] ?? '') === 'incident' ? '<span class="badge text-bg-warning">incidente</span>' : '' ?></li>
                <?php endforeach; ?>
            </ul>
        </details>
    <?php endif; ?>
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Microsoft Entra';

require __DIR__ . '/../layouts/main.php';
