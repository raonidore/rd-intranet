<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditoriaService;

class AuditoriaController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::checkModulo('auditoria');

        $service = new AuditoriaService();

        $registros = $service->listarUltimos();

        $this->view('auditoria/index', [
            'registros' => $registros
        ]);
    }
}
