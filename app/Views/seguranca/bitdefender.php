<?php

use App\Components\Alert;
use App\Components\Badge;
use App\Services\BitdefenderService;

ob_start();

$total = count($endpoints);
$online = count(array_filter($endpoints, fn ($e) => $e['online']));
$problemas = array_filter($endpoints, fn ($e) => $e['infectada'] || $e['assinatura_desatualizada'] || $e['produto_desatualizado'] || $e['modulos_desligados'] || !$e['licenciada']);
$doRd = array_filter($quarentena, fn ($q) => $q['do_rd']);
$nomesModulos = ['antimalware' => 'Antimalware', 'firewall' => 'Firewall', 'advancedThreatControl' => 'Controle de ameaças (ATC)', 'antiTampering' => 'Anti-adulteração', 'networkAttackDefense' => 'Defesa de rede', 'antiphishing' => 'Antiphishing'];
?>

<?= Alert::flash() ?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-shield-fill-check me-1" style="color:#ED1C24"></i> Bitdefender GravityZone</h4>
        <small class="text-muted">
            Antivírus das máquinas, direto do console. Sincroniza a cada 15 min
            -- última: <?= $ultimaSincronizacao ? htmlspecialchars(data_br($ultimaSincronizacao, 'd/m H:i')) : 'ainda não rodou' ?>.
        </small>
        <?php if ($ultimoErro): ?><div class="small text-danger"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($ultimoErro) ?></div><?php endif; ?>
    </div>
    <form method="post" action="<?= url('/seguranca/bitdefender/sincronizar') ?>">
        <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-repeat"></i> Sincronizar agora</button>
    </form>
</div>

<?php if ($doRd): ?>
    <div class="alert alert-danger">
        <strong><i class="bi bi-exclamation-octagon"></i> O Bitdefender colocou em quarentena <?= count($doRd) ?> arquivo(s) do agente RD.Intranet</strong>
        -- o agente para de funcionar nessas máquinas. Inclua as exclusões do RD na política do GravityZone
        (lista em <a href="<?= url('/administracao/integracoes/bitdefender') ?>" class="alert-link">Administração &gt; Integrações &gt; Bitdefender</a>)
        e depois restaure os arquivos na <a href="#quarentena" class="alert-link">quarentena</a> abaixo. Restaurar sem a exclusão faz o Bitdefender bloquear de novo.
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php
        $usadas = (int)($licenca['usedSlots'] ?? 0);
        $vagas = (int)($licenca['totalSlots'] ?? 0);
        $cartoes = [
            ['Máquinas no console', $total . ($total ? " ({$online} ligada(s))" : ''), 'bi-pc-display', 'primary'],
            ['Com problema', count($problemas), 'bi-exclamation-triangle', $problemas ? 'danger' : 'success'],
            ['Em quarentena', count($quarentena), 'bi-archive', $quarentena ? 'warning' : 'secondary'],
            ['Com agente RD e sem Bitdefender', count($semBitdefender), 'bi-shield-x', $semBitdefender ? 'danger' : 'success'],
            ['Licença', $licenca ? "{$usadas} de {$vagas} vagas" . (!empty($licenca['expiryDate']) ? ' · até ' . date('d/m/Y', strtotime($licenca['expiryDate'])) : '') : '—', 'bi-key', 'secondary'],
        ];
    ?>
    <?php foreach ($cartoes as [$rotulo, $valor, $icone, $cor]): ?>
        <div class="col-6 col-lg">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted mb-1"><i class="bi <?= $icone ?> text-<?= $cor ?>"></i> <?= htmlspecialchars($rotulo) ?></div>
                    <div class="fs-5 fw-semibold"><?= htmlspecialchars((string)$valor) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white"><strong><i class="bi bi-pc-display"></i> Máquinas</strong></div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead>
                <tr><th>Máquina</th><th>Ativo RD</th><th>Proteção</th><th>Versão / assinaturas</th><th>Política</th><th>Último contato</th><th class="text-end">Ações</th></tr>
            </thead>
            <tbody>
                <?php foreach ($endpoints as $e): ?>
                    <tr>
                        <td>
                            <i class="bi bi-circle-fill <?= $e['online'] ? 'text-success' : 'text-secondary' ?>" style="font-size:.55rem" title="<?= $e['online'] ? 'ligada' : 'desligada' ?>"></i>
                            <strong><?= htmlspecialchars($e['nome']) ?></strong>
                            <div class="small text-muted"><?= htmlspecialchars(trim($e['so'] . ' · ' . ($e['ip'] ?? ''), ' ·')) ?></div>
                        </td>
                        <td class="small">
                            <?php if ($e['ativo_id']): ?>
                                <a href="<?= url('/ativos/ver?id=' . $e['ativo_id'] . '#abaSeguranca') ?>"><?= htmlspecialchars($e['codigo'] ?? '#' . $e['ativo_id']) ?></a>
                            <?php else: ?><span class="text-muted" title="Sem agente RD ou nome/MAC não bateu">—</span><?php endif; ?>
                        </td>
                        <td class="small">
                            <?php if ($e['infectada']): ?><?= Badge::make('Ameaça ativa', 'danger') ?>
                            <?php elseif ($e['deteccao']): ?><?= Badge::make('Detecção recente', 'warning') ?>
                            <?php else: ?><?= Badge::make('Protegida', 'success') ?><?php endif; ?>
                            <?php if ($e['isolada']): ?><?= Badge::make('Isolada', 'danger') ?><?php endif; ?>
                            <?php if (!$e['licenciada']): ?><?= Badge::make('Sem licença', 'danger') ?><?php endif; ?>
                            <?php if ($e['modulos_desligados']): ?>
                                <div class="text-danger">Desligado: <?= htmlspecialchars(implode(', ', array_map(fn ($m) => $nomesModulos[$m] ?? $m, $e['modulos_desligados']))) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="small">
                            <?= htmlspecialchars($e['versao']) ?>
                            <?php if ($e['produto_desatualizado']): ?><?= Badge::make('versão antiga', 'warning') ?><?php endif; ?>
                            <div class="<?= $e['assinatura_desatualizada'] ? 'text-danger' : 'text-muted' ?>">
                                assinaturas <?= $e['assinatura_desatualizada'] ? 'DESATUALIZADAS' : 'em dia' ?><?= $e['atualizado_em'] ? ' · ' . htmlspecialchars(data_br($e['atualizado_em'], 'd/m H:i')) : '' ?>
                            </div>
                        </td>
                        <td class="small"><?= htmlspecialchars($e['politica']) ?><?php if (!$e['politica_aplicada']): ?> <span class="text-warning">(pendente)</span><?php endif; ?></td>
                        <td class="small text-nowrap"><?= $e['visto_em'] ? htmlspecialchars(data_br($e['visto_em'], 'd/m H:i')) : '—' ?></td>
                        <td class="text-end text-nowrap">
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-search"></i> Verificar</button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <?php foreach (BitdefenderService::TIPOS_VERIFICACAO as $tipo => $rotulo): ?>
                                        <li><form method="post" action="<?= url('/seguranca/bitdefender/verificar') ?>">
                                            <input type="hidden" name="endpoint_id" value="<?= htmlspecialchars($e['endpoint_id']) ?>">
                                            <input type="hidden" name="tipo" value="<?= $tipo ?>">
                                            <button class="dropdown-item">Verificação <?= mb_strtolower($rotulo) ?></button>
                                        </form></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <form method="post" action="<?= url('/seguranca/bitdefender/isolar') ?>" class="d-inline"
                                  onsubmit="return confirm(<?= htmlspecialchars(json_encode($e['isolada'] ? 'Liberar ' . $e['nome'] . ' do isolamento?' : 'Isolar ' . $e['nome'] . ' da rede pelo Bitdefender? Ela só continua falando com o console.'), ENT_QUOTES) ?>)">
                                <input type="hidden" name="endpoint_id" value="<?= htmlspecialchars($e['endpoint_id']) ?>">
                                <input type="hidden" name="acao" value="<?= $e['isolada'] ? 'liberar' : 'isolar' ?>">
                                <button class="btn btn-sm <?= $e['isolada'] ? 'btn-outline-success' : 'btn-outline-danger' ?>"><i class="bi bi-ethernet"></i> <?= $e['isolada'] ? 'Liberar' : 'Isolar' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$endpoints): ?><tr><td colspan="7" class="text-center text-muted py-4">Nenhuma máquina sincronizada ainda -- clique em "Sincronizar agora".</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4" id="quarentena">
    <div class="card-header bg-white"><strong><i class="bi bi-archive"></i> Quarentena</strong></div>
    <?php if ($quarentena): ?>
        <form method="post" action="<?= url('/seguranca/bitdefender/quarentena') ?>" id="formQuarentena">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th style="width:30px"><input type="checkbox" class="form-check-input" onclick="document.querySelectorAll('.chk-q').forEach(c => c.checked = this.checked)"></th><th>Quando</th><th>Máquina</th><th>Ameaça</th><th>Arquivo</th></tr></thead>
                    <tbody>
                        <?php foreach ($quarentena as $q): ?>
                            <tr class="<?= $q['do_rd'] ? 'table-danger' : '' ?>">
                                <td><input type="checkbox" class="form-check-input chk-q" name="itens[]" value="<?= htmlspecialchars($q['item_id']) ?>"></td>
                                <td class="small text-nowrap"><?= htmlspecialchars(data_br($q['quarentenado_em'], 'd/m/Y H:i')) ?></td>
                                <td class="small"><?= htmlspecialchars($q['endpoint_nome']) ?>
                                    <?php if ($q['ativo_id']): ?><a class="text-muted" href="<?= url('/ativos/ver?id=' . $q['ativo_id']) ?>">(<?= htmlspecialchars($q['codigo_patrimonio'] ?? '') ?>)</a><?php endif; ?></td>
                                <td class="small"><?= htmlspecialchars($q['ameaca']) ?></td>
                                <td class="small"><code class="text-break"><?= htmlspecialchars($q['arquivo']) ?></code>
                                    <?php if ($q['do_rd']): ?><div><?= Badge::make('arquivo do agente RD.Intranet', 'danger') ?></div><?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-body d-flex flex-wrap gap-2 border-top">
                <button name="acao" value="restaurar" class="btn btn-sm btn-outline-success" onclick="return confirm('Restaurar os itens marcados? Só faça isso se forem falso positivo (ex.: arquivos do agente RD.Intranet).')"><i class="bi bi-arrow-counterclockwise"></i> Restaurar marcados</button>
                <button name="acao" value="remover" class="btn btn-sm btn-outline-danger" onclick="return confirm('Apagar DE VEZ os itens marcados? Não dá para desfazer.')"><i class="bi bi-trash"></i> Apagar de vez</button>
            </div>
        </form>
    <?php else: ?>
        <div class="card-body text-muted small">Nada em quarentena.</div>
    <?php endif; ?>
</div>

<?php if ($semBitdefender): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><strong><i class="bi bi-shield-x text-danger"></i> Máquinas com agente RD que não aparecem no Bitdefender</strong></div>
        <div class="card-body small">
            <p class="text-muted">Estão sem o Bitdefender ou com outro nome no console. Instale o pacote do GravityZone nelas.</p>
            <?php foreach ($semBitdefender as $m): ?>
                <a class="me-3" href="<?= url('/ativos/ver?id=' . $m['id']) ?>"><?= htmlspecialchars($m['codigo_patrimonio']) ?></a><span class="text-muted me-3"><?= htmlspecialchars($m['nome']) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Segurança - Bitdefender GravityZone';

require __DIR__ . '/../layouts/main.php';
