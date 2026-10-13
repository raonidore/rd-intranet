<?php
ob_start();

use App\Components\Alert;

require __DIR__ . '/_util.php';
?>

<?= Alert::flash() ?>

<div class="mb-4 d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-sliders me-1"></i> Perfis e regras do Wi-Fi</h4>
        <small class="text-muted">Cada perfil define como a pessoa entra, por quanto tempo, com que velocidade, em que horário e quais sites ficam bloqueados.</small>
    </div>
    <a href="<?= url('/wifi/perfis/editar') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Novo perfil</a>
</div>

<div class="row g-3">
<?php foreach ($perfis as $p): $h = json_decode((string)$p['horarios'], true); $f = json_decode((string)$p['filtro'], true) ?: []; ?>
    <div class="col-md-6 col-xl-4">
        <a href="<?= url('/wifi/perfis/editar?id=' . (int)$p['id']) ?>" class="card border-0 shadow-sm h-100 text-decoration-none text-body" style="border-top:4px solid <?= htmlspecialchars($p['cor']) ?> !important">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <h5 class="mb-1"><i class="bi <?= htmlspecialchars($p['icone']) ?> me-1" style="color:<?= htmlspecialchars($p['cor']) ?>"></i> <?= htmlspecialchars($p['nome']) ?></h5>
                    <?= $p['ativo'] ? '' : '<span class="badge text-bg-secondary">desativado</span>' ?>
                </div>
                <p class="small text-muted mb-2"><?= htmlspecialchars((string)$p['descricao']) ?></p>
                <ul class="list-unstyled small mb-0">
                    <li><i class="bi bi-box-arrow-in-right me-1"></i> <?= $p['tipo_acesso'] === 'senha' ? 'Login e senha por e-mail' : 'Cadastro rápido, sem senha' ?><?= $p['exige_liberacao'] ? ', <strong>com liberação</strong>' : '' ?><?= $p['autocadastro'] ? '' : ', só a equipe cadastra' ?></li>
                    <li><i class="bi bi-hourglass-split me-1"></i> Sessão de <?= wifi_duracao($p['sessao_minutos']) ?><?= $p['validade_dias'] ? ', cadastro vale ' . (int)$p['validade_dias'] . ' dia(s)' : ', cadastro sem vencimento' ?></li>
                    <li><i class="bi bi-speedometer2 me-1"></i> &darr; <?= wifi_banda($p['banda_down_kbps']) ?> &middot; &uarr; <?= wifi_banda($p['banda_up_kbps']) ?><?= $p['cota_mb'] ? ' &middot; cota ' . (int)$p['cota_mb'] . ' MB' : '' ?></li>
                    <li><i class="bi bi-phone me-1"></i> <?= (int)$p['max_aparelhos'] ?> aparelho(s)<?= $p['lembrar_dias'] ? ', lembra por ' . (int)$p['lembrar_dias'] . ' dias' : '' ?></li>
                    <?php if ($h && !empty($h['dias'])): ?><li><i class="bi bi-clock me-1"></i> Horário restrito<?= !empty($h['inicio']) ? " ({$h['inicio']}-{$h['fim']})" : '' ?></li><?php endif; ?>
                    <?php if (!empty($f['categorias']) || !empty($f['servicos']) || !empty($f['bloquear'])): ?><li><i class="bi bi-shield-check me-1"></i> Filtro: <?= count($f['categorias'] ?? []) + count($f['servicos'] ?? []) + count($f['bloquear'] ?? []) ?> regra(s)</li><?php endif; ?>
                </ul>
            </div>
            <div class="card-footer bg-transparent small text-muted"><?= (int)$p['pessoas'] ?> cadastro(s)</div>
        </a>
    </div>
<?php endforeach; ?>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Wi-Fi - Perfis';

require __DIR__ . '/../layouts/main.php';
