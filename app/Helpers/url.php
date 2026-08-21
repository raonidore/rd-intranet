<?php

use App\Services\ConfigService;

function url(string $path = ''): string
{
    $base = ConfigService::get('base_url', '/rd.intranet');

    if ($path === '') {
        return $base;
    }

    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

/**
 * Igual a url(), mas anexa "?v=<mtime do arquivo>" -- sem isso, editar um
 * CSS/JS estático (ex: rd-ui.css, rd-diagnostico.js) não tem efeito pra
 * quem já tinha a versão antiga em cache até o navegador decidir revalidar
 * por conta própria (visto ao vivo: overlay de carregamento sem estilo
 * nenhum num cliente já atualizado, porque o rd-ui.css antigo continuava
 * em cache). O mtime muda sozinho a cada deploy que tocar o arquivo, sem
 * precisar lembrar de bumpar um número de versão à mão.
 */
function asset_url(string $path): string
{
    $relativo = ltrim($path, '/');
    $absoluto = __DIR__ . '/../../public/' . $relativo;

    $versao = is_file($absoluto) ? filemtime($absoluto) : time();

    return url('/' . $relativo) . '?v=' . $versao;
}
