<?php
ob_start();

use App\Components\Alert;

/**
 * Agrupa saltos consecutivos em timeout numa unica linha "timeout reached"
 * (mesmo estilo de ferramentas de traceroute online), em vez de uma linha
 * "*" por salto perdido.
 */
function agruparSaltos(array $saltos): array
{
    $grupos = [];
    $timeoutAtual = null;

    foreach ($saltos as $s) {
        if ($s['timeout']) {
            if ($timeoutAtual === null) {
                $timeoutAtual = ['tipo' => 'timeout', 'de' => $s['ttl'], 'ate' => $s['ttl']];
            } else {
                $timeoutAtual['ate'] = $s['ttl'];
            }
            continue;
        }

        if ($timeoutAtual !== null) {
            $grupos[] = $timeoutAtual;
            $timeoutAtual = null;
        }

        $grupos[] = ['tipo' => 'salto'] + $s;
    }

    if ($timeoutAtual !== null) {
        $grupos[] = $timeoutAtual;
    }

    return $grupos;
}
?>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-signpost me-1"></i> Traceroute</h4>
        <small class="text-muted">Mostra o caminho de rede até um host ou IP, com número do AS quando disponível.</small>
    </div>
    <a href="<?= url('/infraestrutura/rede') ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Interfaces
    </a>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="post" action="<?= url('/infraestrutura/rede/traceroute') ?>" id="formTraceroute" class="d-flex gap-2">
            <input type="text" name="destino" class="form-control" placeholder="Ex: 8.8.8.8 ou google.com"
                   value="<?= htmlspecialchars($destino) ?>" required>
            <button type="submit" class="btn btn-primary text-nowrap">
                <i class="bi bi-play-fill"></i> Executar
            </button>
        </form>
        <small class="text-muted d-block mt-2">Pode levar alguns segundos para concluir.</small>
    </div>
</div>

<?php if ($resultado !== null): ?>
    <div class="card border-0 shadow-sm" id="cardResultadoTraceroute">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span><?= htmlspecialchars($cabecalho ?: 'Resultado para ' . $destino) ?></span>
            <div class="d-flex align-items-center gap-1">
                <?= $resultado['success'] ? '<span class="badge text-bg-success">OK</span>' : '<span class="badge text-bg-danger">Falhou</span>' ?>
                <?php if ($resultado['success']): ?>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#saida-bruta" data-rd-export-ignore>
                        <i class="bi bi-terminal"></i> Saída bruta
                    </button>
                <?php endif; ?>
                <div class="dropdown d-inline-block" data-rd-export-ignore
                     data-rd-export-container="#cardResultadoTraceroute"
                     data-rd-export-titulo="Traceroute"
                     data-rd-export-ferramenta="traceroute"
                     data-rd-export-alvo="<?= htmlspecialchars($destino) ?>">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-download"></i> Exportar
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" data-rd-export-formato="html">HTML</a></li>
                        <li><a class="dropdown-item" href="#" data-rd-export-formato="pdf">PDF</a></li>
                        <li><a class="dropdown-item" href="#" data-rd-export-formato="jpg">JPG</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <?php if (!$resultado['success']): ?>
            <div class="card-body">
                <pre class="bg-dark text-light p-3 rounded mb-0" style="white-space:pre-wrap"><?= htmlspecialchars($resultado['output']) ?></pre>
            </div>
        <?php else: ?>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>TTL</th>
                            <th>AS#</th>
                            <th>Host</th>
                            <th>Endereço</th>
                            <th>Tempo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (agruparSaltos($saltos) as $g): ?>
                            <?php if ($g['tipo'] === 'timeout'): ?>
                                <tr class="table-light text-muted">
                                    <td colspan="5">
                                        <i class="bi bi-chevron-right"></i>
                                        timeout reached
                                        (<?= $g['de'] === $g['ate'] ? "salto {$g['de']}" : "saltos {$g['de']}–{$g['ate']}" ?>)
                                    </td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td><?= $g['ttl'] ?></td>
                                    <td><?= $g['as'] ? '<code>' . htmlspecialchars($g['as']) . '</code>' : '<span class="text-muted">-</span>' ?></td>
                                    <td><?= $g['host'] ? htmlspecialchars($g['host']) : '<span class="text-muted">-</span>' ?></td>
                                    <td><code><?= htmlspecialchars($g['ip'] ?? ($g['bruto'] ?? '-')) ?></code></td>
                                    <td><?= $g['ms'] !== null ? htmlspecialchars((string)$g['ms']) . ' ms' : '-' ?></td>
                                </tr>
                            <?php endif; ?>
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

<script src="<?= url('/assets/js/rd-diagnostico.js') ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js"></script>
<script>
    RdDiagnostico.armarFormulario(document.getElementById('formTraceroute'), 'Executando Traceroute... pode levar até 25 segundos.');
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - Traceroute';

require __DIR__ . '/../layouts/main.php';
