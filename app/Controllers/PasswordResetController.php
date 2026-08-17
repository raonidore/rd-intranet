<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\AtivoService;
use App\Services\AuditService;
use App\Services\EmailService;
use App\Services\NotificationService;
use App\Services\PasswordPolicyService;
use App\Services\PasswordResetService;

class PasswordResetController extends Controller
{
    private PasswordResetService $service;

    public function __construct()
    {
        $this->service = new PasswordResetService();
    }

    public function esqueciForm(): void
    {
        if (!(new EmailService())->configurado()) {
            header('Location: ' . url('/login'));
            exit;
        }

        $this->view('auth/esqueci_senha', [
            'logoSistemaConfigurada' => (new AtivoService())->logoSistemaConfigurada(),
        ]);
    }

    public function esqueciEnviar(): void
    {
        if ((new EmailService())->configurado()) {
            $urlBase = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? 'off') !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
            $this->service->solicitar($_POST['login_ou_email'] ?? '', $urlBase);

            AuditService::registrar('Autenticação', 'Esqueci minha senha', 'Pedido de redefinição pra: ' . trim($_POST['login_ou_email'] ?? ''));
        }

        NotificationService::success('Se os dados informados corresponderem a uma conta com e-mail cadastrado, você vai receber um link de redefinição em instantes.');

        header('Location: ' . url('/login'));
        exit;
    }

    public function redefinirForm(): void
    {
        $token = $_GET['token'] ?? '';
        $registro = $this->service->validarToken($token);

        $this->view('auth/redefinir_senha', [
            'token' => $token,
            'valido' => $registro !== null,
            'politica' => PasswordPolicyService::politicaParaJs(),
            'dadosObvios' => $registro ? ['nome' => $registro['nome'], 'login' => $registro['login'], 'email' => $registro['email']] : [],
            'logoSistemaConfigurada' => (new AtivoService())->logoSistemaConfigurada(),
        ]);
    }

    public function redefinir(): void
    {
        $token = $_POST['token'] ?? '';
        $resultado = $this->service->redefinir($token, $_POST['senha'] ?? '', $_POST['confirmacao'] ?? '');

        if ($resultado['success']) {
            AuditService::registrar('Autenticação', 'Redefinir senha', 'Senha redefinida via link de e-mail.');
            NotificationService::success($resultado['message']);
            header('Location: ' . url('/login'));
            exit;
        }

        NotificationService::error($resultado['message']);
        header('Location: ' . url('/login/redefinir?token=' . urlencode($token)));
        exit;
    }
}
