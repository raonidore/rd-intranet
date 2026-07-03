<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\AuditService;

class AuthController extends Controller
{
    public function loginForm(): void
    {
        $this->view('auth/login');
    }

    public function login(): void
    {
        $pdo = Database::connection();

        $login = trim($_POST['login'] ?? '');
        $senha = $_POST['senha'] ?? '';

        $stmt = $pdo->prepare("
            SELECT *
            FROM usuarios
            WHERE login = ?
              AND ativo = 1
            LIMIT 1
        ");

        $stmt->execute([$login]);

        $usuario = $stmt->fetch();

        if ($usuario && password_verify($senha, $usuario['senha_hash'])) {

            $_SESSION['usuario'] = [
                'id'      => $usuario['id'],
                'nome'    => $usuario['nome'],
                'login'   => $usuario['login'],
                'perfil'  => $usuario['perfil']
            ];

            AuditService::registrar(
                'Autenticação',
                'Login',
                'Usuário '.$usuario['login'].' realizou login.'
            );

            header('Location: /rd.intranet/dashboard');
            exit;
        }

        AuditService::registrar(
            'Autenticação',
            'Falha de Login',
            'Tentativa de login utilizando o usuário: '.$login
        );

        $_SESSION['flash_msg'] = 'Usuário ou senha inválidos.';
        $_SESSION['flash_tipo'] = 'error';

        header('Location: /rd.intranet/login');
        exit;
    }

    public function logout(): void
    {
        if (isset($_SESSION['usuario'])) {

            AuditService::registrar(
                'Autenticação',
                'Logout',
                'Usuário '.$_SESSION['usuario']['login'].' encerrou a sessão.'
            );
        }

        session_destroy();

        header('Location: /rd.intranet/login');
        exit;
    }
}
