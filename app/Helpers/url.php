<?php

function url(string $path = ''): string
{
    $base = '/rd.intranet';

    if ($path === '') {
        return $base;
    }

    return $base . '/' . ltrim($path, '/');
}
