<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\ModuloCatalogo;
use App\Services\NotificationService;
use App\Services\UserService;

class UserController extends Controller
{
    private UserService $service;

    public function __construct()
    {
        $this->service = new UserService();
    }

    public function index(): void
    {
        AuthMiddleware::checkAdmin();

        $this->view('administracao/usuarios', [
            'usuarios' => $this->service->listar(),
        ]);
    }

    public function novoForm(): void
    {
        AuthMiddleware::checkAdmin();

        $this->view('administracao/usuario_form', [
            'usuario' => null,
            'modulosAgrupados' => ModuloCatalogo::agrupados(),
            'modulosSelecionados' => [],
        ]);
    }

    public function novo(): void
    {
        AuthMiddleware::checkAdmin();

        $login = trim($_POST['login'] ?? '');

        $ok = $this->service->criar([
            'nome' => $_POST['nome'] ?? '',
            'login' => $login,
            'senha' => $_POST['senha'] ?? '',
            'perfil' => $_POST['perfil'] ?? '',
            'modulos' => $_POST['modulos'] ?? [],
        ]);

        if ($ok) {
            AuditService::registrar('Usuários', 'Criar', "Usuário {$login} criado.");
            NotificationService::success('Usuário criado com sucesso.');
        }

        header('Location: ' . url('/administracao/usuarios'));
        exit;
    }

    public function editarForm(): void
    {
        AuthMiddleware::checkAdmin();

        $id = (int)($_GET['id'] ?? 0);
        $usuario = $this->service->buscar($id);

        if (!$usuario) {
            header('Location: ' . url('/administracao/usuarios'));
            exit;
        }

        $this->view('administracao/usuario_form', [
            'usuario' => $usuario,
            'modulosAgrupados' => ModuloCatalogo::agrupados(),
            'modulosSelecionados' => $this->service->modulosDoUsuario($id),
        ]);
    }

    public function editar(): void
    {
        AuthMiddleware::checkAdmin();

        $id = (int)($_POST['id'] ?? 0);

        $ok = $this->service->atualizar($id, [
            'nome' => $_POST['nome'] ?? '',
            'perfil' => $_POST['perfil'] ?? '',
            'modulos' => $_POST['modulos'] ?? [],
        ]);

        if ($ok) {
            AuditService::registrar('Usuários', 'Editar', "Usuário #{$id} atualizado.");
            NotificationService::success('Usuário atualizado com sucesso.');
        }

        header('Location: ' . url('/administracao/usuarios'));
        exit;
    }

    public function senhaForm(): void
    {
        AuthMiddleware::checkAdmin();

        $id = (int)($_GET['id'] ?? 0);
        $usuario = $this->service->buscar($id);

        if (!$usuario) {
            header('Location: ' . url('/administracao/usuarios'));
            exit;
        }

        $this->view('administracao/usuario_senha', [
            'usuario' => $usuario,
        ]);
    }

    public function senha(): void
    {
        AuthMiddleware::checkAdmin();

        $id = (int)($_POST['id'] ?? 0);

        $ok = $this->service->redefinirSenha(
            $id,
            $_POST['senha'] ?? '',
            $_POST['confirmacao'] ?? ''
        );

        if ($ok) {
            AuditService::registrar('Usuários', 'Redefinir senha', "Senha do usuário #{$id} redefinida.");
            NotificationService::success('Senha redefinida com sucesso.');
        }

        header('Location: ' . url('/administracao/usuarios'));
        exit;
    }

    public function ativar(): void
    {
        AuthMiddleware::checkAdmin();

        $id = (int)($_GET['id'] ?? 0);

        $this->service->ativar($id);

        AuditService::registrar('Usuários', 'Ativar', "Usuário #{$id} ativado.");
        NotificationService::success('Usuário ativado com sucesso.');

        header('Location: ' . url('/administracao/usuarios'));
        exit;
    }

    public function desativar(): void
    {
        AuthMiddleware::checkAdmin();

        $id = (int)($_GET['id'] ?? 0);

        if ($this->service->desativar($id)) {
            AuditService::registrar('Usuários', 'Desativar', "Usuário #{$id} desativado.");
            NotificationService::success('Usuário desativado com sucesso.');
        }

        header('Location: ' . url('/administracao/usuarios'));
        exit;
    }

    public function excluirForm(): void
    {
        AuthMiddleware::checkAdmin();

        $id = (int)($_GET['id'] ?? 0);
        $usuario = $this->service->buscar($id);

        if (!$usuario) {
            header('Location: ' . url('/administracao/usuarios'));
            exit;
        }

        $this->view('administracao/usuario_excluir', [
            'usuario' => $usuario,
        ]);
    }

    public function excluir(): void
    {
        AuthMiddleware::checkAdmin();

        $id = (int)($_POST['id'] ?? 0);

        if ($this->service->excluir($id)) {
            AuditService::registrar('Usuários', 'Excluir', "Usuário #{$id} excluído.");
            NotificationService::success('Usuário excluído com sucesso.');
        }

        header('Location: ' . url('/administracao/usuarios'));
        exit;
    }
}
