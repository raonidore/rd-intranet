<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\SambaUsuario;

class SambaController extends Controller
{
    public function usuarios(): void
    {
        $usuarios = SambaUsuario::listar();

        $total = SambaUsuario::contarTotal();
        $ativos = SambaUsuario::contarAtivos();
        $sshTotal = SambaUsuario::contarComSsh();

        $this->view('samba/usuarios', compact(
            'usuarios',
            'total',
            'ativos',
            'sshTotal'
        ));
    }
}
