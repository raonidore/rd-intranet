<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\KbService;
use App\Services\NotificationService;

/**
 * Hub de integrações do Sistema -- admin-only (dados sensíveis: senha de
 * SMTP, API key da base central etc., que usuários com perfil abaixo de
 * admin não precisam ver mesmo que tenham acesso ao módulo relacionado).
 */
class IntegracoesController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::checkAdmin();

        $this->view('administracao/integracoes', []);
    }

    public function baseConhecimentoForm(): void
    {
        AuthMiddleware::checkAdmin();

        $service = new KbService();

        $this->view('administracao/integracoes_base_conhecimento', [
            'urlCentral' => $service->urlCentral(),
            'apiKeyCentral' => $service->apiKeyCentral(),
        ]);
    }

    public function baseConhecimentoSalvar(): void
    {
        AuthMiddleware::checkAdmin();

        $resultado = (new KbService())->salvarConfigCentral($_POST['url'] ?? '', $_POST['api_key'] ?? '');

        if ($resultado['success']) {
            AuditService::registrar('Integrações', 'Base de Conhecimento', 'Configuração da base central atualizada.');
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/administracao/integracoes/base-conhecimento'));
        exit;
    }
}
