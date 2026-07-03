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
        AuthMiddleware::check();

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
        AuthMiddleware::check();

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
        AuthMiddleware::check();

        $id = (int)($_POST['id'] ?? 0);
        $senha = $_POST['senha'] ?? '';
        $confirmacao = $_POST['confirmacao'] ?? '';

        $this->service->alterarSenha($id, $senha, $confirmacao);

        header('Location: ' . url('/samba/usuarios'));
        exit;
    }

    public function desativar(): void
    {
        AuthMiddleware::check();

        $id = (int)($_GET['id'] ?? 0);

        $this->service->desativarUsuario($id);

        header('Location: ' . url('/samba/usuarios'));
        exit;
    }
}
