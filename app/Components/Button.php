<?php

namespace App\Components;

class Button
{
    public static function primary(string $label, string $href = '#', string $icon = ''): string
    {
        $iconHtml = $icon ? "<i class=\"bi bi-{$icon} me-1\"></i>" : '';

        return "<a href=\"{$href}\" class=\"btn btn-primary\">{$iconHtml}{$label}</a>";
    }

    public static function outline(string $label, string $href = '#', string $icon = '', string $color = 'secondary'): string
    {
        $iconHtml = $icon ? "<i class=\"bi bi-{$icon}\"></i>" : '';

        return "<a href=\"{$href}\" class=\"btn btn-sm btn-outline-{$color}\" title=\"{$label}\">{$iconHtml}</a>";
    }
}
