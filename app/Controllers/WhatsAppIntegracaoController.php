<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\LinuxService;
use App\Services\NotificationService;
use App\Services\WhatsAppBridgeService;
use App\Services\WhatsAppConfigService;

class WhatsAppIntegracaoController extends Controller
{
    private WhatsAppConfigService $config;

    public function __construct()
    {
        $this->config = new WhatsAppConfigService();
    }

    public function form(): void
    {
        AuthMiddleware::checkAdmin();

        $this->view('administracao/integracoes_whatsapp', [
            'tipoAtual' => $this->config->tipoIntegracao(),
            'bridgeInstalado' => $this->config->bridgeInstalado(),
        ]);
    }

    public function salvarTipo(): void
    {
        AuthMiddleware::checkAdmin();

        $tipo = $_POST['tipo'] ?? '';
        $resultado = $this->config->salvarTipoIntegracao($tipo);

        if ($resultado['success']) {
            AuditService::registrar('Integrações', 'WhatsApp', "Tipo de integração alterado para \"{$tipo}\".");
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/administracao/integracoes/whatsapp'));
        exit;
    }

    /**
     * Dispara a instalação do bridge em segundo plano (npm install pode
     * levar dezenas de segundos) -- a tela acompanha via polling em
     * status()/qrcode(), não espera essa requisição terminar.
     */
    public function instalar(): void
    {
        AuthMiddleware::checkAdmin();
        header('Content-Type: application/json');

        // URL absoluta de verdade (não só o path de url()) -- o bridge é
        // um processo Node à parte, sem noção de "host atual" nenhuma,
        // então precisa do endereço completo pra conseguir chamar o
        // webhook de volta. Usa o mesmo host/porta/esquema que o próprio
        // admin está usando agora pra acessar esta tela.
        $esquema = ($_SERVER['HTTPS'] ?? '') === 'on' ? 'https' : 'http';
        $webhookUrl = $esquema . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url('/api/whatsapp/webhook');

        $repoDir = realpath(__DIR__ . '/../..');

        (new LinuxService())->executarScriptEmSegundoPlano(
            '/opt/rdtecnologia/scripts/whatsapp_bridge_instalar_web.sh',
            [
                $repoDir,
                (string)$this->config->bridgePorta(),
                $this->config->bridgeApiKey(),
                $webhookUrl,
            ]
        );

        $this->config->marcarBridgeInstalado();

        AuditService::registrar('Integrações', 'WhatsApp', 'Instalação do bridge (QR Code) disparada.');

        echo json_encode(['success' => true, 'message' => 'Instalação iniciada -- pode levar um minuto.']);
    }

    public function status(): void
    {
        AuthMiddleware::checkAdmin();
        header('Content-Type: application/json');

        echo json_encode((new WhatsAppBridgeService())->status());
    }

    public function qrcode(): void
    {
        AuthMiddleware::checkAdmin();
        header('Content-Type: application/json');

        echo json_encode((new WhatsAppBridgeService())->qrcode());
    }

    public function desconectar(): void
    {
        AuthMiddleware::checkAdmin();

        $resultado = (new WhatsAppBridgeService())->desconectar();

        AuditService::registrar('Integrações', 'WhatsApp', 'Desconexão do WhatsApp solicitada.');

        if ($resultado['success']) {
            NotificationService::success($resultado['message'] ?? 'Desconectado.');
        } else {
            NotificationService::error($resultado['message'] ?? 'Falha ao desconectar.');
        }

        header('Location: ' . url('/administracao/integracoes/whatsapp'));
        exit;
    }
}
