<?php

namespace App\Services;

use App\Models\SambaUsuario;

class SambaService
{
    public function listarUsuarios(): array
    {
        return SambaUsuario::listar();
    }

    public function dashboard(): array
    {
        return [

            'total' => SambaUsuario::contarTotal(),

            'ativos' => SambaUsuario::contarAtivos(),

            'ssh' => SambaUsuario::contarComSsh(),

            'compartilhamentos' => 3

        ];
    }
}
