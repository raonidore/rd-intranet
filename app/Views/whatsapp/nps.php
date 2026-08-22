<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-emoji-smile me-1"></i> WhatsApp - NPS</h4>
    <small class="text-muted">
        Pesquisa de satisfação enviada ao cliente quando o atendente encerra um atendimento de um setor com NPS ativo
        (configurável em <a href="<?= url('/whatsapp/setores') ?>">Setores</a>). Últimos 90 dias.
    </small>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-muted small">Respostas</div>
                <div class="fs-2"><?= (int)$resumo['total'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-muted small">Nota média (0-10)</div>
                <div class="fs-2"><?= $resumo['media'] !== null ? htmlspecialchars((string)$resumo['media']) : '—' ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-muted small">Score NPS (-100 a 100)</div>
                <?php
                $corScore = 'text-muted';
                if ($resumo['score'] !== null) {
                    $corScore = $resumo['score'] >= 50 ? 'text-success' : ($resumo['score'] >= 0 ? 'text-warning' : 'text-danger');
                }
                ?>
                <div class="fs-2 <?= $corScore ?>"><?= $resumo['score'] !== null ? htmlspecialchars((string)$resumo['score']) : '—' ?></div>
            </div>
        </div>
    </div>
</div>

<?php if ($resumo['total'] > 0): ?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <h6 class="mb-3">Distribuição das notas</h6>
        <?php for ($nota = 10; $nota >= 0; $nota--): ?>
            <?php
            $qtd = $resumo['distribuicao'][$nota];
            $pct = $resumo['total'] > 0 ? round(($qtd / $resumo['total']) * 100) : 0;
            $corBarra = $nota >= 9 ? 'bg-success' : ($nota >= 7 ? 'bg-warning' : 'bg-danger');
            ?>
            <div class="d-flex align-items-center gap-2 mb-1">
                <div class="text-muted small text-end" style="width:18px"><?= $nota ?></div>
                <div class="flex-grow-1 bg-light rounded" style="height:16px">
                    <div class="<?= $corBarra ?> rounded" style="height:16px; width:<?= $pct ?>%"></div>
                </div>
                <div class="text-muted small text-nowrap" style="width:60px"><?= $qtd ?> (<?= $pct ?>%)</div>
            </div>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-3" style="max-width:900px">
    <div class="card-body">
        <h6 class="mb-2">Mensagens da pesquisa</h6>
        <form method="post" action="<?= url('/whatsapp/nps/mensagens') ?>">
            <div class="mb-2">
                <label class="form-label small text-muted">Pergunta (mandada quando o atendente encerra)</label>
                <textarea name="pergunta" class="form-control" rows="2" required><?= htmlspecialchars($npsPergunta) ?></textarea>
            </div>
            <div class="mb-2">
                <label class="form-label small text-muted">Agradecimento (mandada depois da resposta)</label>
                <textarea name="agradecimento" class="form-control" rows="2" required><?= htmlspecialchars($npsAgradecimento) ?></textarea>
            </div>
            <p class="text-muted small mb-2">Use <code>{nome}</code> pro nome do cliente.</p>
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($respostas)): ?>
            <p class="text-muted text-center py-4 mb-0">Nenhuma resposta ainda.</p>
        <?php else: ?>
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Setor</th>
                        <th>Atendente</th>
                        <th>Nota</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($respostas as $r): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($r['contato_nome'] ?: '(sem nome)') ?>
                                <span class="text-muted small ms-1"><?= htmlspecialchars(telefone_br($r['numero'])) ?></span>
                            </td>
                            <td><?= $r['setor_nome'] ? htmlspecialchars($r['setor_nome']) : '<span class="text-muted">-</span>' ?></td>
                            <td><?= $r['usuario_nome'] ? htmlspecialchars($r['usuario_nome']) : '<span class="text-muted">-</span>' ?></td>
                            <td>
                                <?php $badge = $r['nota'] >= 9 ? 'success' : ($r['nota'] >= 7 ? 'warning' : 'danger'); ?>
                                <span class="badge text-bg-<?= $badge ?>"><?= (int)$r['nota'] ?></span>
                            </td>
                            <td><?= data_br($r['criado_em']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'WhatsApp - NPS';

require __DIR__ . '/../layouts/main.php';
