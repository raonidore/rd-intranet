<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\VpnIkev2SaidaService;

class VpnIkev2SaidaController extends Controller
{
    private VpnIkev2SaidaService $service;

    public function __construct()
    {
        $this->service = new VpnIkev2SaidaService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('vpn_ikev2_saida');

        $this->view('vpn/ikev2_saida', [
            'conexoes' => $this->service->listar(),
        ]);
    }

    public function novoForm(): void
    {
        AuthMiddleware::checkModulo('vpn_ikev2_saida');

        $this->view('vpn/ikev2_saida_form', []);
    }

    public function novo(): void
    {
        AuthMiddleware::checkModulo('vpn_ikev2_saida');
        header('Content-Type: application/json');

        $resultado = $this->service->criar($_POST);

        if ($resultado['success']) {
            $nome = trim($_POST['nome'] ?? '');
            AuditService::registrar('VPN IKEv2 (saída)', 'Criar conexão', "Conexão \"{$nome}\" criada.");
        }

        echo json_encode($resultado);
    }

    public function conectar(): void
    {
        AuthMiddleware::checkModulo('vpn_ikev2_saida');
        header('Content-Type: application/json');

        set_time_limit(30);

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->conectar($id);

        AuditService::registrar('VPN IKEv2 (saída)', 'Conectar', "Conexão #{$id}: " . ($resultado['message'] ?? ''));

        echo json_encode($resultado);
    }

    public function desconectar(): void
    {
        AuthMiddleware::checkModulo('vpn_ikev2_saida');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->desconectar($id);

        AuditService::registrar('VPN IKEv2 (saída)', 'Desconectar', "Conexão #{$id}.");

        echo json_encode($resultado);
    }

    public function alternarBoot(): void
    {
        AuthMiddleware::checkModulo('vpn_ikev2_saida');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $ativo = ($_POST['ativo'] ?? '0') === '1';

        echo json_encode($this->service->alternarAtivoNoBoot($id, $ativo));
    }

    public function remover(): void
    {
        AuthMiddleware::checkModulo('vpn_ikev2_saida');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->remover($id);

        AuditService::registrar('VPN IKEv2 (saída)', 'Remover conexão', "Conexão #{$id} removida.");

        echo json_encode($resultado);
    }
}
