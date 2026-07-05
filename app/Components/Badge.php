<?php

namespace App\Components;

class Badge
{
    public static function make(string $label, string $color = 'secondary'): string
    {
        return "<span class=\"badge text-bg-{$color}\">{$label}</span>";
    }

    public static function status(string $status): string
    {
        return match ($status) {
            'ativo' => self::make('Ativo', 'success'),
            'desativado' => self::make('Desativado', 'danger'),
            default => self::make($status, 'secondary')
        };
    }

    public static function departamento(string $departamento): string
    {
        return self::make(htmlspecialchars($departamento), 'secondary');
    }
}
