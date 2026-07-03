<?php

use App\Components\Alert;
use App\Components\Badge;
use App\Components\StatCard;

ob_start();
?>

<style>
.share-card{
    border:0;
    border-left:4px solid #0d6efd;
    transition:.2s;
}

.share-card:hover{
    transform:translateY(-2px);
    box-shadow:0 6px 16px rgba(0,0,0,.08);
}

.share-card.desativado{
    opacity:.65;
    background:#f8f9fa;
}
</style>

<div class="row mb-4">

    <?= StatCard::make('Compartilhamentos', $total) ?>

    <?= StatCard::make('Ativos', $ativos) ?>

    <?= StatCard::make('Com lixeira', $lixeira) ?>

    <?= StatCard::make('Bloqueio extensão', $bloqueioExtensoes) ?>

</div>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm mb-4">

    <div class="card-body d-flex justify-content-between align-items-center">

        <div>

            <h5 class="mb-1">
                <i class="bi bi-folder2-open"></i>
                Compartilhamentos Samba
            </h5>

            <small class="text-muted">
                Gerencie pastas publicadas, grupos, políticas e permissões.
            </small>

        </div>

        <div>

            <a href="<?= url('/samba/compartilhamentos/novo') ?>" class="btn btn-primary">

                <i class="bi bi-plus-lg"></i>

                Novo compartilhamento

            </a>

        </div>

    </div>

</div>

<div class="row g-3">

<?php foreach($compartilhamentos as $c): ?>

<?php $desativado = $c['status'] === 'desativado'; ?>

<div class="col-12">

<div class="card share-card <?= $desativado ? 'desativado' : '' ?>">

<div class="card-body">

<div class="d-flex justify-content-between align-items-start flex-wrap">

<div>

<h4 class="mb-1">

<i class="bi bi-folder"></i>

<?= htmlspecialchars($c['nome']) ?>

</h4>

<div class="text-muted small mb-2">

<i class="bi bi-hdd"></i>

<?= htmlspecialchars($c['caminho']) ?>

&nbsp;&nbsp;

<i class="bi bi-people"></i>

Grupo:

<?= htmlspecialchars($c['grupo']) ?>

</div>

</div>

<div class="text-end">

<?= Badge::status($c['status']) ?>

<?= (int)$c['somente_leitura']
    ? Badge::make('Somente leitura','warning')
    : Badge::make('Leitura/Escrita','success') ?>

<?= (int)$c['lixeira']
    ? Badge::make('Lixeira','primary')
    : Badge::make('Sem lixeira','secondary') ?>

<?= (int)$c['bloqueio_extensoes']
    ? Badge::make('Bloqueio extensões','danger')
    : Badge::make('Sem bloqueio','secondary') ?>

</div>

</div>

<hr>

<p class="mb-3 text-muted">

<?= htmlspecialchars($c['descricao']) ?>

</p>

<div class="btn-group">

<a href="#"
class="btn btn-outline-primary btn-sm"
title="Editar">

<i class="bi bi-pencil"></i>

</a>

<a href="#"
class="btn btn-outline-secondary btn-sm"
title="Permissões">

<i class="bi bi-shield-lock"></i>

</a>

<a href="#"
class="btn btn-outline-info btn-sm"
title="Usuários">

<i class="bi bi-people"></i>

</a>

<a href="#"
class="btn btn-outline-danger btn-sm"
title="Excluir">

<i class="bi bi-trash"></i>

</a>

</div>

</div>

</div>

</div>

<?php endforeach; ?>

</div>

<?php

$conteudo = ob_get_clean();

$titulo = 'Compartilhamentos Samba';

require __DIR__.'/../layouts/main.php';
