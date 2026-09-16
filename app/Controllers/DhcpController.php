<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\DhcpService;

class DhcpController extends Controller
{
    private DhcpService $service;

    public function __construct()
    {
        $this->service = new DhcpService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('infra_dhcp');

        $instalado = $this->service->instalado();
        $status = $instalado ? $this->service->statusAoVivo() : ['servico_ativo' => false, 'leases' => []];

        $this->view('infrastructure/dhcp', [
            'instalado' => $instalado,
            'config' => $this->service->config(),
            'interfaces' => $this->service->interfacesDisponiveis(),
            'subnets' => $this->service->listarSubnets(),
            'reservas' => $this->service->listarReservas(),
            'servicoAtivo' => $status['servico_ativo'],
            'leases' => $status['leases'],
        ]);
    }

    public function instalar(): void
    {
        AuthMiddleware::checkModulo('infra_dhcp');
        header('Content-Type: application/json');

        echo json_encode($this->service->instalar());
    }

    public function salvarConfig(): void
    {
        AuthMiddleware::checkModulo('infra_dhcp');
        header('Content-Type: application/json');

        echo json_encode($this->service->salvarConfig($_POST));
    }

    public function salvarSubnet(): void
    {
        AuthMiddleware::checkModulo('infra_dhcp');
        header('Content-Type: application/json');

        echo json_encode($this->service->salvarSubnet($_POST));
    }

    public function excluirSubnet(): void
    {
        AuthMiddleware::checkModulo('infra_dhcp');
        header('Content-Type: application/json');

        echo json_encode($this->service->excluirSubnet((int)($_POST['id'] ?? 0)));
    }

    public function criarReserva(): void
    {
        AuthMiddleware::checkModulo('infra_dhcp');
        header('Content-Type: application/json');

        echo json_encode($this->service->criarReserva($_POST));
    }

    public function excluirReserva(): void
    {
        AuthMiddleware::checkModulo('infra_dhcp');
        header('Content-Type: application/json');

        echo json_encode($this->service->excluirReserva((int)($_POST['id'] ?? 0)));
    }

    public function aplicar(): void
    {
        AuthMiddleware::checkModulo('infra_dhcp');
        header('Content-Type: application/json');

        echo json_encode($this->service->aplicar());
    }

    public function confirmar(): void
    {
        AuthMiddleware::checkModulo('infra_dhcp');
        header('Content-Type: application/json');

        echo json_encode($this->service->confirmar());
    }

    public function reverterAgora(): void
    {
        AuthMiddleware::checkModulo('infra_dhcp');
        header('Content-Type: application/json');

        echo json_encode($this->service->reverterAgora());
    }

    public function status(): void
    {
        AuthMiddleware::checkModulo('infra_dhcp');
        header('Content-Type: application/json');

        echo json_encode($this->service->statusRollback());
    }

    public function statusAoVivo(): void
    {
        AuthMiddleware::checkModulo('infra_dhcp');
        header('Content-Type: application/json');

        echo json_encode($this->service->statusAoVivo());
    }

    public function ligar(): void
    {
        AuthMiddleware::checkModulo('infra_dhcp');
        header('Content-Type: application/json');

        echo json_encode($this->service->ligar());
    }

    public function desligar(): void
    {
        AuthMiddleware::checkModulo('infra_dhcp');
        header('Content-Type: application/json');

        echo json_encode($this->service->desligar());
    }
}
