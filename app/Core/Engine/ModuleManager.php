<?php

namespace App\Core\Engine;

use App\Core\Contracts\ModuleInterface;

class ModuleManager
{
    /**
     * @var ModuleInterface[]
     */
    private array $modules = [];

    public function register(ModuleInterface $module): void
    {
        $this->modules[$module->name()] = $module;
    }

    public function boot(): void
    {
        foreach ($this->modules as $module) {
            $module->boot();
        }
    }

    public function all(): array
    {
        return $this->modules;
    }

    public function info(): array
    {
        $items = [];

        foreach ($this->modules as $module) {
            $items[] = $module->info();
        }

        return $items;
    }
}
