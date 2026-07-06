<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\NetworkRouteService;
use App\Services\AuditService;

class NetworkRouteController extends Controller
{
    private NetworkRouteService $service;

    public function __construct()
    {
        $this->service = new NetworkRouteService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('infra_rede');

        $this->view('infrastructure/rede_rotas', [
            'rotas' => $this->service->listar(),
        ]);
    }

    public function novoForm(): void
    {
        AuthMiddleware::checkModulo('infra_rede');

        $this->view('infrastructure/rede_rotas_novo', [
            'interfaces' => $this->service->interfacesValidas(),
        ]);
    }

    public function novo(): void
    {
        AuthMiddleware::checkModulo('infra_rede');
        header('Content-Type: application/json');

        $destino = trim($_POST['destino'] ?? '');
        $via = trim($_POST['via'] ?? '');
        $dev = trim($_POST['dev'] ?? '');

        $resultado = $this->service->aplicar($destino, $via, $dev);

        if ($resultado['success']) {
            AuditService::registrar('Rede', 'Aplicar rota', "Rota {$destino} via {$via} dev {$dev} aplicada (aguardando confirmação por 120s).");
        }

        echo json_encode($resultado);
    }

    public function confirmar(): void
    {
        AuthMiddleware::checkModulo('infra_rede');
        header('Content-Type: application/json');

        $resultado = $this->service->confirmar();

        if ($resultado['success']) {
            AuditService::registrar('Rede', 'Confirmar rota', 'Rota confirmada e persistida.');
        }

        echo json_encode($resultado);
    }

    public function status(): void
    {
        AuthMiddleware::checkModulo('infra_rede');
        header('Content-Type: application/json');

        echo json_encode($this->service->statusRollback());
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('infra_rede');
        header('Content-Type: application/json');

        $destino = trim($_POST['destino'] ?? '');
        $resultado = $this->service->excluir($destino);

        if ($resultado['success']) {
            AuditService::registrar('Rede', 'Excluir rota', "Rota {$destino} removida.");
        }

        echo json_encode($resultado);
    }

    public function testar(): void
    {
        AuthMiddleware::checkModulo('infra_rede');
        header('Content-Type: application/json');

        $via = trim($_POST['via'] ?? '');
        $resultado = $this->service->testar($via);

        echo json_encode($resultado);
    }
}
