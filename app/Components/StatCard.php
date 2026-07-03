<?php

namespace App\Components;

class StatCard
{
    public static function make(string $label, string|int $value): string
    {
        return "
            <div class=\"col-md-3\">
                <div class=\"card border-0 shadow-sm\">
                    <div class=\"card-body\">
                        <small class=\"text-muted\">{$label}</small>
                        <h3>{$value}</h3>
                    </div>
                </div>
            </div>
        ";
    }
}
