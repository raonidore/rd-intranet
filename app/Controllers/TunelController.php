<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\CloudflareTunnelService;
use App\Services\NotificationService;
use App\Services\TailscaleService;

/** Infraestrutura > Túneis -- Tailscale (VPN mesh privada) e Cloudflare Tunnel (exposição pública protegida), pra acessar/expor este servidor sem depender da VPN do cliente. */
class TunelController extends Controller
{
    private TailscaleService $tailscale;
    private CloudflareTunnelService $cloudflare;

    public function __construct()
    {
        $this->tailscale = new TailscaleService();
        $this->cloudflare = new CloudflareTunnelService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('infra_tuneis');

        $this->view('infrastructure/tuneis', [
            'tailscaleStatus' => $this->tailscale->status(),
            'tailscaleConfigurado' => $this->tailscale->configurado(),
            'tailscaleTailnet' => $this->tailscale->tailnetAtual(),
            'tailscaleHostname' => $this->tailscale->hostnameDesejadoAtual(),
            'cloudflareStatus' => $this->cloudflare->status(),
            'cloudflareConfigurado' => $this->cloudflare->configurado(),
            'cloudflareAccountId' => $this->cloudflare->accountIdAtual(),
            'cloudflareZoneId' => $this->cloudflare->zoneIdAtual(),
            'cloudflareHostname' => $this->cloudflare->hostnameAtual(),
        ]);
    }

    public function tailscaleConfigurarSalvar(): void
    {
        AuthMiddleware::checkModulo('infra_tuneis');

        $this->tailscale->salvarCredenciais(
            trim($_POST['api_token'] ?? ''),
            trim($_POST['tailnet'] ?? ''),
            trim($_POST['hostname'] ?? '')
        );

        $this->voltar();
    }

    public function tailscaleConectar(): void
    {
        AuthMiddleware::checkModulo('infra_tuneis');
        set_time_limit(120);

        $this->notificarEVoltar($this->tailscale->conectar());
    }

    public function tailscaleDesconectar(): void
    {
        AuthMiddleware::checkModulo('infra_tuneis');

        $this->notificarEVoltar($this->tailscale->desconectar((bool)($_POST['logout'] ?? false)));
    }

    public function cloudflareConfigurarSalvar(): void
    {
        AuthMiddleware::checkModulo('infra_tuneis');

        $this->cloudflare->salvarCredenciais(
            trim($_POST['api_token'] ?? ''),
            trim($_POST['account_id'] ?? ''),
            trim($_POST['zone_id'] ?? ''),
            trim($_POST['hostname'] ?? '')
        );

        $this->voltar();
    }

    public function cloudflareCriar(): void
    {
        AuthMiddleware::checkModulo('infra_tuneis');
        set_time_limit(120);

        $this->notificarEVoltar($this->cloudflare->criar());
    }

    public function cloudflareRemover(): void
    {
        AuthMiddleware::checkModulo('infra_tuneis');
        set_time_limit(60);

        $this->notificarEVoltar($this->cloudflare->remover());
    }

    private function notificarEVoltar(array $resultado): void
    {
        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        $this->voltar();
    }

    private function voltar(): void
    {
        header('Location: ' . url('/infraestrutura/tuneis'));
        exit;
    }
}
