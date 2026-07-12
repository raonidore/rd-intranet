<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\VpnIkev2SaidaService;
use App\Services\VpnIkev2Service;
use App\Services\VpnOpenvpnSaidaService;
use App\Services\VpnOpenvpnService;
use App\Services\VpnWireguardSaidaService;
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

        $conexoesSaidaOvpn = (new VpnOpenvpnSaidaService())->listar();
        $conexoesSaidaWg = (new VpnWireguardSaidaService())->listar();

        $ikev2 = new VpnIkev2Service();
        $configIkev2 = $ikev2->config();
        $clientesIkev2 = $configIkev2 ? $ikev2->status()['clientes'] ?? [] : [];

        $clientesIkev2Ativos = array_filter($clientesIkev2, fn($c) => (int)$c['ativo'] === 1);
        $clientesIkev2Online = array_filter($clientesIkev2Ativos, fn($c) => $c['online']);
        $trafegoHojeIkev2 = $ikev2->trafegoAgregadoHoje();

        $conexoesSaidaIkev2 = (new VpnIkev2SaidaService())->listar();

        $this->view('vpn/dashboard', [
            'wireguard' => [
                'instalado' => (bool)($configWg['instalado'] ?? false),
                'exposto' => (bool)($configWg['exposto_internet'] ?? false),
                'peers_total' => count($peersAtivos),
                'peers_online' => count($peersOnline),
                'rx_hoje' => (int)($trafegoHojeWg['rx_total'] ?? 0),
                'tx_hoje' => (int)($trafegoHojeWg['tx_total'] ?? 0),
                'conexoes_saida_ativas' => count(array_filter($conexoesSaidaWg, fn($c) => $c['ativo'])),
                'conexoes_saida_total' => count($conexoesSaidaWg),
            ],
            'openvpn' => [
                'instalado' => (bool)($configOvpn['instalado'] ?? false),
                'exposto' => (bool)($configOvpn['exposto_internet'] ?? false),
                'clientes_total' => count($clientesAtivos),
                'clientes_online' => count($clientesOnline),
                'rx_hoje' => (int)($trafegoHojeOvpn['rx_total'] ?? 0),
                'tx_hoje' => (int)($trafegoHojeOvpn['tx_total'] ?? 0),
                'conexoes_saida_ativas' => count(array_filter($conexoesSaidaOvpn, fn($c) => $c['ativo'])),
                'conexoes_saida_total' => count($conexoesSaidaOvpn),
            ],
            'ikev2' => [
                'instalado' => (bool)($configIkev2['instalado'] ?? false),
                'exposto' => (bool)($configIkev2['exposto_internet'] ?? false),
                'clientes_total' => count($clientesIkev2Ativos),
                'clientes_online' => count($clientesIkev2Online),
                'rx_hoje' => (int)($trafegoHojeIkev2['rx_total'] ?? 0),
                'tx_hoje' => (int)($trafegoHojeIkev2['tx_total'] ?? 0),
                'conexoes_saida_ativas' => count(array_filter($conexoesSaidaIkev2, fn($c) => $c['ativo'])),
                'conexoes_saida_total' => count($conexoesSaidaIkev2),
            ],
        ]);
    }
}
