<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\UserService;

class PerfilController extends Controller
{
    private UserService $service;

    public function __construct()
    {
        $this->service = new UserService();
    }

    public function index(): void
    {
        AuthMiddleware::check();

        $this->view('perfil/index', [
            'usuario' => $this->service->buscar((int)$_SESSION['usuario']['id']),
        ]);
    }

    public function atualizar(): void
    {
        AuthMiddleware::check();

        $id = (int)$_SESSION['usuario']['id'];

        if ($this->service->atualizarNomeProprio($id, $_POST['nome'] ?? '')) {
            AuditService::registrar('Perfil', 'Editar', "Usuário #{$id} atualizou o próprio nome.");
            NotificationService::success('Nome atualizado com sucesso.');
        }

        header('Location: ' . url('/perfil'));
        exit;
    }

    public function senha(): void
    {
        AuthMiddleware::check();

        $id = (int)$_SESSION['usuario']['id'];

        $ok = $this->service->alterarSenhaProprio(
            $id,
            $_POST['senha_atual'] ?? '',
            $_POST['senha'] ?? '',
            $_POST['confirmacao'] ?? ''
        );

        if ($ok) {
            AuditService::registrar('Perfil', 'Alterar senha', "Usuário #{$id} alterou a própria senha.");
            NotificationService::success('Senha alterada com sucesso.');
        }

        header('Location: ' . url('/perfil'));
        exit;
    }
}
