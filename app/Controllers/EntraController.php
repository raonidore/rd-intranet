<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\EntraService;

class EntraController extends Controller
{
    private EntraService $service;

    public function __construct()
    {
        $this->service = new EntraService();
    }

    public function dashboard(): void
    {
        AuthMiddleware::checkModulo('entra_dashboard');

        $configurado = $this->service->configurado();
        $usuarios = [];
        $skus = [];

        if ($configurado) {
            $usuarios = $this->service->listarUsuarios();
            $skus = $this->service->listarSkus();
        }

        $this->view('entra/dashboard', [
            'configurado' => $configurado,
            'totalUsuarios' => count($usuarios),
            'totalAtivos' => count(array_filter($usuarios, fn($u) => $u['accountEnabled'] ?? false)),
            'skus' => $skus,
        ]);
    }

    public function configuracaoForm(): void
    {
        AuthMiddleware::checkModulo('entra_configuracao');

        $this->view('entra/configuracao', [
            'configurado' => $this->service->configurado(),
            'tenantIdAtual' => $this->service->tenantIdAtual(),
            'clientIdAtual' => $this->service->clientIdAtual(),
        ]);
    }

    public function configuracaoSalvar(): void
    {
        AuthMiddleware::checkModulo('entra_configuracao');

        $this->service->salvarConfiguracao(
            $_POST['tenant_id'] ?? '',
            $_POST['client_id'] ?? '',
            $_POST['client_secret'] ?? ''
        );

        header('Location: ' . url('/entra/configuracao'));
        exit;
    }

    public function configuracaoRemover(): void
    {
        AuthMiddleware::checkModulo('entra_configuracao');

        $this->service->removerConfiguracao();

        header('Location: ' . url('/entra/configuracao'));
        exit;
    }

    public function usuarios(): void
    {
        AuthMiddleware::checkModulo('entra_usuarios');

        $configurado = $this->service->configurado();
        $usuarios = [];
        $skus = [];

        if ($configurado) {
            $usuarios = $this->service->listarUsuarios();
            $skus = $this->service->listarSkus();
        }

        $this->view('entra/usuarios', [
            'configurado' => $configurado,
            'usuarios' => $usuarios,
            'skus' => $skus,
        ]);
    }

    public function usuarioNovo(): void
    {
        AuthMiddleware::checkModulo('entra_usuarios');

        $this->service->criarUsuario(
            $_POST['nome'] ?? '',
            $_POST['upn'] ?? '',
            $_POST['senha'] ?? ''
        );

        header('Location: ' . url('/entra/usuarios'));
        exit;
    }

    public function resetarSenha(): void
    {
        AuthMiddleware::checkModulo('entra_usuarios');

        $this->service->resetarSenha(
            $_POST['user_id'] ?? '',
            $_POST['upn'] ?? '',
            $_POST['senha'] ?? ''
        );

        header('Location: ' . url('/entra/usuarios'));
        exit;
    }

    public function ativar(): void
    {
        AuthMiddleware::checkModulo('entra_usuarios');

        $this->service->ativarDesativar($_POST['user_id'] ?? '', $_POST['upn'] ?? '', true);

        header('Location: ' . url('/entra/usuarios'));
        exit;
    }

    public function desativar(): void
    {
        AuthMiddleware::checkModulo('entra_usuarios');

        $this->service->ativarDesativar($_POST['user_id'] ?? '', $_POST['upn'] ?? '', false);

        header('Location: ' . url('/entra/usuarios'));
        exit;
    }

    public function licencaAtribuir(): void
    {
        AuthMiddleware::checkModulo('entra_usuarios');

        $this->service->atribuirLicenca($_POST['user_id'] ?? '', $_POST['upn'] ?? '', $_POST['sku_id'] ?? '');

        header('Location: ' . url('/entra/usuarios'));
        exit;
    }

    public function licencaRemover(): void
    {
        AuthMiddleware::checkModulo('entra_usuarios');

        $this->service->removerLicenca($_POST['user_id'] ?? '', $_POST['upn'] ?? '', $_POST['sku_id'] ?? '');

        header('Location: ' . url('/entra/usuarios'));
        exit;
    }
}
