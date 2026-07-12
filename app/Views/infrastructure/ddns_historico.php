<?php
ob_start();

use App\Components\Badge;
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-journal-text me-1"></i> Histórico: <?= htmlspecialchars($conta['apelido']) ?></h4>
        <small class="text-muted">
            <?= htmlspecialchars($provedoresLabel[$conta['provedor']] ?? $conta['provedor']) ?> ·
            <span class="font-monospace"><?= htmlspecialchars($conta['hostname']) ?></span>
        </small>
    </div>
    <a href="<?= url('/infraestrutura/ddns') ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($historico)): ?>
            <div class="text-center text-muted py-4">Nenhuma atualização registrada ainda para esta conta.</div>
        <?php else: ?>
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Quando</th>
                        <th>IP</th>
                        <th>Resultado</th>
                        <th>Mensagem do provedor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historico as $h): ?>
                        <tr>
                            <td class="small"><?= htmlspecialchars(data_br($h['criado_em'])) ?></td>
                            <td class="font-monospace small"><?= htmlspecialchars($h['ip']) ?></td>
                            <td><?= (int)$h['sucesso'] === 1 ? Badge::make('Sucesso', 'success') : Badge::make('Falha', 'danger') ?></td>
                            <td class="small"><?= htmlspecialchars($h['mensagem'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - Histórico DDNS';

require __DIR__ . '/../layouts/main.php';
