<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\VpnOpenvpnSaidaService;
use App\Services\VpnOpenvpnService;
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
        $trafegoHojeWg = $wg->trafegoAgregadoHoje();

        $ovpn = new VpnOpenvpnService();
        $configOvpn = $ovpn->config();
        $clientesOvpn = $configOvpn ? $ovpn->status()['clientes'] ?? [] : [];

        $clientesAtivos = array_filter($clientesOvpn, fn($c) => (int)$c['ativo'] === 1);
        $clientesOnline = array_filter($clientesAtivos, fn($c) => $c['online']);
        $trafegoHojeOvpn = $ovpn->trafegoAgregadoHoje();

        $conexoesSaida = (new VpnOpenvpnSaidaService())->listar();

        $this->view('vpn/dashboard', [
            'wireguard' => [
                'instalado' => (bool)($configWg['instalado'] ?? false),
                'exposto' => (bool)($configWg['exposto_internet'] ?? false),
                'peers_total' => count($peersAtivos),
                'peers_online' => count($peersOnline),
                'rx_hoje' => (int)($trafegoHojeWg['rx_total'] ?? 0),
                'tx_hoje' => (int)($trafegoHojeWg['tx_total'] ?? 0),
            ],
            'openvpn' => [
                'instalado' => (bool)($configOvpn['instalado'] ?? false),
                'exposto' => (bool)($configOvpn['exposto_internet'] ?? false),
                'clientes_total' => count($clientesAtivos),
                'clientes_online' => count($clientesOnline),
                'rx_hoje' => (int)($trafegoHojeOvpn['rx_total'] ?? 0),
                'tx_hoje' => (int)($trafegoHojeOvpn['tx_total'] ?? 0),
                'conexoes_saida_ativas' => count(array_filter($conexoesSaida, fn($c) => $c['ativo'])),
                'conexoes_saida_total' => count($conexoesSaida),
            ],
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
