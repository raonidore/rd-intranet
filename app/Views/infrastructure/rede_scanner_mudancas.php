<?php
/**
 * "Mudanças na rede" -- linha do tempo de quem entrou/saiu da rede, uma
 * linha por varredura (manual ou automática) que teve alguma diferença em
 * relação à anterior da mesma faixa. Incluído por rede_scanner.php; espera
 * $mudancas (NetworkToolsService::listarMudancasRecentes()).
 */
$chipHost = function (array $h, bool $entrou): string {
    $nome = $h['ativo']['nome'] ?? ($h['hostname'] ?? '');
    $titulo = trim(($h['mac'] ?? '') . ' ' . ($h['vendor'] ?? ''));
    $ip = htmlspecialchars($h['ip']);
    $ipHtml = !empty($h['ativo'])
        ? '<a href="' . url('/ativos/ver?id=' . (int)$h['ativo']['id']) . '">' . $ip . '</a>'
        : $ip;

    return '<span class="mudanca-chip ' . ($entrou ? 'entrou' : 'saiu') . '" title="' . htmlspecialchars($titulo) . '">'
        . '<i class="bi ' . ($entrou ? 'bi-box-arrow-in-right' : 'bi-box-arrow-left') . '"></i> '
        . '<span class="font-monospace">' . $ipHtml . '</span>'
        . ($nome !== '' ? ' <span class="nome">' . htmlspecialchars($nome) . '</span>' : '')
        . (!empty($h['vendor']) && $nome === '' ? ' <span class="nome">' . htmlspecialchars($h['vendor']) . '</span>' : '')
        . ($entrou && empty($h['ativo']) ? ' <a class="cadastrar" href="' . url('/ativos/novo') . '?ip=' . urlencode($h['ip']) . '&nome=' . urlencode($h['hostname'] ?? '') . '" target="_blank" title="Cadastrar como ativo"><i class="bi bi-plus-circle"></i></a>' : '')
        . '</span>';
};
?>
<style>
.mudanca-linha { display:flex; gap:14px; padding:12px 0; border-bottom:1px solid #f0f2f5; }
.mudanca-linha:last-child { border-bottom:0; }
.mudanca-quando { width:118px; flex-shrink:0; font-size:.8rem; color:#6c757d; }
.mudanca-quando b { display:block; color:#212529; font-size:.88rem; }
.mudanca-chip { display:inline-flex; align-items:center; gap:5px; border-radius:8px; padding:3px 9px; font-size:.78rem; margin:0 6px 6px 0; }
.mudanca-chip.entrou { background:#e8f7ee; color:#146c43; border:1px solid #b9e4c9; }
.mudanca-chip.saiu { background:#fdecec; color:#a52834; border:1px solid #f3c2c6; }
.mudanca-chip .nome { color:#495057; }
.mudanca-chip a { color:inherit; }
.mudanca-chip a.cadastrar { color:#0d6efd; }
</style>

<div class="card ipscan-card mb-4" id="mudancasRede">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
        <span class="small text-muted text-uppercase" style="letter-spacing:.04em"><i class="bi bi-activity me-1"></i>Mudanças na rede</span>
        <small class="text-muted">quem entrou <i class="bi bi-box-arrow-in-right text-success"></i> e saiu <i class="bi bi-box-arrow-left text-danger"></i> entre uma varredura e a anterior</small>
    </div>
    <div class="card-body py-1">
        <?php if (empty($mudancas)): ?>
            <p class="text-muted small my-3">Nenhuma mudança registrada ainda -- aparece aqui a partir da segunda varredura de uma mesma faixa.</p>
        <?php else: ?>
            <?php foreach ($mudancas as $m): ?>
                <div class="mudanca-linha">
                    <div class="mudanca-quando">
                        <b><?= htmlspecialchars(date('d/m H:i', strtotime($m['executado_em']))) ?></b>
                        <?= $m['automatica'] ? '<i class="bi bi-clock-history"></i> automática' : '<i class="bi bi-person"></i> manual' ?>
                        <div><button type="button" class="btn btn-link btn-sm p-0" onclick="window.ipscanVerExecucao && window.ipscanVerExecucao(<?= (int)$m['id'] ?>)">ver varredura</button></div>
                    </div>
                    <div class="flex-grow-1">
                        <?php foreach ($m['novos'] as $h): ?><?= $chipHost($h, true) ?><?php endforeach; ?>
                        <?php foreach ($m['sumiram'] as $h): ?><?= $chipHost($h, false) ?><?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
