<?php

namespace App\Actions\Contracts;

interface ActionInterface
{
    public function name(): string;

    public function preview(array $payload): array;

    public function execute(array $payload): void;
}
