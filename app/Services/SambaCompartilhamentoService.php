<?php

namespace App\Services;

use App\Repositories\SambaCompartilhamentoRepository;

class SambaCompartilhamentoService
{
    private SambaCompartilhamentoRepository $repository;

    public function __construct()
    {
        $this->repository = new SambaCompartilhamentoRepository();
    }

    public function listar(): array
    {
        return $this->repository->listar();
    }

    public function dashboard(): array
    {
        return [
            'total' => $this->repository->contarTotal(),
            'ativos' => $this->repository->contarAtivos(),
            'lixeira' => $this->repository->contarComLixeira(),
            'bloqueio_extensoes' => $this->repository->contarComBloqueioExtensoes(),
        ];
    }

    public function buscar(int $id): ?array
    {
        return $this->repository->buscarPorId($id);
    }
}
