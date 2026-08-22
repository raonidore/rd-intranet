<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\WhatsAppConfigService;

class WhatsAppConfiguracaoController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::checkModulo('whatsapp_configuracoes');

        $config = new WhatsAppConfigService();

        $this->view('whatsapp/configuracoes', [
            'anexosAtivos' => $config->anexosAtivos(),
        ]);
    }

    public function salvar(): void
    {
        AuthMiddleware::checkModulo('whatsapp_configuracoes');

        $resultado = (new WhatsAppConfigService())->salvarAnexos(isset($_POST['anexos_ativos']));

        AuditService::registrar('WhatsApp', 'Configurações', $resultado['message']);

        NotificationService::success($resultado['message']);
        header('Location: ' . url('/whatsapp/configuracoes'));
        exit;
    }
}
