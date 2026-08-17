<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\PasswordPolicyService;

class PoliticaSenhaController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::checkAdmin();

        $this->view('administracao/politica_senha', [
            'politica' => PasswordPolicyService::politica(),
        ]);
    }

    public function salvar(): void
    {
        AuthMiddleware::checkAdmin();

        PasswordPolicyService::salvar($_POST);

        AuditService::registrar('Sistema', 'Configurar política de senha', 'Política de senha atualizada.');
        NotificationService::success('Política de senha salva com sucesso.');

        header('Location: ' . url('/administracao/usuarios/politica-senha'));
        exit;
    }
}
