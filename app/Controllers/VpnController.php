<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\VpnWireguardService;

class VpnController extends Controller
{
    public function dashboard(): void
    {
        AuthMiddleware::checkModulo('vpn_dashboard');

        $wg = new VpnWireguardService();
        $configWg = $wg->config();
        $peers = $configWg ? $wg->status()['peers'] ?? [] : [];

        $peersAtivos = array_filter($peers, fn($p) => (int)$p['ativo'] === 1);
        $peersOnline = array_filter($peersAtivos, fn($p) => $p['online']);
        $trafegoHoje = $wg->trafegoAgregadoHoje();

        $this->view('vpn/dashboard', [
            'wireguard' => [
                'instalado' => (bool)($configWg['instalado'] ?? false),
                'exposto' => (bool)($configWg['exposto_internet'] ?? false),
                'peers_total' => count($peersAtivos),
                'peers_online' => count($peersOnline),
                'rx_hoje' => (int)($trafegoHoje['rx_total'] ?? 0),
                'tx_hoje' => (int)($trafegoHoje['tx_total'] ?? 0),
            ],
        ]);
    }

    public function openvpnEmBreve(): void
    {
        AuthMiddleware::checkModulo('vpn_dashboard');

        $this->view('vpn/em_breve', [
            'titulo' => 'OpenVPN',
            'descricao' => 'Cobre também o uso como "SSL VPN" (o protocolo OpenVPN já é baseado em TLS/SSL). Fase seguinte do módulo VPN — precisa de uma PKI própria (CA + certificado por cliente).',
            'icone' => 'bi-shield-lock',
        ]);
    }

    public function ikev2EmBreve(): void
    {
        AuthMiddleware::checkModulo('vpn_dashboard');

        $this->view('vpn/em_breve', [
            'titulo' => 'IKEv2 / IPsec',
            'descricao' => 'Suporte nativo em iOS/Android/Windows sem instalar app de terceiros. Fase seguinte do módulo VPN (via strongSwan) — depende da mesma PKI planejada para o OpenVPN.',
            'icone' => 'bi-globe-americas',
        ]);
    }
}
