<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\LinuxService;
use App\Services\NotificationService;
use App\Services\WhatsAppApiOficialService;
use App\Services\WhatsAppBridgeService;
use App\Services\WhatsAppConfigService;
use App\Services\WhatsAppTwilioService;

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

        $apiOficial = new WhatsAppApiOficialService();
        $twilio = new WhatsAppTwilioService();

        $esquema = ($_SERVER['HTTPS'] ?? '') === 'on' ? 'https' : 'http';
        $baseUrl = $esquema . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

        $this->view('administracao/integracoes_whatsapp', [
            'tipoAtual' => $this->config->tipoIntegracao(),
            'bridgeInstalado' => $this->config->bridgeInstalado(),
            'webhookMetaUrl' => $baseUrl . url('/api/whatsapp/webhook/meta'),
            'webhookTwilioUrl' => $baseUrl . url('/api/whatsapp/webhook/twilio'),
            'metaPhoneNumberId' => $apiOficial->phoneNumberId(),
            'metaVerifyToken' => $apiOficial->verifyToken(),
            'metaConfigurado' => $apiOficial->configurado(),
            'twilioAccountSid' => $twilio->accountSid(),
            'twilioNumero' => $twilio->numero(),
            'twilioConfigurado' => $twilio->configurado(),
        ]);
    }

    public function salvarMeta(): void
    {
        AuthMiddleware::checkAdmin();

        $resultado = (new WhatsAppApiOficialService())->salvarConfig(
            $_POST['phone_number_id'] ?? '',
            $_POST['access_token'] ?? '',
            $_POST['verify_token'] ?? '',
            $_POST['app_secret'] ?? ''
        );

        AuditService::registrar('Integrações', 'WhatsApp API Oficial', $resultado['message']);

        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/administracao/integracoes/whatsapp'));
        exit;
    }

    public function salvarTwilio(): void
    {
        AuthMiddleware::checkAdmin();

        $resultado = (new WhatsAppTwilioService())->salvarConfig(
            $_POST['account_sid'] ?? '',
            $_POST['auth_token'] ?? '',
            $_POST['numero'] ?? ''
        );

        AuditService::registrar('Integrações', 'WhatsApp Twilio', $resultado['message']);

        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/administracao/integracoes/whatsapp'));
        exit;
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
