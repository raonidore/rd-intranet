<?php

use App\Core\Samba\Health\SambaHealthEngine;

ob_start();

$health = (new SambaHealthEngine())->analyze($inventory);

$services = $inventory['services'];
$shares = $inventory['shares'];
$pendencias = $inventory['deploy_pending'];

$inconsistencias = count($shares['orfaos_linux']) + count($shares['ausentes_linux']);
?>

<style>
.hero-health {
    background: linear-gradient(135deg, #111827, #1f2937);
    color: white;
    border-radius: 18px;
}
.metric-card {
    border: 0;
    border-radius: 16px;
    box-shadow: 0 6px 18px rgba(0,0,0,.06);
}
.status-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    display: inline-block;
}
.dot-success { background:#198754; }
.dot-warning { background:#ffc107; }
.dot-danger { background:#dc3545; }
</style>

<div class="hero-health p-4 mb-4 shadow-sm">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="mb-1">Samba</h2>
            <small class="text-white-50">
                Inventário gerado em <?= htmlspecialchars($inventory['generated_at']) ?>
            </small>
        </div>

        <div class="text-end">
            <div style="font-size:56px;font-weight:800;">
                <?= (int)$health['score'] ?>%
            </div>
            <span class="badge bg-<?= htmlspecialchars($health['level']) ?>">
                <?= htmlspecialchars($health['status']) ?>
            </span>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card metric-card">
            <div class="card-body">
                <div class="text-muted">Compartilhamentos</div>
                <h2><?= (int)$shares['banco_total'] ?></h2>
                <small>cadastrados no banco</small>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card metric-card">
            <div class="card-body">
                <div class="text-muted">Pastas Linux</div>
                <h2><?= (int)$shares['linux_total'] ?></h2>
                <small>detectadas no servidor</small>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card metric-card">
            <div class="card-body">
                <div class="text-muted">Inconsistências</div>
                <h2><?= $inconsistencias ?></h2>
                <small>Banco x Linux</small>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card metric-card">
            <div class="card-body">
                <div class="text-muted">Pendências</div>
                <h2><?= count($pendencias) ?></h2>
                <small>aguardando deploy</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card metric-card h-100">
            <div class="card-header bg-white">
                <strong><i class="bi bi-activity"></i> Estado dos serviços</strong>
            </div>

            <div class="card-body">
                <p>
                    <span class="status-dot <?= ($services['smbd'] ?? '') === 'active' ? 'dot-success' : 'dot-danger' ?>"></span>
                    <strong>SMBD:</strong>
                    <?= htmlspecialchars($services['smbd'] ?? 'unknown') ?>
                </p>

                <p>
                    <span class="status-dot <?= ($services['nmbd'] ?? '') === 'active' ? 'dot-success' : 'dot-warning' ?>"></span>
                    <strong>NMBD:</strong>
                    <?= htmlspecialchars($services['nmbd'] ?? 'unknown') ?>
                </p>

                <a href="<?= url('/infraestrutura/servicos') ?>" class="btn btn-outline-primary btn-sm">
                    Gerenciar serviços
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card metric-card h-100">
            <div class="card-header bg-white">
                <strong><i class="bi bi-lightbulb"></i> Recomendações</strong>
            </div>

            <div class="card-body">
                <?php if (empty($health['recommendations'])): ?>
                    <div class="alert alert-success mb-0">
                        Nenhuma recomendação pendente.
                    </div>
                <?php endif; ?>

                <?php foreach ($health['recommendations'] as $r): ?>
                    <div class="border rounded p-3 mb-3">
                        <strong><?= htmlspecialchars($r['title']) ?></strong><br>
                        <span class="text-muted"><?= htmlspecialchars($r['description']) ?></span><br>
                        <a href="<?= url($r['url']) ?>" class="btn btn-sm btn-outline-primary mt-2">
                            Abrir
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="card metric-card mb-4">
    <div class="card-header bg-white">
        <strong><i class="bi bi-exclamation-triangle"></i> Alertas inteligentes</strong>
    </div>

    <div class="card-body">
        <?php if (empty($health['alerts'])): ?>
            <div class="alert alert-success mb-0">
                Nenhum alerta crítico detectado.
            </div>
        <?php endif; ?>

        <?php foreach ($health['alerts'] as $alert): ?>
            <div class="alert alert-<?= htmlspecialchars($alert['level']) ?>">
                <strong><?= htmlspecialchars($alert['title']) ?></strong><br>
                <?= htmlspecialchars($alert['description']) ?><br>
                <small><?= htmlspecialchars($alert['action']) ?></small>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="card metric-card">
    <div class="card-header bg-white">
        <strong><i class="bi bi-diagram-3"></i> Banco x Linux</strong>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Compartilhamento</th>
                    <th>Banco</th>
                    <th>Linux</th>
                    <th>Tamanho</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($shares['sincronizados'] as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['nome']) ?></td>
                        <td><span class="badge bg-success">OK</span></td>
                        <td><span class="badge bg-success">OK</span></td>
                        <td><?= htmlspecialchars($s['linux']['tamanho'] ?? 'N/A') ?></td>
                        <td>Sincronizado</td>
                    </tr>
                <?php endforeach; ?>

                <?php foreach ($shares['orfaos_linux'] as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['nome']) ?></td>
                        <td><span class="badge bg-secondary">Ausente</span></td>
                        <td><span class="badge bg-warning text-dark">Existe</span></td>
                        <td><?= htmlspecialchars($p['tamanho'] ?? 'N/A') ?></td>
                        <td>Pasta órfã</td>
                    </tr>
                <?php endforeach; ?>

                <?php foreach ($shares['ausentes_linux'] as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['nome']) ?></td>
                        <td><span class="badge bg-success">OK</span></td>
                        <td><span class="badge bg-danger">Ausente</span></td>
                        <td>-</td>
                        <td>Compartilhamento sem pasta</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Samba Dashboard';

require __DIR__ . '/../layouts/main.php';
