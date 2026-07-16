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
        AuthMiddleware::checkModulo('samba_diagnostico');

        $diagnostico = $this->service->executar();
        $achadosLogs = $this->service->interpretarLogs($diagnostico['logs']);

        $this->view('samba/diagnostico', [
            'diagnostico' => $diagnostico,
            'achadosLogs' => $achadosLogs,
        ]);
    }

    public function logsCompletos(): void
    {
        AuthMiddleware::checkModulo('samba_diagnostico');
        header('Content-Type: application/json');

        echo json_encode($this->service->logsCompletos());
    }
}
