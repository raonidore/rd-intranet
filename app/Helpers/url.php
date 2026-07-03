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
