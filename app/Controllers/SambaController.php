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

    /**
     * Lista usuários Samba.
     */
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

    /**
     * Formulário para alterar senha.
     */
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

    /**
     * Executa alteração de senha.
     */
    public function alterarSenha(): void
    {
        AuthMiddleware::check();

        $id = (int)($_POST['id'] ?? 0);
        $senha = $_POST['senha'] ?? '';
        $confirmacao = $_POST['confirmacao'] ?? '';

        $this->service->alterarSenha(
            $id,
            $senha,
            $confirmacao
        );

        header('Location: ' . url('/samba/usuarios'));
        exit;
    }

    /**
     * Formulário de edição.
     */
    public function editarForm(): void
    {
        AuthMiddleware::check();

        $id = (int)($_GET['id'] ?? 0);

        $usuario = $this->service->buscarUsuario($id);

        if (!$usuario) {
            header('Location: ' . url('/samba/usuarios'));
            exit;
        }

        $departamentos = $this->service->departamentos();

        $this->view('samba/editar', [
            'usuarioSamba' => $usuario,
            'departamentos' => $departamentos
        ]);
    }

    /**
     * Salva edição.
     */
    public function editar(): void
    {
        AuthMiddleware::check();

        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $departamento = $_POST['departamento'] ?? '';
        $ssh = ($_POST['ssh'] ?? '0') === '1';

        $this->service->editarUsuario(
            $id,
            $nome,
            $departamento,
            $ssh
        );

        header('Location: ' . url('/samba/usuarios'));
        exit;
    }

    /**
     * Desativa usuário.
     */
    public function desativar(): void
    {
        AuthMiddleware::check();

        $id = (int)($_GET['id'] ?? 0);

        $this->service->desativarUsuario($id);

        header('Location: ' . url('/samba/usuarios'));
        exit;
    }

    /**
     * Tela de confirmação de exclusão.
     */
    public function excluirForm(): void
    {
        AuthMiddleware::check();

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

    /**
     * Executa exclusão.
     */
    public function excluir(): void
    {
        AuthMiddleware::check();

        $id = (int)($_POST['id'] ?? 0);

        $this->service->excluirUsuario($id);

        header('Location: ' . url('/samba/usuarios'));
        exit;
    }
}
