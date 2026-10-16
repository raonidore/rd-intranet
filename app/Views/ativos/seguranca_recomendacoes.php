<?php
ob_start();

use App\Components\Alert;
use App\Components\Badge;
use App\Services\SegurancaRecomendacaoService;

$coresSeveridade = ['alta' => 'danger', 'media' => 'warning', 'baixa' => 'secondary'];
$totalMaquinas = array_sum(array_map(fn ($r) => count($r['maquinas']), $recomendacoes));
?>

<?= Alert::flash() ?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-shield-lock me-1"></i> Central de Segurança</h4>
        <small class="text-muted">Verificações automáticas sobre o inventário e a segurança das máquinas, com correção de um clique.</small>
    </div>
</div>

<ul class="nav nav-pills mb-4">
    <li class="nav-item"><a class="nav-link" href="<?= url('/ativos/seguranca') ?>"><i class="bi bi-speedometer2"></i> Visão geral</a></li>
    <li class="nav-item"><a class="nav-link active" href="<?= url('/ativos/seguranca/recomendacoes') ?>"><i class="bi bi-lightbulb"></i> Recomendações
        <?php if ($recomendacoes): ?><span class="badge text-bg-danger ms-1"><?= count($recomendacoes) ?></span><?php endif; ?></a></li>
    <li class="nav-item"><a class="nav-link" href="<?= url('/ativos/seguranca/excecoes') ?>"><i class="bi bi-funnel"></i> Exceções e falsos positivos</a></li>
    <li class="nav-item"><a class="nav-link" href="<?= url('/ativos/seguranca/configuracao') ?>"><i class="bi bi-gear"></i> Configuração</a></li>
</ul>

<?php if (!$recomendacoes): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-shield-check display-5 text-success"></i>
            <h5 class="mt-3">Nada a recomendar agora</h5>
            <p class="text-muted mb-0">Firewall, antivírus, cópias de sombra e o resto das verificações estão em ordem nas máquinas com agente.</p>
        </div>
    </div>
<?php else: ?>
    <p class="text-muted small mb-3">
        <?= count($recomendacoes) ?> recomendação(ões), <?= $totalMaquinas ?> ocorrência(s) em máquinas. As ações são comandos prontos do RD.Intranet enviados ao agente;
        máquina desligada recebe quando ligar e o resultado aparece aqui. Dados de cópias de sombra e verificação do antivírus vêm do agente 1.0.69 ou mais novo.
    </p>

    <?php foreach ($recomendacoes as $r): ?>
        <?php $cor = $coresSeveridade[$r['severidade']] ?? 'secondary'; ?>
        <div class="card border-0 shadow-sm mb-3 border-start border-4 border-<?= $cor ?>">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-start gap-2">
                    <div class="flex-grow-1" style="min-width:260px">
                        <h6 class="mb-1">
                            <?= Badge::make(SegurancaRecomendacaoService::SEVERIDADES[$r['severidade']], $cor) ?>
                            <?= htmlspecialchars($r['titulo']) ?>
                            <?php if ($r['maquinas']): ?><span class="text-muted fw-normal small">· <?= count($r['maquinas']) ?> máquina(s)</span><?php endif; ?>
                        </h6>
                        <p class="small text-muted mb-2"><?= htmlspecialchars($r['porque']) ?></p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php if ($r['acao'] && $r['maquinas'] && $podeAgir): ?>
                            <form method="post" action="<?= url('/ativos/seguranca/recomendacoes/aplicar') ?>" onsubmit="return confirm(<?= htmlspecialchars(json_encode('Aplicar "' . $r['acao']['rotulo'] . '" em ' . count($r['maquinas']) . ' máquina(s)?'), ENT_QUOTES) ?>)">
                                <input type="hidden" name="chave" value="<?= htmlspecialchars($r['chave']) ?>">
                                <?php foreach ($r['maquinas'] as $m): ?><input type="hidden" name="ativos[]" value="<?= (int)$m['ativo_id'] ?>"><?php endforeach; ?>
                                <button class="btn btn-sm btn-primary"><i class="bi bi-magic"></i> <?= htmlspecialchars($r['acao']['rotulo']) ?><?= count($r['maquinas']) > 1 ? ' -- todas' : '' ?></button>
                            </form>
                        <?php endif; ?>
                        <?php if (!empty($r['link']['url'])): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= url($r['link']['url']) ?>"><?= htmlspecialchars($r['link']['rotulo']) ?></a>
                        <?php endif; ?>
                        <?php if ($podeAgir): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary js-ignorar" data-chave="<?= htmlspecialchars($r['chave']) ?>" data-ativo="" data-nome="<?= htmlspecialchars($r['titulo']) ?>" title="Não mostrar mais esta recomendação para nenhuma máquina">
                                <i class="bi bi-eye-slash"></i> Ignorar
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($r['maquinas']): ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 small">
                            <thead><tr><th>Máquina</th><th>Situação</th><th>Última ação</th><th class="text-end"></th></tr></thead>
                            <tbody>
                                <?php foreach ($r['maquinas'] as $m): ?>
                                    <tr>
                                        <td class="text-nowrap">
                                            <i class="bi bi-circle-fill <?= $m['ligada'] ? 'text-success' : 'text-secondary' ?>" style="font-size:.55rem" title="<?= $m['ligada'] ? 'ligada' : 'desligada' ?>"></i>
                                            <a href="<?= url('/ativos/ver?id=' . $m['ativo_id'] . (!empty($r['link']['aba']) ? '#' . $r['link']['aba'] : '')) ?>"><?= htmlspecialchars($m['codigo']) ?></a>
                                            <span class="text-muted"><?= htmlspecialchars($m['nome']) ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($m['detalhe']) ?></td>
                                        <td>
                                            <?php if ($m['acao']): ?>
                                                <?php $estado = ['aguardando' => ['secondary', 'aguardando a máquina'], 'ok' => ['success', 'aplicado'], 'falhou' => ['danger', 'falhou']][$m['acao']['estado']]; ?>
                                                <?= Badge::make($estado[1], $estado[0]) ?>
                                                <span class="text-muted"><?= htmlspecialchars(data_br($m['acao']['em'], 'd/m H:i')) ?></span>
                                                <?php if ($m['acao']['mensagem'] !== ''): ?><div class="text-muted text-break" style="max-width:420px"><?= htmlspecialchars($m['acao']['mensagem']) ?></div><?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <?php if ($r['acao'] && $podeAgir): ?>
                                                <form method="post" action="<?= url('/ativos/seguranca/recomendacoes/aplicar') ?>" class="d-inline">
                                                    <input type="hidden" name="chave" value="<?= htmlspecialchars($r['chave']) ?>">
                                                    <input type="hidden" name="ativos[]" value="<?= (int)$m['ativo_id'] ?>">
                                                    <button class="btn btn-sm btn-outline-primary py-0" title="<?= htmlspecialchars($r['acao']['rotulo']) ?> só nesta máquina"><i class="bi bi-magic"></i> Aplicar</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($podeAgir): ?>
                                                <button type="button" class="btn btn-sm btn-link py-0 text-muted js-ignorar" data-chave="<?= htmlspecialchars($r['chave']) ?>" data-ativo="<?= (int)$m['ativo_id'] ?>" data-nome="<?= htmlspecialchars($r['titulo'] . ' -- ' . $m['codigo']) ?>">ignorar</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($r['ignoradas']): ?><div class="small text-muted mt-1"><?= $r['ignoradas'] ?> máquina(s) ignorada(s) nesta recomendação.</div><?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($ignoradas): ?>
    <details class="mt-4">
        <summary class="text-muted small" style="cursor:pointer">Ignoradas (<?= count($ignoradas) ?>)</summary>
        <table class="table table-sm small mt-2">
            <thead><tr><th>Recomendação</th><th>Máquina</th><th>Motivo</th><th>Por</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($ignoradas as $i): ?>
                    <tr>
                        <td><?= htmlspecialchars($i['titulo']) ?></td>
                        <td><?= $i['ativo_id'] ? htmlspecialchars(($i['codigo_patrimonio'] ?? '#' . $i['ativo_id']) . ' ' . ($i['nome'] ?? '')) : '<em>todas</em>' ?></td>
                        <td><?= htmlspecialchars($i['motivo']) ?></td>
                        <td class="text-nowrap"><?= htmlspecialchars(($i['por'] ?? '') . ' · ' . data_br($i['em'], 'd/m/Y')) ?></td>
                        <td class="text-end">
                            <?php if ($podeAgir): ?>
                                <form method="post" action="<?= url('/ativos/seguranca/recomendacoes/reativar') ?>">
                                    <input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
                                    <button class="btn btn-sm btn-link py-0">voltar a mostrar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </details>
<?php endif; ?>

<?php if ($podeAgir): ?>
    <div class="modal fade" id="modalIgnorar" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="post" action="<?= url('/ativos/seguranca/recomendacoes/ignorar') ?>">
                <div class="modal-header"><h5 class="modal-title">Ignorar recomendação</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p class="small" id="ignorarNome"></p>
                    <input type="hidden" name="chave" id="ignorarChave">
                    <input type="hidden" name="ativo_id" id="ignorarAtivo">
                    <label class="form-label small fw-semibold" for="ignorarMotivo">Por quê?</label>
                    <input class="form-control" name="motivo" id="ignorarMotivo" required maxlength="300" placeholder="ex.: usa outro antivírus (Kaspersky)">
                    <div class="form-text">Fica registrado; dá para voltar a mostrar a qualquer momento.</div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Ignorar</button></div>
            </form>
        </div>
    </div>
    <script>
    document.addEventListener('click', function (e) {
        const b = e.target.closest('.js-ignorar');
        if (!b) return;
        document.getElementById('ignorarChave').value = b.dataset.chave;
        document.getElementById('ignorarAtivo').value = b.dataset.ativo;
        document.getElementById('ignorarNome').textContent = (b.dataset.ativo ? 'Só nesta máquina: ' : 'Para todas as máquinas: ') + b.dataset.nome;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalIgnorar')).show();
        setTimeout(function () { document.getElementById('ignorarMotivo').focus(); }, 300);
    });
    </script>
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Ativos - Central de Segurança - Recomendações';

require __DIR__ . '/../layouts/main.php';
