<?php

namespace App\Components;

class Avatar
{
    public static function initials(string $nome): string
    {
        $partes = preg_split('/\s+/', trim($nome));
        $ini = '';

        foreach ($partes as $p) {
            if ($p !== '') {
                $ini .= mb_strtoupper(mb_substr($p, 0, 1));
            }

            if (mb_strlen($ini) >= 2) {
                break;
            }
        }

        $ini = $ini ?: '?';

        return "<div class=\"avatar\">{$ini}</div>";
    }
}
