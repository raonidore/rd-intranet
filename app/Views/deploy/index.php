<?php

ob_start();

$pendente = (int)($samba['alteracoes_pendentes'] ?? 0) === 1;
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">

        <h4>
            🚀 Deploy Center
        </h4>

        <small class="text-muted">
            Central de Deploy da RD Intranet
        </small>

    </div>
</div>

<div class="card shadow-sm border-0">

    <div class="card-body">

        <div class="d-flex justify-content-between">

            <div>

                <h5>Samba</h5>

                <p class="mb-1">

                    Último deploy:

                    <strong>

                        <?= $samba['ultimo_deploy'] ?? 'Nunca' ?>

                    </strong>

                </p>

                <p class="mb-1">

                    Último backup:

                    <strong>

                        <?= $samba['ultimo_backup'] ?? '-' ?>

                    </strong>

                </p>

                <p>

                    Usuário:

                    <strong>

                        <?= $samba['ultimo_usuario'] ?? '-' ?>

                    </strong>

                </p>

            </div>

            <div class="text-end">

                <?php if($pendente): ?>

                    <span class="badge bg-warning text-dark">

                        Alterações pendentes

                    </span>

                <?php else: ?>

                    <span class="badge bg-success">

                        Produção sincronizada

                    </span>

                <?php endif; ?>

                <br><br>

                <a href="<?= url('/deploy/samba/aplicar') ?>"
                   class="btn btn-primary">

                    🚀 Aplicar Configuração

                </a>

            </div>

        </div>

    </div>

</div>

<?php if(!empty($pendencias)): ?>

<br>

<div class="card border-0 shadow-sm">

    <div class="card-header">

        <strong>

            Alterações pendentes

        </strong>

    </div>

    <table class="table table-hover mb-0">

        <thead>

        <tr>

            <th width="160">

                Tipo

            </th>

            <th width="220">

                Referência

            </th>

            <th>

                Descrição

            </th>

            <th width="170">

                Data

            </th>

        </tr>

        </thead>

        <tbody>

        <?php foreach($pendencias as $p): ?>

            <tr>

                <td>

                    <?= htmlspecialchars($p['tipo']) ?>

                </td>

                <td>

                    <?= htmlspecialchars($p['referencia']) ?>

                </td>

                <td>

                    <?= htmlspecialchars($p['descricao']) ?>

                </td>

                <td>

                    <?= date('d/m/Y H:i', strtotime($p['criado_em'])) ?>

                </td>

            </tr>

        <?php endforeach; ?>

        </tbody>

    </table>

</div>

<?php endif; ?>

<?php

$conteudo = ob_get_clean();

$titulo = 'Deploy Center';

require __DIR__.'/../layouts/main.php';
