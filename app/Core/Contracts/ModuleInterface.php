<?php

namespace App\Core\Contracts;

interface ModuleInterface
{
    /**
     * Nome único do módulo.
     */
    public function name(): string;

    /**
     * Nome amigável.
     */
    public function displayName(): string;

    /**
     * Versão.
     */
    public function version(): string;

    /**
     * Registra todos os serviços do módulo.
     */
    public function boot(): void;

    /**
     * Retorna informações do módulo.
     */
    public function info(): array;
}
