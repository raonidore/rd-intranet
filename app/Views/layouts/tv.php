<?php
/**
 * Layout do Modo TV -- tela cheia, sem sidebar/topbar do sistema,
 * pensado pra ficar ligado o dia inteiro numa TV/monitor de parede.
 * Densidade baixa, fonte grande, fundo escuro de propósito (ver
 * seção "O painel na parede" da proposta do módulo).
 */
$titulo = $titulo ?? 'Painel de Projetos';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($titulo) ?></title>
    <link rel="icon" href="<?= url('/favicon.ico') ?>" sizes="any">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --tv-bg: #0A0E15; --tv-ink: #F2F5F8; --tv-muted: #8892A4; --tv-line: #232B3A;
            --tv-accent: #3FE0D0; --tv-warn: #F0B24A; --tv-danger: #FF6E62;
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0; height: 100%; background: var(--tv-bg); color: var(--tv-ink);
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
        }
        .tv-page { max-width: 1400px; margin: 0 auto; padding: 32px 40px; }
    </style>
</head>
<body>
<div class="tv-page">
    <?= $conteudo ?? '' ?>
</div>
</body>
</html>
