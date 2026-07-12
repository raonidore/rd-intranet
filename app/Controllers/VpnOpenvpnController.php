<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\CronService;
use App\Services\IptablesService;
use App\Services\NotificationService;
use App\Services\VpnOpenvpnService;

class VpnOpenvpnController extends Controller
{
    private const NOME_JOB_CRON = 'Coleta de tráfego VPN (OpenVPN)';

    private VpnOpenvpnService $service;

    public function __construct()
    {
        $this->service = new VpnOpenvpnService();
    }

    public function servidor(): void
    {
        AuthMiddleware::checkModulo('vpn_openvpn_servidor');

        $iptables = new IptablesService();

        $this->view('vpn/openvpn_servidor', [
            'status' => $this->service->status(),
            'firewallPendente' => $iptables->statusRollback(),
        ]);
    }

    public function instalar(): void
    {
        AuthMiddleware::checkModulo('vpn_openvpn_servidor');
        header('Content-Type: application/json');

        set_time_limit(120);

        $resultado = $this->service->instalar();

        AuditService::registrar('VPN OpenVPN', 'Instalar', $resultado['message'] ?? '');

        echo json_encode($resultado);
    }

    public function inicializarPki(): void
    {
        AuthMiddleware::checkModulo('vpn_openvpn_servidor');
        header('Content-Type: application/json');

        set_time_limit(180);

        $resultado = $this->service->inicializarPki();

        AuditService::registrar('VPN OpenVPN', 'Inicializar PKI', $resultado['message'] ?? '');

        echo json_encode($resultado);
    }

    public function salvarConfig(): void
    {
        AuthMiddleware::checkModulo('vpn_openvpn_servidor');

        $resultado = $this->service->salvarConfigServidor([
            'porta' => (int)($_POST['porta'] ?? 1194),
            'protocolo' => $_POST['protocolo'] ?? 'udp',
            'subnet_cidr' => trim($_POST['subnet_cidr'] ?? ''),
            'dns_push' => trim($_POST['dns_push'] ?? '') ?: null,
            'endpoint_publico' => trim($_POST['endpoint_publico'] ?? '') ?: null,
            'redirect_gateway' => isset($_POST['redirect_gateway']),
        ]);

        if ($resultado['success']) {
            AuditService::registrar('VPN OpenVPN', 'Salvar configuração do servidor', $resultado['message'] ?? '');
        }
        $this->notificarEVoltar($resultado, '/vpn/openvpn/servidor');
    }

    public function exporToggle(): void
    {
        AuthMiddleware::checkModulo('vpn_openvpn_servidor');
        header('Content-Type: application/json');

        $expor = ($_POST['expor'] ?? '0') === '1';
        $resultado = $this->service->exporConexaoInternet($expor);

        AuditService::registrar('VPN OpenVPN', $expor ? 'Expor à internet' : 'Deixar de expor à internet', $resultado['message'] ?? '');

        echo json_encode($resultado);
    }

    public function ativarColeta(): void
    {
        AuthMiddleware::checkModulo('vpn_openvpn_trafego');
        header('Content-Type: application/json');

        if ($this->coletaAtiva()) {
            echo json_encode(['success' => true, 'message' => 'Coleta já estava ativa.']);
            return;
        }

        $resultado = (new CronService())->criar([
            'nome' => self::NOME_JOB_CRON,
            'descricao' => 'Grava snapshot de tráfego por cliente OpenVPN (VPN > OpenVPN > Tráfego).',
            'expressao' => '*/5 * * * *',
            'usuario_execucao' => 'www-data',
            'comando' => 'php /var/www/rd.intranet/rd vpn:coletar-openvpn',
            'ativo' => true,
        ]);

        AuditService::registrar('VPN OpenVPN', 'Ativar coleta de tráfego', $resultado['message']);

        echo json_encode($resultado);
    }

    public function clientes(): void
    {
        AuthMiddleware::checkModulo('vpn_openvpn_clientes');

        $this->view('vpn/openvpn_clientes', [
            'status' => $this->service->status(),
        ]);
    }

    public function criarCliente(): void
    {
        AuthMiddleware::checkModulo('vpn_openvpn_clientes');
        header('Content-Type: application/json');

        set_time_limit(60);

        $nome = trim($_POST['nome'] ?? '');
        $resultado = $this->service->criarCliente($nome);

        if ($resultado['success']) {
            AuditService::registrar('VPN OpenVPN', 'Criar cliente', "Cliente \"{$nome}\" criado.");
        }

        echo json_encode($resultado);
    }

    public function baixarClienteNovamente(): void
    {
        AuthMiddleware::checkModulo('vpn_openvpn_clientes');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->baixarClienteNovamente($id);

        AuditService::registrar('VPN OpenVPN', 'Baixar config novamente', "Cliente #{$id}.");

        echo json_encode($resultado);
    }

    public function marcarEntregue(): void
    {
        AuthMiddleware::checkModulo('vpn_openvpn_clientes');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $this->service->marcarConfigEntregue($id);

        echo json_encode(['success' => true]);
    }

    public function revogarCliente(): void
    {
        AuthMiddleware::checkModulo('vpn_openvpn_clientes');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->revogarCliente($id);

        AuditService::registrar('VPN OpenVPN', 'Revogar cliente', "Cliente #{$id} revogado.");

        echo json_encode($resultado);
    }

    public function trafego(): void
    {
        AuthMiddleware::checkModulo('vpn_openvpn_trafego');

        $status = $this->service->status();
        $clienteId = (int)($_GET['cliente_id'] ?? ($status['clientes'][0]['id'] ?? 0));

        $this->view('vpn/openvpn_trafego', [
            'status' => $status,
            'clienteSelecionado' => $clienteId,
            'historico' => $clienteId ? $this->service->historicoTrafego($clienteId) : [],
            'coletaAtiva' => $this->coletaAtiva(),
        ]);
    }

    private function coletaAtiva(): bool
    {
        foreach ((new CronService())->listar() as $job) {
            if ($job['nome'] === self::NOME_JOB_CRON) {
                return true;
            }
        }

        return false;
    }

    private function notificarEVoltar(array $resultado, string $destino): void
    {
        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url($destino));
        exit;
    }
}
