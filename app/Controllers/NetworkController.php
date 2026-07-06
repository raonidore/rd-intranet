<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\NetworkConfigService;
use App\Services\AuditService;

class NetworkController extends Controller
{
    private NetworkConfigService $service;

    public function __construct()
    {
        $this->service = new NetworkConfigService();
    }

    public function editarForm(): void
    {
        AuthMiddleware::checkModulo('infra_servidor');

        $interface = $_GET['interface'] ?? '';

        if (!in_array($interface, $this->service->interfacesValidas(), true)) {
            header('Location: ' . url('/infraestrutura/servidor'));
            exit;
        }

        $this->view('infrastructure/rede_editar', [
            'interface' => $interface,
            'atual'     => $this->service->configuracaoAtual($interface),
        ]);
    }

    public function aplicar(): void
    {
        AuthMiddleware::checkModulo('infra_servidor');
        header('Content-Type: application/json');

        $interface = $_POST['interface'] ?? '';
        $modo      = ($_POST['modo'] ?? '') === 'dhcp' ? 'dhcp' : 'estatico';
        $ipCidr    = trim($_POST['ip_cidr'] ?? '');
        $gateway   = trim($_POST['gateway'] ?? '');
        $dns       = trim($_POST['dns'] ?? '');

        $resultado = $this->service->aplicar($interface, $modo, $ipCidr, $gateway, $dns);

        if ($resultado['success']) {
            AuditService::registrar('Rede', 'Editar interface', "Configuração de rede aplicada em {$interface} (aguardando confirmação por 120s).");
        }

        echo json_encode($resultado);
    }

    public function confirmar(): void
    {
        AuthMiddleware::checkModulo('infra_servidor');
        header('Content-Type: application/json');

        $resultado = $this->service->confirmar();

        if ($resultado['success']) {
            AuditService::registrar('Rede', 'Confirmar', 'Alteração de rede confirmada e mantida definitivamente.');
        }

        echo json_encode($resultado);
    }

    public function status(): void
    {
        AuthMiddleware::checkModulo('infra_servidor');
        header('Content-Type: application/json');

        echo json_encode($this->service->statusRollback());
    }
}
