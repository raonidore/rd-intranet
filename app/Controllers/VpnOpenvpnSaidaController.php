<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\VpnOpenvpnSaidaService;

class VpnOpenvpnSaidaController extends Controller
{
    private VpnOpenvpnSaidaService $service;

    public function __construct()
    {
        $this->service = new VpnOpenvpnSaidaService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('vpn_openvpn_saida');

        $this->view('vpn/openvpn_saida', [
            'conexoes' => $this->service->listar(),
        ]);
    }

    public function novoForm(): void
    {
        AuthMiddleware::checkModulo('vpn_openvpn_saida');

        $this->view('vpn/openvpn_saida_form', []);
    }

    public function novo(): void
    {
        AuthMiddleware::checkModulo('vpn_openvpn_saida');
        header('Content-Type: application/json');

        $nome = trim($_POST['nome'] ?? '');
        $conteudo = $_POST['conteudo_ovpn'] ?? '';

        $resultado = $this->service->criar($nome, $conteudo);

        if ($resultado['success']) {
            AuditService::registrar('VPN OpenVPN (saída)', 'Criar conexão', "Conexão \"{$nome}\" criada.");
        }

        echo json_encode($resultado);
    }

    public function conectar(): void
    {
        AuthMiddleware::checkModulo('vpn_openvpn_saida');
        header('Content-Type: application/json');

        set_time_limit(30);

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->conectar($id);

        AuditService::registrar('VPN OpenVPN (saída)', 'Conectar', "Conexão #{$id}: " . ($resultado['message'] ?? ''));

        echo json_encode($resultado);
    }

    public function desconectar(): void
    {
        AuthMiddleware::checkModulo('vpn_openvpn_saida');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->desconectar($id);

        AuditService::registrar('VPN OpenVPN (saída)', 'Desconectar', "Conexão #{$id}.");

        echo json_encode($resultado);
    }

    public function alternarBoot(): void
    {
        AuthMiddleware::checkModulo('vpn_openvpn_saida');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $ativo = ($_POST['ativo'] ?? '0') === '1';

        $resultado = $this->service->alternarAtivoNoBoot($id, $ativo);

        echo json_encode($resultado);
    }

    public function remover(): void
    {
        AuthMiddleware::checkModulo('vpn_openvpn_saida');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->remover($id);

        AuditService::registrar('VPN OpenVPN (saída)', 'Remover conexão', "Conexão #{$id} removida.");

        echo json_encode($resultado);
    }
}
