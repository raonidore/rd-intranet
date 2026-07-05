<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\SambaService;

class SambaController extends Controller
{
    private SambaService $service;

    public function __construct()
    {
        $this->service = new SambaService();
    }

    public function usuarios(): void
    {
        AuthMiddleware::checkModulo('samba_usuarios');

        $usuarios = $this->service->listarUsuarios();
        $dashboard = $this->service->dashboard();

        $this->view('samba/usuarios', [
            'usuarios' => $usuarios,
            'total' => $dashboard['total'],
            'ativos' => $dashboard['ativos'],
            'sshTotal' => $dashboard['ssh'],
        ]);
    }

    public function alterarSenhaForm(): void
    {
        AuthMiddleware::checkModulo('samba_usuarios');

        $id = (int)($_GET['id'] ?? 0);
        $usuario = $this->service->buscarUsuario($id);

        if (!$usuario) {
            header('Location: ' . url('/samba/usuarios'));
            exit;
        }

        $this->view('samba/alterar_senha', [
            'usuarioSamba' => $usuario
        ]);
    }

    public function alterarSenha(): void
    {
        AuthMiddleware::checkModulo('samba_usuarios');

        $this->service->alterarSenha(
            (int)($_POST['id'] ?? 0),
            $_POST['senha'] ?? '',
            $_POST['confirmacao'] ?? ''
        );

        header('Location: ' . url('/samba/usuarios'));
        exit;
    }

    public function editarForm(): void
    {
        AuthMiddleware::checkModulo('samba_usuarios');

        $id = (int)($_GET['id'] ?? 0);
        $usuario = $this->service->buscarUsuario($id);

        if (!$usuario) {
            header('Location: ' . url('/samba/usuarios'));
            exit;
        }

        $this->view('samba/editar', [
            'usuarioSamba' => $usuario,
            'departamentos' => $this->service->departamentos()
        ]);
    }

    public function editar(): void
    {
        AuthMiddleware::checkModulo('samba_usuarios');

        $this->service->editarUsuario(
            (int)($_POST['id'] ?? 0),
            trim($_POST['nome'] ?? ''),
            $_POST['departamento'] ?? '',
            ($_POST['ssh'] ?? '0') === '1'
        );

        header('Location: ' . url('/samba/usuarios'));
        exit;
    }

    public function desativar(): void
    {
        AuthMiddleware::checkModulo('samba_usuarios');

        $this->service->desativarUsuario((int)($_GET['id'] ?? 0));

        header('Location: ' . url('/samba/usuarios'));
        exit;
    }

    public function ativar(): void
    {
        AuthMiddleware::checkModulo('samba_usuarios');

        $this->service->ativarUsuario((int)($_GET['id'] ?? 0));

        header('Location: ' . url('/samba/usuarios'));
        exit;
    }

    public function excluirForm(): void
    {
        AuthMiddleware::checkModulo('samba_usuarios');

        $id = (int)($_GET['id'] ?? 0);
        $usuario = $this->service->buscarUsuario($id);

        if (!$usuario) {
            header('Location: ' . url('/samba/usuarios'));
            exit;
        }

        $this->view('samba/excluir', [
            'usuarioSamba' => $usuario
        ]);
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('samba_usuarios');

        $this->service->excluirUsuario((int)($_POST['id'] ?? 0));

        header('Location: ' . url('/samba/usuarios'));
        exit;
    }
}
