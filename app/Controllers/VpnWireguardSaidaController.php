<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\VpnWireguardSaidaService;

class VpnWireguardSaidaController extends Controller
{
    private VpnWireguardSaidaService $service;

    public function __construct()
    {
        $this->service = new VpnWireguardSaidaService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('vpn_wireguard_saida');

        $this->view('vpn/wireguard_saida', [
            'conexoes' => $this->service->listar(),
        ]);
    }

    public function novoForm(): void
    {
        AuthMiddleware::checkModulo('vpn_wireguard_saida');

        $this->view('vpn/wireguard_saida_form', []);
    }

    public function gerarChave(): void
    {
        AuthMiddleware::checkModulo('vpn_wireguard_saida');
        header('Content-Type: application/json');

        echo json_encode($this->service->gerarParDeChaves());
    }

    public function novo(): void
    {
        AuthMiddleware::checkModulo('vpn_wireguard_saida');
        header('Content-Type: application/json');

        $nome = trim($_POST['nome'] ?? '');
        $conteudo = $_POST['conteudo_conf'] ?? '';

        $resultado = $this->service->criar($nome, $conteudo);

        if ($resultado['success']) {
            AuditService::registrar('VPN WireGuard (saída)', 'Criar conexão', "Conexão \"{$nome}\" criada.");
        }

        echo json_encode($resultado);
    }

    public function conectar(): void
    {
        AuthMiddleware::checkModulo('vpn_wireguard_saida');
        header('Content-Type: application/json');

        set_time_limit(30);

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->conectar($id);

        AuditService::registrar('VPN WireGuard (saída)', 'Conectar', "Conexão #{$id}: " . ($resultado['message'] ?? ''));

        echo json_encode($resultado);
    }

    public function desconectar(): void
    {
        AuthMiddleware::checkModulo('vpn_wireguard_saida');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->desconectar($id);

        AuditService::registrar('VPN WireGuard (saída)', 'Desconectar', "Conexão #{$id}.");

        echo json_encode($resultado);
    }

    public function alternarBoot(): void
    {
        AuthMiddleware::checkModulo('vpn_wireguard_saida');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $ativo = ($_POST['ativo'] ?? '0') === '1';

        echo json_encode($this->service->alternarAtivoNoBoot($id, $ativo));
    }

    public function remover(): void
    {
        AuthMiddleware::checkModulo('vpn_wireguard_saida');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->remover($id);

        AuditService::registrar('VPN WireGuard (saída)', 'Remover conexão', "Conexão #{$id} removida.");

        echo json_encode($resultado);
    }
}
