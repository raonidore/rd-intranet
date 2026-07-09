<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\DbConexaoService;
use App\Services\NotificationService;

class DbConexaoController extends Controller
{
    private DbConexaoService $service;

    public function __construct()
    {
        $this->service = new DbConexaoService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');

        $this->view('database/conexoes', [
            'conexoes' => $this->service->listar(),
        ]);
    }

    public function novoForm(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');

        $this->view('database/conexao_form', ['conexao' => null]);
    }

    public function novo(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');

        $nome = trim($_POST['nome'] ?? '');

        if ($this->service->criar($_POST)) {
            AuditService::registrar('Banco de Dados', 'Criar conexão', "Conexão {$nome} criada.");
            NotificationService::success('Conexão criada com sucesso.');
        }

        header('Location: ' . url('/banco-dados/conexoes'));
        exit;
    }

    public function editarForm(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');

        $id = (int)($_GET['id'] ?? 0);
        $conexao = $this->service->buscar($id);

        if (!$conexao) {
            header('Location: ' . url('/banco-dados/conexoes'));
            exit;
        }

        $this->view('database/conexao_form', ['conexao' => $conexao]);
    }

    public function editar(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');

        $id = (int)($_POST['id'] ?? 0);

        if ($this->service->atualizar($id, $_POST)) {
            AuditService::registrar('Banco de Dados', 'Editar conexão', "Conexão #{$id} atualizada.");
            NotificationService::success('Conexão atualizada com sucesso.');
        }

        header('Location: ' . url('/banco-dados/conexoes'));
        exit;
    }

    public function senhaForm(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');

        $id = (int)($_GET['id'] ?? 0);
        $conexao = $this->service->buscar($id);

        if (!$conexao) {
            header('Location: ' . url('/banco-dados/conexoes'));
            exit;
        }

        $this->view('database/conexao_senha', ['conexao' => $conexao]);
    }

    public function senha(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');

        $id = (int)($_POST['id'] ?? 0);

        if ($this->service->redefinirSenha($id, $_POST['senha'] ?? '')) {
            AuditService::registrar('Banco de Dados', 'Redefinir credencial', "Credencial da conexão #{$id} redefinida.");
            NotificationService::success('Credencial atualizada com sucesso.');
        }

        header('Location: ' . url('/banco-dados/conexoes'));
        exit;
    }

    public function ativar(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');

        $id = (int)($_GET['id'] ?? 0);

        $this->service->ativar($id);

        AuditService::registrar('Banco de Dados', 'Ativar conexão', "Conexão #{$id} ativada.");
        NotificationService::success('Conexão ativada com sucesso.');

        header('Location: ' . url('/banco-dados/conexoes'));
        exit;
    }

    public function desativar(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');

        $id = (int)($_GET['id'] ?? 0);

        $this->service->desativar($id);

        AuditService::registrar('Banco de Dados', 'Desativar conexão', "Conexão #{$id} desativada.");
        NotificationService::success('Conexão desativada com sucesso.');

        header('Location: ' . url('/banco-dados/conexoes'));
        exit;
    }

    public function excluirForm(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');

        $id = (int)($_GET['id'] ?? 0);
        $conexao = $this->service->buscar($id);

        if (!$conexao) {
            header('Location: ' . url('/banco-dados/conexoes'));
            exit;
        }

        $this->view('database/conexao_excluir', ['conexao' => $conexao]);
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');

        $id = (int)($_POST['id'] ?? 0);

        if ($this->service->excluir($id)) {
            AuditService::registrar('Banco de Dados', 'Excluir conexão', "Conexão #{$id} excluída.");
            NotificationService::success('Conexão excluída com sucesso.');
        }

        header('Location: ' . url('/banco-dados/conexoes'));
        exit;
    }

    public function testar(): void
    {
        AuthMiddleware::checkModulo('bd_mysql');

        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->testar($id);

        AuditService::registrar('Banco de Dados', 'Testar conexão', "Teste da conexão #{$id}: " . ($resultado['success'] ? 'ok' : 'falhou'));

        echo json_encode($resultado);
    }
}
