<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\SambaService;
use App\Middleware\AuthMiddleware;

class SambaController extends Controller
{
    private SambaService $service;

    public function __construct()
    {
        $this->service = new SambaService();
    }

    public function usuarios(): void
    {
        AuthMiddleware::check();

        $usuarios = $this->service->listarUsuarios();

        $dashboard = $this->service->dashboard();

        $this->view('samba/usuarios', [
            'usuarios' => $usuarios,
            'total' => $dashboard['total'],
            'ativos' => $dashboard['ativos'],
            'sshTotal' => $dashboard['ssh'],
        ]);
    }
}
