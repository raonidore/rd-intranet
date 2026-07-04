<?php

namespace App\Modules\Samba\Discovery;

use App\Core\Contracts\DiscoveryEngineInterface;
use App\Services\SambaInventoryService;
use App\Services\SambaDiscoveryService;
use App\Core\Samba\Health\SambaHealthEngine;

class SambaDiscoveryEngine implements DiscoveryEngineInterface
{
    private SambaInventoryService $inventory;
    private SambaDiscoveryService $discovery;
    private SambaHealthEngine $health;

    public function __construct()
    {
        $this->inventory = new SambaInventoryService();
        $this->discovery = new SambaDiscoveryService();
        $this->health = new SambaHealthEngine();
    }

    public function module(): string
    {
        return 'samba';
    }

    public function discover(): array
    {
        return $this->discovery->compararBancoLinux();
    }

    public function snapshot(): array
    {
        $inventory = $this->inventory->snapshot();

        return [
            'module' => 'samba',
            'generated_at' => date('Y-m-d H:i:s'),
            'inventory' => $inventory,
            'discovery' => $this->discover(),
            'health' => $this->health->analyze($inventory)
        ];
    }

    public function compare(): array
    {
        return $this->discover();
    }

    public function health(): array
    {
        return $this->health->analyze(
            $this->inventory->snapshot()
        );
    }
}
