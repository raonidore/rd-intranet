<?php

namespace App\Services;

use App\Repositories\SambaUsuarioRepository;

class SambaService
{
    private SambaUsuarioRepository $repository;

    public function __construct()
    {
        $this->repository = new SambaUsuarioRepository();
    }

    public function listarUsuarios(): array
    {
        return $this->repository->listar();
    }

    public function dashboard(): array
    {
        return [
            'total' => $this->repository->contarTotal(),
            'ativos' => $this->repository->contarAtivos(),
            'ssh' => $this->repository->contarComSSH(),
            'compartilhamentos' => 3
        ];
    }

    public function buscarUsuario(int $id): ?array
    {
        return $this->repository->buscarPorId($id);
    }
}
