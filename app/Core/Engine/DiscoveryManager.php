<?php

namespace App\Core\Engine;

use App\Core\Contracts\DiscoveryEngineInterface;

class DiscoveryManager
{
    /**
     * @var DiscoveryEngineInterface[]
     */
    private array $engines = [];

    public function register(DiscoveryEngineInterface $engine): void
    {
        $this->engines[$engine->module()] = $engine;
    }

    public function run(string $module): array
    {
        if (!isset($this->engines[$module])) {
            throw new \RuntimeException("Discovery Engine '{$module}' não registrado.");
        }

        return $this->engines[$module]->snapshot();
    }

    public function runAll(): array
    {
        $result = [];

        foreach ($this->engines as $name => $engine) {
            $result[$name] = $engine->snapshot();
        }

        return $result;
    }
}
