<?php
ob_start();

use App\Components\Alert;
use App\Services\PermissionService;

require __DIR__ . '/_util.php';
$podeDerrubar = PermissionService::temAcesso('wifi_liberar');
$totalDown = array_sum(array_column($sessoes, 'bytes_down'));
?>

<?= Alert::flash() ?>

<div class="mb-4 d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-wifi me-1"></i> Wi-Fi: conectados agora</h4>
        <small class="text-muted">Quem entrou pelo portal e ainda está com acesso liberado.</small>
    </div>
    <form method="post" action="<?= url('/wifi/atualizar') ?>">
        <button class="btn btn-outline-secondary"><i class="bi bi-arrow-repeat"></i> Atualizar consumo</button>
    </form>
</div>

<?php if (!$portalAtivo): ?>
<div class="alert alert-warning">O portal está <strong>desligado</strong>. Ligue em <a href="<?= url('/wifi/configuracoes') ?>">Wi-Fi &gt; Configurações</a>.</div>
<?php elseif (!$controlador): ?>
<div class="alert alert-danger">O sistema não está conectado ao equipamento de Wi-Fi (integração UniFi não configurada). Ninguém consegue ser liberado.</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body">
        <div class="text-muted small">Aparelhos conectados</div><div class="fs-3 fw-semibold"><?= count($sessoes) ?></div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body">
        <div class="text-muted small">Consumo das sessões abertas</div><div class="fs-3 fw-semibold"><?= wifi_bytes($totalDown) ?></div>
    </div></div></div>
    <div class="col-6 col-lg-3"><a class="text-decoration-none" href="<?= url('/wifi/liberacoes') ?>"><div class="card border-0 shadow-sm"><div class="card-body">
        <div class="text-muted small">Aguardando liberação</div><div class="fs-3 fw-semibold <?= ($contagem['pendente'] ?? 0) ? 'text-warning' : 'text-body' ?>"><?= (int)($contagem['pendente'] ?? 0) ?></div>
    </div></div></a></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body">
        <div class="text-muted small">Cadastros ativos</div><div class="fs-3 fw-semibold"><?= (int)($contagem['ativo'] ?? 0) ?></div>
    </div></div></div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
    <?php if (!$sessoes): ?>
        <div class="text-center text-muted py-5"><i class="bi bi-wifi-off" style="font-size:2rem"></i><p class="mb-0 mt-2">Ninguém conectado pelo portal agora.</p></div>
    <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Pessoa</th><th>Perfil</th><th>Aparelho</th><th>Desde</th><th>Vence</th><th class="text-end">Consumo</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($sessoes as $s): ?>
                <tr>
                    <td>
                        <?php if ($s['pessoa_id']): ?><a href="<?= url('/wifi/pessoas/ver?id=' . (int)$s['pessoa_id']) ?>"><strong><?= htmlspecialchars($s['pessoa_nome'] ?? '') ?></strong></a><?php endif; ?>
                        <div class="small text-muted"><?= htmlspecialchars($s['pessoa_email'] ?: ($s['empresa'] ?? '')) ?></div>
                    </td>
                    <td><?= wifi_perfil($s['perfil_nome'], $s['perfil_cor']) ?></td>
                    <td class="small"><code><?= htmlspecialchars(strtoupper((string)$s['mac'])) ?></code><div class="text-muted"><?= htmlspecialchars((string)$s['ip']) ?><?= $s['ssid'] ? ' &middot; ' . htmlspecialchars($s['ssid']) : '' ?></div></td>
                    <td class="small"><?= date('d/m H:i', strtotime($s['inicio'])) ?></td>
                    <td class="small"><?= $s['fim_previsto'] ? date('d/m H:i', strtotime($s['fim_previsto'])) : '-' ?></td>
                    <td class="text-end small">&darr; <?= wifi_bytes($s['bytes_down']) ?><br>&uarr; <?= wifi_bytes($s['bytes_up']) ?></td>
                    <td class="text-end">
                        <?php if ($podeDerrubar): ?>
                        <form method="post" action="<?= url('/wifi/sessao/derrubar') ?>" onsubmit="return confirm('Desconectar este aparelho? A pessoa precisará entrar de novo pelo portal.')">
                            <input type="hidden" name="sessao_id" value="<?= (int)$s['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" title="Desconectar"><i class="bi bi-x-circle"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Wi-Fi';

require __DIR__ . '/../layouts/main.php';
