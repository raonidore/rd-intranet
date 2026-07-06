<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\NetworkToolsService;
use App\Services\AuditService;

class NetworkToolsController extends Controller
{
    private NetworkToolsService $service;

    public function __construct()
    {
        $this->service = new NetworkToolsService();
    }

    public function arp(): void
    {
        AuthMiddleware::checkModulo('infra_rede');

        $this->view('infrastructure/rede_arp', [
            'linhas' => $this->service->arp(),
        ]);
    }

    public function pingForm(): void
    {
        AuthMiddleware::checkModulo('infra_rede');

        $this->view('infrastructure/rede_ping', ['destino' => '', 'resultado' => null]);
    }

    public function pingExecutar(): void
    {
        AuthMiddleware::checkModulo('infra_rede');

        $destino = trim($_POST['destino'] ?? '');
        $resultado = $this->service->ping($destino);

        AuditService::registrar('Rede', 'Ping', "Ping para {$destino}.");

        $this->view('infrastructure/rede_ping', ['destino' => $destino, 'resultado' => $resultado]);
    }

    public function tracerouteForm(): void
    {
        AuthMiddleware::checkModulo('infra_rede');

        $this->view('infrastructure/rede_traceroute', ['destino' => '', 'resultado' => null, 'saltos' => [], 'cabecalho' => '']);
    }

    public function tracerouteExecutar(): void
    {
        AuthMiddleware::checkModulo('infra_rede');

        $destino = trim($_POST['destino'] ?? '');
        $resultado = $this->service->traceroute($destino);

        AuditService::registrar('Rede', 'Traceroute', "Traceroute para {$destino}.");

        $saltos = [];
        $cabecalho = '';

        if ($resultado['success']) {
            $linhas = explode("\n", trim($resultado['output']));
            $cabecalho = $linhas[0] ?? '';
            $saltos = $this->service->parsearTraceroute($resultado['output']);
        }

        $this->view('infrastructure/rede_traceroute', [
            'destino' => $destino,
            'resultado' => $resultado,
            'saltos' => $saltos,
            'cabecalho' => $cabecalho,
        ]);
    }

    public function trafego(): void
    {
        AuthMiddleware::checkModulo('infra_rede');

        $this->view('infrastructure/rede_trafego', []);
    }

    public function trafegoApi(): void
    {
        AuthMiddleware::checkModulo('infra_rede');

        header('Content-Type: application/json');
        header('Cache-Control: no-cache');

        echo json_encode([
            'timestamp' => microtime(true),
            'interfaces' => $this->service->trafegoInterfaces(),
        ]);
    }
}
