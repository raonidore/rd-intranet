<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\GuacdGatewayService;
use App\Services\NotificationService;
use App\Services\SshConexaoService;

class SshConexaoController extends Controller
{
    private SshConexaoService $service;

    public function __construct()
    {
        $this->service = new SshConexaoService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('ssh_conexoes');

        $this->view('ssh/conexoes', [
            'conexoes' => $this->service->listar(),
        ]);
    }

    public function novoForm(): void
    {
        AuthMiddleware::checkModulo('ssh_conexoes');

        $this->view('ssh/conexao_form', ['conexao' => null]);
    }

    public function novo(): void
    {
        AuthMiddleware::checkModulo('ssh_conexoes');

        $nome = trim($_POST['nome'] ?? '');

        if ($this->service->criar($_POST)) {
            AuditService::registrar('SSH', 'Criar conexão', "Conexão {$nome} criada.");
            NotificationService::success('Conexão criada com sucesso.');
        }

        header('Location: ' . url('/ssh/conexoes'));
        exit;
    }

    public function editarForm(): void
    {
        AuthMiddleware::checkModulo('ssh_conexoes');

        $id = (int)($_GET['id'] ?? 0);
        $conexao = $this->service->buscar($id);

        if (!$conexao) {
            header('Location: ' . url('/ssh/conexoes'));
            exit;
        }

        $this->view('ssh/conexao_form', ['conexao' => $conexao]);
    }

    public function editar(): void
    {
        AuthMiddleware::checkModulo('ssh_conexoes');

        $id = (int)($_POST['id'] ?? 0);

        if ($this->service->atualizar($id, $_POST)) {
            AuditService::registrar('SSH', 'Editar conexão', "Conexão #{$id} atualizada.");
            NotificationService::success('Conexão atualizada com sucesso.');
        }

        header('Location: ' . url('/ssh/conexoes'));
        exit;
    }

    public function credencialForm(): void
    {
        AuthMiddleware::checkModulo('ssh_conexoes');

        $id = (int)($_GET['id'] ?? 0);
        $conexao = $this->service->buscar($id);

        if (!$conexao) {
            header('Location: ' . url('/ssh/conexoes'));
            exit;
        }

        $this->view('ssh/conexao_credencial', ['conexao' => $conexao]);
    }

    public function credencial(): void
    {
        AuthMiddleware::checkModulo('ssh_conexoes');

        $id = (int)($_POST['id'] ?? 0);

        if ($this->service->redefinirCredencial($id, $_POST)) {
            AuditService::registrar('SSH', 'Redefinir credencial', "Credencial da conexão #{$id} redefinida.");
            NotificationService::success('Credencial atualizada com sucesso.');
        }

        header('Location: ' . url('/ssh/conexoes'));
        exit;
    }

    public function ativar(): void
    {
        AuthMiddleware::checkModulo('ssh_conexoes');

        $id = (int)($_GET['id'] ?? 0);
        $this->service->ativar($id);

        AuditService::registrar('SSH', 'Ativar conexão', "Conexão #{$id} ativada.");
        NotificationService::success('Conexão ativada com sucesso.');

        header('Location: ' . url('/ssh/conexoes'));
        exit;
    }

    public function desativar(): void
    {
        AuthMiddleware::checkModulo('ssh_conexoes');

        $id = (int)($_GET['id'] ?? 0);
        $this->service->desativar($id);

        AuditService::registrar('SSH', 'Desativar conexão', "Conexão #{$id} desativada.");
        NotificationService::success('Conexão desativada com sucesso.');

        header('Location: ' . url('/ssh/conexoes'));
        exit;
    }

    public function excluirForm(): void
    {
        AuthMiddleware::checkModulo('ssh_conexoes');

        $id = (int)($_GET['id'] ?? 0);
        $conexao = $this->service->buscar($id);

        if (!$conexao) {
            header('Location: ' . url('/ssh/conexoes'));
            exit;
        }

        $this->view('ssh/conexao_excluir', ['conexao' => $conexao]);
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('ssh_conexoes');

        $id = (int)($_POST['id'] ?? 0);

        if ($this->service->excluir($id)) {
            AuditService::registrar('SSH', 'Excluir conexão', "Conexão #{$id} excluída.");
            NotificationService::success('Conexão excluída com sucesso.');
        }

        header('Location: ' . url('/ssh/conexoes'));
        exit;
    }

    public function testar(): void
    {
        AuthMiddleware::checkModulo('ssh_conexoes');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->testar($id);

        AuditService::registrar('SSH', 'Testar conexão', "Teste da conexão #{$id}: " . ($resultado['success'] ? 'ok' : 'falhou'));

        echo json_encode($resultado);
    }

    /** Fluxo modal + fetch (igual RDP) -- sempre JSON, nunca redirect. */
    public function conectar(): void
    {
        AuthMiddleware::checkModulo('ssh_conexoes');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $conexao = $this->service->buscar($id);

        if (!$conexao) {
            echo json_encode(['success' => false, 'message' => 'Conexão não encontrada.']);
            return;
        }

        $gateway = new GuacdGatewayService();
        if (!$gateway->pronto()) {
            echo json_encode(['success' => false, 'message' => 'O suporte a acesso remoto pelo navegador ainda não está pronto neste servidor. Instale pelo módulo Ativos > RDP (mesmo gateway) ou pelo botão abaixo.']);
            return;
        }

        $largura = (int)($_POST['largura'] ?? 1024);
        $altura = (int)($_POST['altura'] ?? 768);
        $token = $this->service->gerarToken($id, $largura, $altura);

        if ($token === null) {
            echo json_encode(['success' => false, 'message' => 'Nenhuma credencial válida configurada pra esta conexão.']);
            return;
        }

        AuditService::registrar('SSH', 'Terminal', "Sessão de terminal aberta para a conexão #{$id} ({$conexao['nome']}).");

        echo json_encode(['success' => true, 'token' => $token]);
    }

    public function statusGateway(): void
    {
        AuthMiddleware::checkModulo('ssh_conexoes');
        header('Content-Type: application/json');

        $gateway = new GuacdGatewayService();

        echo json_encode(['success' => true, 'gateway' => $gateway->status()]);
    }

    public function instalarGateway(): void
    {
        AuthMiddleware::checkModulo('ssh_conexoes');
        header('Content-Type: application/json');
        set_time_limit(180);

        $gateway = new GuacdGatewayService();
        $resultado = $gateway->instalar();

        if ($resultado['success']) {
            AuditService::registrar('SSH', 'Gateway', 'Suporte a acesso remoto pelo navegador instalado neste servidor.');
        }

        echo json_encode($resultado);
    }
}
