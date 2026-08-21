<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-search me-1"></i> Diagnóstico DNS</h4>
        <small class="text-muted">Testa a resolução de um domínio pelo DNS atual do servidor e compara com Google (8.8.8.8) e Cloudflare (1.1.1.1).</small>
    </div>
    <a href="<?= url('/infraestrutura/rede') ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Interfaces
    </a>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="post" action="<?= url('/infraestrutura/rede/dns') ?>" class="d-flex gap-2">
            <input type="text" name="dominio" class="form-control" placeholder="Ex: google.com ou o site do cliente"
                   value="<?= htmlspecialchars($dominio) ?>" required>
            <button type="submit" class="btn btn-primary text-nowrap">
                <i class="bi bi-play-fill"></i> Executar
            </button>
        </form>
    </div>
</div>

<?php if ($resultado !== null): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span>Resultado para <code><?= htmlspecialchars($dominio) ?></code></span>
            <?= $resultado['success'] ? '<span class="badge text-bg-success">OK</span>' : '<span class="badge text-bg-danger">Falhou</span>' ?>
        </div>

        <?php if (!$resultado['success'] || empty($resolvers)): ?>
            <div class="card-body">
                <pre class="bg-dark text-light p-3 rounded mb-0" style="white-space:pre-wrap"><?= htmlspecialchars($resultado['output']) ?></pre>
            </div>
        <?php else: ?>
            <?php if ($resolvconf !== ''): ?>
                <div class="card-body pb-0">
                    <small class="text-muted">Resolvedores em <code>/etc/resolv.conf</code>: <?= htmlspecialchars($resolvconf) ?></small>
                </div>
            <?php endif; ?>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Resolver</th>
                            <th>Servidor</th>
                            <th>Status</th>
                            <th>Tempo</th>
                            <th>Resposta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resolvers as $r): ?>
                            <?php $ok = $r['status'] === 'ok'; ?>
                            <tr>
                                <td><?= htmlspecialchars($r['nome']) ?></td>
                                <td><code><?= htmlspecialchars($r['servidor']) ?></code></td>
                                <td>
                                    <?= $ok
                                        ? '<span class="badge text-bg-success">OK</span>'
                                        : '<span class="badge text-bg-danger">Falha</span>' ?>
                                </td>
                                <td><?= $r['tempo_ms'] !== null ? $r['tempo_ms'] . ' ms' : '-' ?></td>
                                <td><?= $r['resposta'] !== '' ? '<code>' . htmlspecialchars($r['resposta']) . '</code>' : '<span class="text-muted">-</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - Diagnóstico DNS';

require __DIR__ . '/../layouts/main.php';
