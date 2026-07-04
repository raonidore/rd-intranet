<?php

namespace App\UI\Components;

class MetricCard
{
    public static function render(
        string $titulo,
        string|int $valor,
        string $descricao = '',
        string $icone = 'bi-graph-up',
        string $cor = 'primary'
    ): void
    {
?>
<div class="card border-0 shadow-sm h-100 rd-card">

    <div class="card-body">

        <div class="d-flex justify-content-between">

            <div>

                <div class="text-muted small">
                    <?= htmlspecialchars($titulo) ?>
                </div>

                <div class="display-6 fw-bold">
                    <?= htmlspecialchars((string)$valor) ?>
                </div>

                <?php if ($descricao): ?>

                    <small class="text-muted">
                        <?= htmlspecialchars($descricao) ?>
                    </small>

                <?php endif; ?>

            </div>

            <div class="align-self-center">

                <div class="rounded-circle bg-<?= htmlspecialchars($cor) ?> bg-opacity-10 p-3">

                    <i class="bi <?= htmlspecialchars($icone) ?> text-<?= htmlspecialchars($cor) ?> fs-3"></i>

                </div>

            </div>

        </div>

    </div>

</div>
<?php
    }
}
