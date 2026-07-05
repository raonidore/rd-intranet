<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\SambaGrupoService;

class SambaGrupoController extends Controller
{
    private SambaGrupoService $service;

    public function __construct()
    {
        $this->service = new SambaGrupoService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('samba_grupos');

        $this->view('samba/grupos', [
            'grupos' => $this->service->listarComDetalhes(),
        ]);
    }
}
