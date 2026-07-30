<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\EmailService;
use App\Services\NotificationService;

class EmailConfigController extends Controller
{
    private EmailService $service;

    public function __construct()
    {
        $this->service = new EmailService();
    }

    public function index(): void
    {
        AuthMiddleware::checkAdmin();

        $this->view('administracao/email', [
            'configurado' => $this->service->configurado(),
            'host' => $this->service->host(),
            'porta' => $this->service->porta(),
            'usuario' => $this->service->usuario(),
            'criptografia' => $this->service->criptografia(),
            'remetenteNome' => $this->service->remetenteNome(),
            'remetenteEmail' => $this->service->remetenteEmail(),
        ]);
    }

    public function salvar(): void
    {
        AuthMiddleware::checkAdmin();

        $resultado = $this->service->salvarConfiguracao($_POST);

        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        AuditService::registrar('Sistema', 'Configurar e-mail SMTP', $resultado['message']);

        header('Location: ' . url('/administracao/email'));
        exit;
    }

    public function testar(): void
    {
        AuthMiddleware::checkAdmin();
        header('Content-Type: application/json');

        $para = trim($_POST['para'] ?? '');
        $resultado = $this->service->enviarTeste($para);

        AuditService::registrar('Sistema', 'Testar e-mail SMTP', $resultado['message']);

        echo json_encode($resultado);
    }
}
