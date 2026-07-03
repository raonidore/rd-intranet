<?php

namespace App\Services;

use App\Repositories\AuditoriaRepository;

class AuditoriaService
{
    private AuditoriaRepository $repository;

    public function __construct()
    {
        $this->repository = new AuditoriaRepository();
    }

    public function listarUltimos(): array
    {
        return $this->repository->ultimos(100);
    }
}
