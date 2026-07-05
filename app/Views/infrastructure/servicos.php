<?php

use App\Components\Alert;
use App\Components\Badge;

ob_start();

function serviceBadge(string $status): string
{
    return match ($status) {
        'active' => Badge::make('Online', 'success'),
        'inactive' => Badge::make('Parado', 'secondary'),
        'failed' => Badge::make('Falha', 'danger'),
        default => Badge::make($status, 'warning')
    };
}

function serviceIcon(string $service): string
{
    return match ($service) {
        'samba' => 'bi-folder2-open',
        'apache' => 'bi-globe2',
        'mariadb' => 'bi-database',
        'ssh' => 'bi-terminal',
        default => 'bi-hdd-network'
    };
}
?>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-1">
                <i class="bi bi-hdd-network"></i> Serviços do Servidor
            </h5>
            <small class="text-muted">
                Monitore e administre serviços essenciais do Ubuntu Server.
            </small>
        </div>
        <a href="<?= url('/infraestrutura/servicos/configurar') ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-ui-checks"></i> Configurar serviços
        </a>
    </div>
</div>

<div class="row g-3">
    <?php foreach ($servicos as $item): ?>
        <?php $s = $item['status']; ?>

        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="mb-1">
                                <i class="bi <?= serviceIcon($item['chave']) ?>"></i>
                                <?= htmlspecialchars($item['nome']) ?>
                            </h5>

                            <small class="text-muted">
                                Unidade: <?= htmlspecialchars($s['unit']) ?>
                            </small>
                        </div>

                        <?= serviceBadge($s['status']) ?>
                    </div>

                    <div class="mb-3">
                        <div class="small text-muted">Inicialização automática</div>
                        <strong><?= htmlspecialchars($s['enabled']) ?></strong>
                    </div>

                    <div class="mb-3">
                        <div class="small text-muted">Ativo desde</div>
                        <strong><?= htmlspecialchars($s['uptime'] ?: '-') ?></strong>
                    </div>

                    <div class="btn-group">
                        <a href="<?= url('/infraestrutura/servicos/reiniciar?service=' . $item['chave']) ?>"
                           class="btn btn-sm btn-outline-danger"
                           onclick="return confirm('Deseja reiniciar este serviço?')">
                            <i class="bi bi-arrow-clockwise"></i> Reiniciar
                        </a>

                        <a href="<?= url('/infraestrutura/servicos/recarregar?service=' . $item['chave']) ?>"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-arrow-repeat"></i> Recarregar
                        </a>

                        <a href="<?= url('/infraestrutura/servicos/logs?service=' . $item['chave']) ?>"
                           class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-journal-text"></i> Logs
                        </a>
                    </div>

                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - Serviços';

require __DIR__ . '/../layouts/main.php';
