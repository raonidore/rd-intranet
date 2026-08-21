<?php
ob_start();

use App\Components\Alert;
?>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-activity me-1"></i> MTR</h4>
        <small class="text-muted">Ping contínuo por salto (equivalente ao WinMTR) -- mostra % de perda e latência em cada trecho da rota até um host ou IP.</small>
    </div>
    <a href="<?= url('/infraestrutura/rede') ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Interfaces
    </a>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="post" action="<?= url('/infraestrutura/rede/mtr') ?>" class="d-flex gap-2">
            <input type="text" name="destino" class="form-control" placeholder="Ex: 8.8.8.8 ou google.com"
                   value="<?= htmlspecialchars($destino) ?>" required>
            <button type="submit" class="btn btn-primary text-nowrap">
                <i class="bi bi-play-fill"></i> Executar
            </button>
        </form>
        <small class="text-muted d-block mt-2">Leva cerca de 20 segundos para concluir (20 ciclos de ping por salto).</small>
    </div>
</div>

<?php if ($resultado !== null): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span>Resultado para <code><?= htmlspecialchars($destino) ?></code></span>
            <div>
                <?= $resultado['success'] ? '<span class="badge text-bg-success">OK</span>' : '<span class="badge text-bg-danger">Falhou</span>' ?>
                <?php if ($resultado['success']): ?>
                    <button class="btn btn-sm btn-outline-secondary ms-1" type="button" data-bs-toggle="collapse" data-bs-target="#saida-bruta">
                        <i class="bi bi-terminal"></i> Saída bruta
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$resultado['success']): ?>
            <div class="card-body">
                <pre class="bg-dark text-light p-3 rounded mb-0" style="white-space:pre-wrap"><?= htmlspecialchars($resultado['output']) ?></pre>
            </div>
        <?php elseif (empty($saltos)): ?>
            <div class="card-body">
                <p class="text-muted mb-0">Não foi possível interpretar a saída do mtr. Veja a saída bruta abaixo.</p>
            </div>
            <div class="collapse show" id="saida-bruta">
                <div class="card-body border-top">
                    <pre class="bg-dark text-light p-3 rounded mb-0" style="white-space:pre-wrap"><?= htmlspecialchars($resultado['output']) ?></pre>
                </div>
            </div>
        <?php else: ?>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Salto</th>
                            <th>Host</th>
                            <th>Endereço</th>
                            <th>Perda</th>
                            <th>Env.</th>
                            <th>Últ.</th>
                            <th>Média</th>
                            <th>Melhor</th>
                            <th>Pior</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($saltos as $s): ?>
                            <?php $comPerda = $s['perda_pct'] > 0; ?>
                            <tr class="<?= $comPerda ? 'table-danger' : '' ?>">
                                <td><?= $s['hop'] ?></td>
                                <td><?= $s['host'] ? htmlspecialchars($s['host']) : '<span class="text-muted">-</span>' ?></td>
                                <td><code><?= htmlspecialchars($s['ip'] ?? '-') ?></code></td>
                                <td class="<?= $comPerda ? 'fw-bold text-danger' : '' ?>"><?= htmlspecialchars((string)$s['perda_pct']) ?>%</td>
                                <td><?= $s['enviados'] ?></td>
                                <td><?= htmlspecialchars((string)$s['ultimo_ms']) ?> ms</td>
                                <td><?= htmlspecialchars((string)$s['media_ms']) ?> ms</td>
                                <td><?= htmlspecialchars((string)$s['melhor_ms']) ?> ms</td>
                                <td><?= htmlspecialchars((string)$s['pior_ms']) ?> ms</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="collapse" id="saida-bruta">
                <div class="card-body border-top">
                    <pre class="bg-dark text-light p-3 rounded mb-0" style="white-space:pre-wrap"><?= htmlspecialchars($resultado['output']) ?></pre>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - MTR';

require __DIR__ . '/../layouts/main.php';
