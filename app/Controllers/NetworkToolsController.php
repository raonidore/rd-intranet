<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\NetworkToolsService;
use App\Services\TrafegoHistoricoService;
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

    public function mtrForm(): void
    {
        AuthMiddleware::checkModulo('infra_rede');

        $this->view('infrastructure/rede_mtr', ['destino' => '', 'resultado' => null, 'saltos' => []]);
    }

    public function mtrExecutar(): void
    {
        AuthMiddleware::checkModulo('infra_rede');

        set_time_limit(60);

        $destino = trim($_POST['destino'] ?? '');
        $resultado = $this->service->mtr($destino);

        AuditService::registrar('Rede', 'MTR', "MTR para {$destino}.");

        $saltos = $resultado['success'] ? $this->service->parsearMtr($resultado['output']) : [];

        $this->view('infrastructure/rede_mtr', [
            'destino' => $destino,
            'resultado' => $resultado,
            'saltos' => $saltos,
        ]);
    }

    public function dnsForm(): void
    {
        AuthMiddleware::checkModulo('infra_rede');

        $this->view('infrastructure/rede_dns', ['dominio' => '', 'resultado' => null, 'resolvconf' => '', 'resolvers' => []]);
    }

    public function dnsExecutar(): void
    {
        AuthMiddleware::checkModulo('infra_rede');

        $dominio = trim($_POST['dominio'] ?? '');
        $resultado = $this->service->verificarDns($dominio);

        AuditService::registrar('Rede', 'Verificação DNS', "Consulta DNS para {$dominio}.");

        $parseado = $resultado['success'] ? $this->service->parsearDns($resultado['output']) : ['resolvconf' => '', 'resolvers' => []];

        $this->view('infrastructure/rede_dns', [
            'dominio' => $dominio,
            'resultado' => $resultado,
            'resolvconf' => $parseado['resolvconf'],
            'resolvers' => $parseado['resolvers'],
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

    public function historico(): void
    {
        AuthMiddleware::checkModulo('infra_rede');

        $dias = (int)($_GET['dias'] ?? 30);
        $dias = $dias > 0 && $dias <= 365 ? $dias : 30;

        $this->view('infrastructure/rede_trafego_historico', [
            'dias' => $dias,
            'consumo' => (new TrafegoHistoricoService())->consumoDiario($dias),
        ]);
    }
}
