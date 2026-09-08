<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\KbService;
use App\Services\NotificationService;
use App\Services\OmadaService;
use App\Services\UnifiService;

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

    public function unifiForm(): void
    {
        AuthMiddleware::checkAdmin();

        $service = new UnifiService();

        $this->view('administracao/integracoes_unifi', [
            'urlAtual' => $service->urlAtual(),
            'configurado' => $service->configurado(),
            'siteId' => $service->siteIdAtual(),
        ]);
    }

    public function unifiSalvar(): void
    {
        AuthMiddleware::checkAdmin();

        (new UnifiService())->salvarConfiguracao($_POST['url'] ?? '', $_POST['api_key'] ?? '');

        header('Location: ' . url('/administracao/integracoes/unifi'));
        exit;
    }

    public function unifiRemover(): void
    {
        AuthMiddleware::checkAdmin();

        (new UnifiService())->removerConfiguracao();

        header('Location: ' . url('/administracao/integracoes/unifi'));
        exit;
    }

    public function unifiTestar(): void
    {
        AuthMiddleware::checkAdmin();
        header('Content-Type: application/json');

        set_time_limit(30);

        echo json_encode((new UnifiService())->testarConexao());
    }

    public function omadaForm(): void
    {
        AuthMiddleware::checkAdmin();

        $service = new OmadaService();

        $this->view('administracao/integracoes_omada', [
            'urlAtual' => $service->urlAtual(),
            'omadacIdAtual' => $service->omadacIdAtual(),
            'clientIdAtual' => $service->clientIdAtual(),
            'configurado' => $service->configurado(),
            'siteId' => $service->siteIdAtual(),
        ]);
    }

    public function omadaSalvar(): void
    {
        AuthMiddleware::checkAdmin();

        (new OmadaService())->salvarConfiguracao(
            $_POST['url'] ?? '',
            $_POST['omadac_id'] ?? '',
            $_POST['client_id'] ?? '',
            $_POST['client_secret'] ?? ''
        );

        header('Location: ' . url('/administracao/integracoes/omada'));
        exit;
    }

    public function omadaRemover(): void
    {
        AuthMiddleware::checkAdmin();

        (new OmadaService())->removerConfiguracao();

        header('Location: ' . url('/administracao/integracoes/omada'));
        exit;
    }

    public function omadaTestar(): void
    {
        AuthMiddleware::checkAdmin();
        header('Content-Type: application/json');

        set_time_limit(30);

        echo json_encode((new OmadaService())->testarConexao());
    }
}
