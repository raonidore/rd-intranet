<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\CronService;
use App\Services\IptablesService;
use App\Services\NotificationService;
use App\Services\VpnWireguardService;

class VpnWireguardController extends Controller
{
    private const NOME_JOB_CRON = 'Coleta de tráfego VPN (WireGuard)';

    private VpnWireguardService $service;

    public function __construct()
    {
        $this->service = new VpnWireguardService();
    }

    public function servidor(): void
    {
        AuthMiddleware::checkModulo('vpn_wireguard_servidor');

        $iptables = new IptablesService();

        $this->view('vpn/wireguard_servidor', [
            'status' => $this->service->status(),
            'firewallPendente' => $iptables->statusRollback(),
            'coletaAtiva' => $this->coletaAtiva(),
        ]);
    }

    public function instalar(): void
    {
        AuthMiddleware::checkModulo('vpn_wireguard_servidor');
        header('Content-Type: application/json');

        set_time_limit(120);

        $resultado = $this->service->instalar();

        AuditService::registrar('VPN WireGuard', 'Instalar', $resultado['message'] ?? '');

        echo json_encode($resultado);
    }

    public function salvarConfig(): void
    {
        AuthMiddleware::checkModulo('vpn_wireguard_servidor');

        $resultado = $this->service->salvarConfigServidor([
            'interface_nome' => trim($_POST['interface_nome'] ?? 'wg0'),
            'porta' => (int)($_POST['porta'] ?? 51820),
            'subnet_cidr' => trim($_POST['subnet_cidr'] ?? ''),
            'servidor_ip_interno' => trim($_POST['servidor_ip_interno'] ?? ''),
            'dns_push' => trim($_POST['dns_push'] ?? '') ?: null,
            'endpoint_publico' => trim($_POST['endpoint_publico'] ?? '') ?: null,
            'mtu' => (int)($_POST['mtu'] ?? 0) ?: null,
        ]);

        if ($resultado['success']) {
            AuditService::registrar('VPN WireGuard', 'Salvar configuração do servidor', $resultado['message'] ?? '');
        }
        $this->notificarEVoltar($resultado);
    }

    public function exporToggle(): void
    {
        AuthMiddleware::checkModulo('vpn_wireguard_servidor');
        header('Content-Type: application/json');

        $expor = ($_POST['expor'] ?? '0') === '1';
        $resultado = $this->service->exporConexaoInternet($expor);

        AuditService::registrar('VPN WireGuard', $expor ? 'Expor à internet' : 'Deixar de expor à internet', $resultado['message'] ?? '');

        echo json_encode($resultado);
    }

    public function ativarColeta(): void
    {
        AuthMiddleware::checkModulo('vpn_wireguard_trafego');
        header('Content-Type: application/json');

        if ($this->coletaAtiva()) {
            echo json_encode(['success' => true, 'message' => 'Coleta já estava ativa.']);
            return;
        }

        $resultado = (new CronService())->criar([
            'nome' => self::NOME_JOB_CRON,
            'descricao' => 'Grava snapshot de tráfego/handshake por peer WireGuard (VPN > WireGuard > Tráfego).',
            'expressao' => '*/5 * * * *',
            'usuario_execucao' => 'www-data',
            'comando' => 'php /var/www/rd.intranet/rd vpn:coletar-wireguard',
            'ativo' => true,
        ]);

        AuditService::registrar('VPN WireGuard', 'Ativar coleta de tráfego', $resultado['message']);

        echo json_encode($resultado);
    }

    public function peers(): void
    {
        AuthMiddleware::checkModulo('vpn_wireguard_peers');

        $this->view('vpn/wireguard_peers', [
            'status' => $this->service->status(),
        ]);
    }

    public function criarPeer(): void
    {
        AuthMiddleware::checkModulo('vpn_wireguard_peers');
        header('Content-Type: application/json');

        $nome = trim($_POST['nome'] ?? '');
        $resultado = $this->service->criarPeer($nome);

        if ($resultado['success']) {
            AuditService::registrar('VPN WireGuard', 'Criar peer', "Peer \"{$nome}\" criado.");
        }

        // nunca loga a config/QR (contem chave privada) em lugar nenhum
        echo json_encode($resultado);
    }

    public function marcarEntregue(): void
    {
        AuthMiddleware::checkModulo('vpn_wireguard_peers');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $this->service->marcarConfigEntregue($id);

        echo json_encode(['success' => true]);
    }

    public function revogarPeer(): void
    {
        AuthMiddleware::checkModulo('vpn_wireguard_peers');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->revogarPeer($id);

        AuditService::registrar('VPN WireGuard', 'Revogar peer', "Peer #{$id} revogado.");

        echo json_encode($resultado);
    }

    public function trafego(): void
    {
        AuthMiddleware::checkModulo('vpn_wireguard_trafego');

        $status = $this->service->status();
        $peerId = (int)($_GET['peer_id'] ?? ($status['peers'][0]['id'] ?? 0));

        $this->view('vpn/wireguard_trafego', [
            'status' => $status,
            'peerSelecionado' => $peerId,
            'historico' => $peerId ? $this->service->historicoTrafego($peerId) : [],
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

    private function notificarEVoltar(array $resultado): void
    {
        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/vpn/wireguard/servidor'));
        exit;
    }
}
