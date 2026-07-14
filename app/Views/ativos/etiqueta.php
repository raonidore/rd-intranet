<?php
// Layout/campos/fontes vem de EtiquetaService (Ativos > Configurações de
// Etiqueta) -- cada item de $blocosHtml já é o mesmo HTML/CSS usado na
// pré-visualização daquela tela, só que com o QR code de verdade
// (escaneável) em vez do ícone de placeholder.
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Etiquetas de Ativos - RD Intranet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        @page { size: A4; margin: 10mm; }
        body { background: #e9ecef; }
        .barra-acoes { position: sticky; top: 0; z-index: 10; }
        .grade-etiquetas { display: flex; flex-wrap: wrap; gap: 4mm; padding: 6mm; }

        .rd-etiqueta-preview {
            border: 1px solid #999;
            display: flex;
            align-items: center;
            gap: 2mm;
            box-sizing: border-box;
            background: #fff;
            page-break-inside: avoid;
        }
        .rd-etiqueta-qr {
            flex-shrink: 0;
            border: 1px dashed #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 6mm;
            color: #999;
        }
        .rd-etiqueta-qr-img { flex-shrink: 0; }
        .rd-etiqueta-texto {
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            width: 100%;
        }
        .rd-etiqueta-linha {
            font-weight: 700;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .rd-etiqueta-espaco {
            margin-top: 2mm;
            font-weight: 400;
            color: #666;
        }

        @media print {
            body { background: #fff; }
            .barra-acoes { display: none; }
            .grade-etiquetas { padding: 0; }
            .rd-etiqueta-preview { border: 1px dashed #ccc; }
        }
    </style>
</head>
<body>

<div class="barra-acoes bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
    <strong><i class="bi bi-qr-code"></i> <?= count($blocosHtml) ?> etiqueta<?= count($blocosHtml) === 1 ? '' : 's' ?></strong>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary" id="botaoImprimirZebra"><i class="bi bi-printer"></i> Imprimir na Zebra</button>
        <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir (navegador)</button>
        <a href="javascript:window.close()" class="btn btn-outline-secondary">Fechar</a>
    </div>
</div>

<div class="grade-etiquetas">
    <?php foreach ($blocosHtml as $bloco): ?>
        <?= $bloco ?>
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
