<?php

namespace App\Components;

use App\Services\NotificationService;

class Alert
{
    public static function flash(): string
    {
        if (!isset($_SESSION['flash_msg'])) {
            return '';
        }

        $tipo = $_SESSION['flash_tipo'] ?? 'success';
        $mensagem = $_SESSION['flash_msg'];
        $detalhes = $_SESSION['flash_tecnico'] ?? null;

        $classe = $tipo === 'success' ? 'success' : 'danger';
        $titulo = $tipo === 'success'
            ? 'Operação concluída com sucesso.'
            : 'Falha na operação.';

        ob_start();
        ?>

        <div class="alert alert-<?= $classe ?> shadow-sm">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <strong><?= htmlspecialchars($titulo) ?></strong><br>
                    <?= htmlspecialchars($mensagem) ?>
                </div>

                <?php if ($detalhes): ?>
                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalDetalhes">
                        <i class="bi bi-terminal"></i> Detalhes técnicos
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($detalhes): ?>
            <div class="modal fade" id="modalDetalhes" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Detalhes técnicos da operação</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="modal-body">
                            <pre class="bg-dark text-light p-3 rounded"><?= htmlspecialchars($detalhes) ?></pre>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php
        NotificationService::clear();

        return ob_get_clean();
    }
}
