<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-list-ul me-1"></i> Tabela ARP</h4>
        <small class="text-muted">Dispositivos vistos recentemente na rede local (<code>ip neigh</code>).</small>
    </div>
    <a href="<?= url('/infraestrutura/rede') ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Interfaces
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($linhas)): ?>
            <div class="text-center text-muted py-4">
                <i class="bi bi-list-ul display-6"></i>
                <p class="mt-2">Nenhuma entrada encontrada</p>
            </div>
        <?php else: ?>
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>IP</th>
                    <th>Interface</th>
                    <th>MAC</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($linhas as $l): ?>
                    <tr>
                        <td><?= htmlspecialchars($l['ip']) ?></td>
                        <td><?= htmlspecialchars($l['dev']) ?></td>
                        <td><code><?= htmlspecialchars($l['mac']) ?></code></td>
                        <td>
                            <?php
                                $cor = match ($l['estado']) {
                                    'REACHABLE' => 'success',
                                    'STALE' => 'warning',
                                    'FAILED' => 'danger',
                                    default => 'secondary',
                                };
                            ?>
                            <span class="badge text-bg-<?= $cor ?>"><?= htmlspecialchars($l['estado']) ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - Tabela ARP';

require __DIR__ . '/../layouts/main.php';
