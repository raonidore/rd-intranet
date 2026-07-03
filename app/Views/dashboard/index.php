<?php
ob_start();
?>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <small class="text-muted">Usuários Samba</small>
                <h3><?= $dashboardSamba['total'] ?></h3>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <small class="text-muted">Usuários ativos</small>
                <h3><?= $dashboardSamba['ativos'] ?></h3>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <small class="text-muted">Com SSH</small>
                <h3><?= $dashboardSamba['ssh'] ?></h3>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <small class="text-muted">Compartilhamentos</small>
                <h3><?= $dashboardSamba['compartilhamentos'] ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h5>RD Intranet</h5>
        <p class="mb-0">
            Plataforma administrativa da RD Tecnologia para gestão de usuários, Samba,
            servidores, VPN, backup e monitoramento.
        </p>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Dashboard';

require __DIR__ . '/../layouts/main.php';
