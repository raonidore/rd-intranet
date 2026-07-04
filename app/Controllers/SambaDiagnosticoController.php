<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\SambaDiagnosticoService;

class SambaDiagnosticoController extends Controller
{
    private SambaDiagnosticoService $service;

    public function __construct()
    {
        $this->service = new SambaDiagnosticoService();
    }

    public function index(): void
    {
        AuthMiddleware::check();

        $diagnostico = $this->service->executar();
        $achadosLogs = $this->service->interpretarLogs($diagnostico['logs']);

        $this->view('samba/diagnostico', [
            'diagnostico' => $diagnostico,
            'achadosLogs' => $achadosLogs,
        ]);
    }
}
