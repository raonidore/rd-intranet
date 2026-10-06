<?php

/**
 * Transforma endereços (http://, https:// e www.) em link clicável, abrindo
 * em nova aba. Recebe texto JÁ escapado (htmlspecialchars) -- só cria <a>
 * pra esses esquemas, então nunca vira "javascript:". Pontuação colada no
 * fim (".", ",", ")"...) fica fora do link; endereço longo aparece
 * encurtado, com o completo no title.
 */
function linkificar(string $htmlEscapado, int $maxExibido = 60): string
{
    return (string)preg_replace_callback(
        '~\b(?:https?://|www\.)[^\s<]+~iu',
        function (array $m) use ($maxExibido): string {
            $link = $m[0];
            $sobra = '';
            // Pontuação de fim de frase e fechamento de parênteses sem abertura no link.
            while ($link !== '' && preg_match('~(?:[.,;:!?\'"]|&quot;|&#039;|\))$~', $link, $fim)) {
                if ($fim[0] === ')' && substr_count($link, '(') >= substr_count($link, ')')) {
                    break;
                }
                $sobra = $fim[0] . $sobra;
                $link = substr($link, 0, -strlen($fim[0]));
            }
            if ($link === '') {
                return $m[0];
            }

            $real = html_entity_decode($link, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $href = preg_match('~^https?://~i', $real) ? $real : 'http://' . $real;
            $exibido = mb_strlen($real) > $maxExibido ? mb_substr($real, 0, $maxExibido - 1) . '…' : $real;

            return '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '" target="_blank" rel="noopener noreferrer"'
                . ' title="' . htmlspecialchars($real, ENT_QUOTES) . '">' . htmlspecialchars($exibido) . '</a>' . $sobra;
        },
        $htmlEscapado
    );
}
