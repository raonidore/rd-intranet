<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\AtivoService;
use App\Services\AuditService;
use App\Services\EmailService;

class AuthController extends Controller
{
    public function loginForm(): void
    {
        $ativoService = new AtivoService();

        $this->view('auth/login', [
            'logoSistemaConfigurada' => $ativoService->logoSistemaConfigurada(),
            'recuperacaoDisponivel' => (new EmailService())->configurado(),
        ]);
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

            // UNION com grupo_modulos -- quem está num grupo com módulo
            // concedido ganha o mesmo acesso de quem tem concessão
            // individual, resolvido aqui (na sessão) igual sempre foi
            // feito com usuario_modulos -- por isso um módulo novo
            // concedido ao grupo só passa a valer no próximo login.
            $stmtModulos = $pdo->prepare("
                SELECT modulo FROM usuario_modulos WHERE usuario_id = ?
                UNION
                SELECT gm.modulo FROM grupo_modulos gm
                JOIN grupo_usuarios gu ON gu.grupo_id = gm.grupo_id
                WHERE gu.usuario_id = ?
            ");
            $stmtModulos->execute([$usuario['id'], $usuario['id']]);

            $_SESSION['usuario'] = [
                'id'      => $usuario['id'],
                'nome'    => $usuario['nome'],
                'login'   => $usuario['login'],
                'perfil'  => $usuario['perfil'],
                'modulos' => $stmtModulos->fetchAll(\PDO::FETCH_COLUMN)
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
