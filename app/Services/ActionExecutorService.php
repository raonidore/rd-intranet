<?php

namespace App\Services;

use App\Actions\Contracts\ActionInterface;

class ActionExecutorService
{
    public function preview(ActionInterface $action, array $payload): array
    {
        return $action->preview($payload);
    }

    public function execute(ActionInterface $action, array $payload): void
    {
        $action->execute($payload);
    }
}
