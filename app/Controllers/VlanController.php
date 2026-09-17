<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\VlanService;

class VlanController extends Controller
{
    private VlanService $service;

    public function __construct()
    {
        $this->service = new VlanService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('infra_vlan');

        $interfaces = $this->service->interfacesFisicasDisponiveis();

        $this->view('infrastructure/vlan', [
            'vlans' => $this->service->listar(),
            'interfaces' => $interfaces,
            'proximoIdSugerido' => !empty($interfaces) ? $this->service->proximoIdSugerido($interfaces[0]) : 10,
        ]);
    }

    public function proximoId(): void
    {
        AuthMiddleware::checkModulo('infra_vlan');
        header('Content-Type: application/json');

        $interface = $_GET['interface'] ?? '';
        echo json_encode(['id' => $this->service->proximoIdSugerido($interface)]);
    }

    public function salvar(): void
    {
        AuthMiddleware::checkModulo('infra_vlan');
        header('Content-Type: application/json');

        echo json_encode($this->service->salvar($_POST));
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('infra_vlan');
        header('Content-Type: application/json');

        echo json_encode($this->service->excluir((int)($_POST['id'] ?? 0)));
    }

    public function aplicar(): void
    {
        AuthMiddleware::checkModulo('infra_vlan');
        header('Content-Type: application/json');

        echo json_encode($this->service->aplicar());
    }

    public function confirmar(): void
    {
        AuthMiddleware::checkModulo('infra_vlan');
        header('Content-Type: application/json');

        echo json_encode($this->service->confirmar());
    }

    public function reverterAgora(): void
    {
        AuthMiddleware::checkModulo('infra_vlan');
        header('Content-Type: application/json');

        echo json_encode($this->service->reverterAgora());
    }

    public function status(): void
    {
        AuthMiddleware::checkModulo('infra_vlan');
        header('Content-Type: application/json');

        echo json_encode($this->service->statusRollback());
    }

    public function guia(): void
    {
        AuthMiddleware::checkQualquerModulo(['infra_dhcp', 'infra_vlan']);

        $this->view('infrastructure/guia_dhcp_vlan', []);
    }
}
