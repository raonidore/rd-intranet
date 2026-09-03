<?php
/**
 * Bolha de mensagem (HTML) -- compartilhado entre atendimento_chat.php
 * (conversa ao vivo) e contato_historico.php (histórico de todos os
 * atendimentos de um contato). Usado só na primeira renderização (SSR);
 * o polling de mensagens novas em atendimento_chat.php monta o
 * equivalente em JS (montarBolha() no script daquela view), mesma
 * lógica de cor/alinhamento nos dois lugares.
 */

/** Imagem/áudio/documento em vez do texto simples, quando a mensagem tiver midia_path. */
function renderizarConteudoBolha(array $m): string
{
    if (empty($m['midia_path'])) {
        return '<div style="white-space:pre-wrap">' . htmlspecialchars($m['conteudo']) . '</div>';
    }

    $url = url('/whatsapp/atendimentos/midia?id=' . (int)$m['id']);

    if ($m['tipo'] === 'imagem') {
        return '<a href="' . htmlspecialchars($url) . '" target="_blank">'
            . '<img src="' . htmlspecialchars($url) . '" style="max-width:100%; border-radius:6px; display:block; margin-bottom:4px">'
            . '</a>'
            . ($m['conteudo'] !== '' ? '<div style="white-space:pre-wrap">' . htmlspecialchars($m['conteudo']) . '</div>' : '');
    }

    if ($m['tipo'] === 'audio') {
        return '<audio controls style="max-width:220px; display:block"><source src="' . htmlspecialchars($url) . '"></audio>';
    }

    if ($m['tipo'] === 'documento') {
        return '<a href="' . htmlspecialchars($url) . '" target="_blank" class="d-flex align-items-center gap-2 text-decoration-none text-reset">'
            . '<i class="bi bi-file-earmark-arrow-down fs-4"></i>'
            . '<span>' . htmlspecialchars($m['conteudo'] !== '' ? $m['conteudo'] : 'Documento') . '</span>'
            . '</a>';
    }

    return '<div style="white-space:pre-wrap">' . htmlspecialchars($m['conteudo']) . '</div>';
}

function renderizarBolha(array $m): string
{
    $minhas = $m['direcao'] === 'saida';
    $corBolha = $m['origem'] === 'bot' ? '#e0e7ff' : ($minhas ? '#dcf8c6' : '#ffffff');
    $alinhamento = $minhas ? 'flex-end' : 'flex-start';
    $rotulo = $m['origem'] === 'bot' ? 'Bot' : ($m['origem'] === 'cliente' ? '' : ($m['usuario_nome'] ?? 'Você'));

    $avisoFalha = $m['status_entrega'] === 'falhou'
        ? '<div class="small text-danger mt-1"><i class="bi bi-exclamation-triangle-fill"></i> Falha ao enviar -- não chegou no WhatsApp do cliente.</div>'
        : '';

    return '<div class="d-flex mb-2" style="justify-content:' . $alinhamento . '" data-msg-id="' . (int)$m['id'] . '">'
        . '<div style="max-width:70%; background:' . $corBolha . '; border-radius:10px; padding:8px 12px; box-shadow:0 1px 2px rgba(0,0,0,.1);">'
        . ($rotulo ? '<div class="small text-muted mb-1">' . htmlspecialchars($rotulo) . '</div>' : '')
        . renderizarConteudoBolha($m)
        . $avisoFalha
        . '<div class="text-muted text-end" style="font-size:10px">' . htmlspecialchars(data_br($m['criado_em'], 'H:i')) . '</div>'
        . '</div></div>';
}
