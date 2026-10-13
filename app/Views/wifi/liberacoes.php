<?php
ob_start();

use App\Components\Alert;

require __DIR__ . '/_util.php';
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-person-check me-1"></i> Liberações do Wi-Fi</h4>
    <small class="text-muted">Cadastros feitos pelo portal que precisam de alguém para conferir. Ao liberar, a pessoa recebe o login e a senha por e-mail.</small>
</div>

<?php if (!$pendentes): ?>
<div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">
    <i class="bi bi-check2-circle" style="font-size:2rem"></i><p class="mb-0 mt-2">Nada aguardando liberação.</p>
</div></div>
<?php else: ?>
<div class="row g-3">
<?php foreach ($pendentes as $p): $extra = json_decode((string)$p['dados_extra'], true) ?: []; ?>
    <div class="col-12 col-xl-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h6 class="mb-0"><a href="<?= url('/wifi/pessoas/ver?id=' . (int)$p['id']) ?>"><?= htmlspecialchars($p['nome']) ?></a></h6>
                        <small class="text-muted">cadastrado em <?= date('d/m/Y H:i', strtotime($p['criado_em'])) ?></small>
                    </div>
                    <?= wifi_perfil($p['perfil_nome'], $p['perfil_cor']) ?>
                </div>
                <dl class="row small mb-3">
                    <?php foreach (['email' => 'E-mail', 'telefone' => 'Celular', 'empresa' => 'Empresa', 'cnpj' => 'CNPJ', 'documento' => 'Documento', 'setor' => 'Setor', 'anfitriao' => 'Responsável'] as $k => $rot): if (!empty($p[$k])): ?>
                    <dt class="col-4 text-muted fw-normal"><?= $rot ?></dt><dd class="col-8 mb-1"><?= htmlspecialchars($p[$k]) ?></dd>
                    <?php endif; endforeach; ?>
                    <?php foreach ($extra as $rot => $v): ?>
                    <dt class="col-4 text-muted fw-normal"><?= htmlspecialchars($rot) ?></dt><dd class="col-8 mb-1"><?= htmlspecialchars((string)$v) ?></dd>
                    <?php endforeach; ?>
                </dl>
                <div class="d-flex gap-2 flex-wrap">
                    <form method="post" action="<?= url('/wifi/liberar') ?>">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <button class="btn btn-success"><i class="bi bi-check-lg"></i> Liberar</button>
                    </form>
                    <form method="post" action="<?= url('/wifi/recusar') ?>" class="d-flex gap-2 flex-grow-1" onsubmit="return confirm('Recusar este cadastro?')">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <input type="text" name="motivo" class="form-control" placeholder="Motivo (vai no e-mail, opcional)">
                        <button class="btn btn-outline-danger text-nowrap"><i class="bi bi-x-lg"></i> Recusar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Wi-Fi - Liberações';

require __DIR__ . '/../layouts/main.php';
