<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\SystemServiceManager;

class InfrastructureController extends Controller
{
    private SystemServiceManager $serviceManager;

    public function __construct()
    {
        $this->serviceManager = new SystemServiceManager();
    }

    public function servicos(): void
    {
        AuthMiddleware::check();

        $servicos = [];

        foreach ($this->serviceManager->listarServicos() as $chave => $nome) {
            $servicos[] = [
                'chave' => $chave,
                'nome' => $nome,
                'status' => $this->serviceManager->status($chave)
            ];
        }

        $this->view('infrastructure/servicos', [
            'servicos' => $servicos
        ]);
    }

    public function reiniciar(): void
    {
        AuthMiddleware::check();

        $servico = $_GET['service'] ?? '';

        $this->serviceManager->reiniciar($servico);

        header('Location: ' . url('/infraestrutura/servicos'));
        exit;
    }

    public function recarregar(): void
    {
        AuthMiddleware::check();

        $servico = $_GET['service'] ?? '';

        $this->serviceManager->recarregar($servico);

        header('Location: ' . url('/infraestrutura/servicos'));
        exit;
    }

    public function logs(): void
    {
        AuthMiddleware::check();

        $servico = $_GET['service'] ?? '';

        $logs = $this->serviceManager->logs($servico);

        $this->view('infrastructure/logs', [
            'servico' => $servico,
            'logs' => $logs
        ]);
    }
}
