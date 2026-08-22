<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\WhatsAppConfigService;
use App\Services\WhatsAppNpsService;

class WhatsAppNpsController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::checkModulo('whatsapp_nps');

        $service = new WhatsAppNpsService();
        $config = new WhatsAppConfigService();

        $this->view('whatsapp/nps', [
            'resumo' => $service->resumo(),
            'respostas' => $service->listar(),
            'npsPergunta' => $config->npsPergunta(),
            'npsAgradecimento' => $config->npsAgradecimento(),
        ]);
    }

    public function salvarMensagens(): void
    {
        AuthMiddleware::checkModulo('whatsapp_nps');

        $resultado = (new WhatsAppConfigService())->salvarNps(
            $_POST['pergunta'] ?? '',
            $_POST['agradecimento'] ?? ''
        );

        AuditService::registrar('WhatsApp', 'NPS - mensagens', $resultado['message']);

        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/whatsapp/nps'));
        exit;
    }
}
