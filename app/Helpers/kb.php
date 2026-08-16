<?php

/**
 * Renderiza o texto de um artigo da Base de Conhecimento, reconhecendo
 * blocos de comando marcados com ``` (mesma convenção do Markdown) e
 * trocando cada um por um bloco de código com botão de copiar (JS em
 * app/Views/base_conhecimento/index.php cuida do clique). Texto fora dos
 * blocos vira parágrafo normal (nl2br + escapado).
 */
function kbRenderizarTexto(string $texto): string
{
    $partes = preg_split('/```(?:\w+)?\n?(.*?)```/s', $texto, -1, PREG_SPLIT_DELIM_CAPTURE);
    $html = '';

    foreach ($partes as $indice => $parte) {
        if ($indice % 2 === 1) {
            // capturado dentro de ``` -- e' o comando
            $comando = rtrim($parte, "\n");
            $html .= '<div class="kb-code-bloco">'
                . '<pre class="kb-code">' . htmlspecialchars($comando) . '</pre>'
                . '<button type="button" class="btn btn-sm btn-outline-secondary kb-botao-copiar" data-texto="' . htmlspecialchars($comando, ENT_QUOTES) . '" title="Copiar">'
                . '<i class="bi bi-clipboard"></i></button>'
                . '</div>';
        } elseif (trim($parte) !== '') {
            $html .= '<p class="mb-2">' . nl2br(htmlspecialchars($parte)) . '</p>';
        }
    }

    return $html;
}
