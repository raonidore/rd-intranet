<?php

namespace App\Jobs\Contracts;

interface JobInterface
{
    /**
     * Executa o Job.
     */
    public function execute(): array;

    /**
     * Nome amigável.
     */
    public function name(): string;
}
