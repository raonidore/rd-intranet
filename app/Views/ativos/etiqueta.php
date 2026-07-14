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
        <button class="btn btn-outline-secondary" id="botaoImprimirZebra"><i class="bi bi-printer"></i> Imprimir na Zebra</button>
        <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir (navegador)</button>
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

<script>
(function () {
    const botao = document.getElementById('botaoImprimirZebra');
    if (!botao) return;

    const ids = <?= json_encode(array_column($ativos, 'id')) ?>;
    const PORTA_AGENTE_LOCAL = 8734;
    const textoOriginal = botao.innerHTML;

    botao.addEventListener('click', async function () {
        botao.disabled = true;
        let sucesso = 0, falhas = 0;

        for (const id of ids) {
            botao.innerHTML = '<i class="bi bi-hourglass-split"></i> Imprimindo ' + (sucesso + falhas + 1) + '/' + ids.length + '...';

            try {
                const resZpl = await fetch(<?= json_encode(url('/ativos/etiqueta/zpl')) ?> + '?id=' + id);
                const dadosZpl = await resZpl.json();
                if (!dadosZpl.success) { falhas++; continue; }

                // Chama o agente Windows rodando NESTA maquina (o
                // navegador), nao o servidor -- precisa da impressora
                // Zebra ligada aqui, com o agente configurado.
                const resImpressao = await fetch('http://127.0.0.1:' + PORTA_AGENTE_LOCAL + '/imprimir', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ zpl: dadosZpl.zpl })
                });
                const resultado = await resImpressao.json();
                if (resultado.success) sucesso++; else falhas++;
            } catch (e) {
                falhas++;
            }
        }

        botao.disabled = false;
        botao.innerHTML = textoOriginal;

        let mensagem = sucesso + ' etiqueta(s) enviada(s) com sucesso.';
        if (falhas > 0) {
            mensagem += ' ' + falhas + ' falharam -- confirme que o agente RD Intranet está rodando nesta máquina (ícone na bandeja) e com a impressora Zebra configurada.';
        }
        alert(mensagem);
    });
})();
</script>

</body>
</html>
