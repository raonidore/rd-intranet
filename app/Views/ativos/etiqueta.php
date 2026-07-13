<?php

use App\Services\AtivoService;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Etiquetas de Ativos - RD Intranet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @page { size: A4; margin: 10mm; }
        body { background: #e9ecef; }
        .barra-acoes { position: sticky; top: 0; z-index: 10; }
        .grade-etiquetas { display: flex; flex-wrap: wrap; gap: 4mm; padding: 6mm; }
        .etiqueta {
            width: 62mm; height: 40mm; border: 1px solid #999; border-radius: 3mm;
            background: #fff; padding: 3mm; display: flex; align-items: center; gap: 3mm;
            page-break-inside: avoid; box-sizing: border-box;
        }
        .etiqueta img { width: 30mm; height: 30mm; flex-shrink: 0; }
        .etiqueta .info { min-width: 0; }
        .etiqueta .codigo { font-family: 'SFMono-Regular', Consolas, monospace; font-size: 14pt; font-weight: 700; line-height: 1.1; }
        .etiqueta .tipo { font-size: 8pt; text-transform: uppercase; letter-spacing: .05em; color: #666; }
        .etiqueta .nome { font-size: 9pt; color: #333; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .etiqueta .empresa { font-size: 7pt; color: #999; margin-top: 2mm; }

        @media print {
            body { background: #fff; }
            .barra-acoes { display: none; }
            .grade-etiquetas { padding: 0; }
            .etiqueta { border: 1px dashed #ccc; }
        }
    </style>
</head>
<body>

<div class="barra-acoes bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
    <strong><i class="bi bi-qr-code"></i> <?= count($ativos) ?> etiqueta<?= count($ativos) === 1 ? '' : 's' ?></strong>
    <div class="d-flex gap-2">
        <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
        <a href="javascript:window.close()" class="btn btn-outline-secondary">Fechar</a>
    </div>
</div>

<div class="grade-etiquetas">
    <?php foreach ($ativos as $a): ?>
        <div class="etiqueta">
            <?php if (!empty($qrCodes[$a['id']])): ?>
                <img src="data:image/png;base64,<?= $qrCodes[$a['id']] ?>" alt="QR code">
            <?php endif; ?>
            <div class="info">
                <div class="tipo"><?= htmlspecialchars(AtivoService::TIPOS[$a['tipo']]['label'] ?? $a['tipo']) ?></div>
                <div class="codigo"><?= htmlspecialchars($a['codigo_patrimonio']) ?></div>
                <div class="nome" title="<?= htmlspecialchars($a['nome']) ?>"><?= htmlspecialchars($a['apelido'] ?: $a['nome']) ?></div>
                <div class="empresa"><?= htmlspecialchars($empresaNome) ?> - TI</div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

</body>
</html>
