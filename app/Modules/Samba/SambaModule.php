<?php

namespace App\Modules\Samba;

use App\Core\Contracts\ModuleInterface;

class SambaModule implements ModuleInterface
{
    public function name(): string
    {
        return 'samba';
    }

    public function displayName(): string
    {
        return 'Samba';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function boot(): void
    {
        // Futuramente registraremos Discovery,
        // Recommendation, Repair, Deploy etc.
    }

    public function info(): array
    {
        return [
            'name' => $this->name(),
            'display_name' => $this->displayName(),
            'version' => $this->version()
        ];
    }
}
