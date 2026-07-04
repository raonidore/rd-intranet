<?php

namespace App\Core\Contracts;

interface DiscoveryEngineInterface
{
    public function module(): string;

    public function discover(): array;

    public function snapshot(): array;

    public function compare(): array;

    public function health(): array;
}
