<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\CofreSenhaService;
use App\Services\NotificationService;

class CofreSenhaController extends Controller
{
    private CofreSenhaService $service;

    public function __construct()
    {
        $this->service = new CofreSenhaService();
    }

    private function usuarioId(): int
    {
        return (int)($_SESSION['usuario']['id'] ?? 0);
    }

    public function index(): void
    {
        AuthMiddleware::checkModuloRestrito('seguranca_cofre_senhas');

        $this->view('cofre_senhas/lista', [
            'segredos' => $this->service->listar($this->usuarioId()),
            'usuarioIdAtual' => $this->usuarioId(),
        ]);
    }

    public function novoForm(): void
    {
        AuthMiddleware::checkModuloRestrito('seguranca_cofre_senhas');

        $this->view('cofre_senhas/form', ['segredo' => null]);
    }

    public function novo(): void
    {
        AuthMiddleware::checkModuloRestrito('seguranca_cofre_senhas');

        $nome = trim($_POST['nome'] ?? '');

        if ($this->service->criar($_POST, $this->usuarioId())) {
            AuditService::registrar('COFRE_SENHAS', 'Criar segredo', "Segredo '{$nome}' criado.");
            NotificationService::success('Segredo criado com sucesso.');
        }

        header('Location: ' . url('/seguranca/cofre-senhas'));
        exit;
    }

    public function editarForm(): void
    {
        AuthMiddleware::checkModuloRestrito('seguranca_cofre_senhas');

        $id = (int)($_GET['id'] ?? 0);
        $segredo = $this->service->buscar($id, $this->usuarioId());

        if (!$segredo) {
            header('Location: ' . url('/seguranca/cofre-senhas'));
            exit;
        }

        $this->view('cofre_senhas/form', ['segredo' => $segredo]);
    }

    public function editar(): void
    {
        AuthMiddleware::checkModuloRestrito('seguranca_cofre_senhas');

        $id = (int)($_POST['id'] ?? 0);

        if ($this->service->atualizar($id, $_POST, $this->usuarioId())) {
            AuditService::registrar('COFRE_SENHAS', 'Editar segredo', "Segredo #{$id} atualizado.");
            NotificationService::success('Segredo atualizado com sucesso.');
        }

        header('Location: ' . url('/seguranca/cofre-senhas'));
        exit;
    }

    public function excluirForm(): void
    {
        AuthMiddleware::checkModuloRestrito('seguranca_cofre_senhas');

        $id = (int)($_GET['id'] ?? 0);
        $segredo = $this->service->buscar($id, $this->usuarioId());

        if (!$segredo) {
            header('Location: ' . url('/seguranca/cofre-senhas'));
            exit;
        }

        $this->view('cofre_senhas/excluir', ['segredo' => $segredo]);
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModuloRestrito('seguranca_cofre_senhas');

        $id = (int)($_POST['id'] ?? 0);

        if ($this->service->excluir($id, $this->usuarioId())) {
            AuditService::registrar('COFRE_SENHAS', 'Excluir segredo', "Segredo #{$id} excluído.");
            NotificationService::success('Segredo excluído com sucesso.');
        } else {
            NotificationService::error('Não foi possível excluir (segredo não encontrado ou você não é o dono).');
        }

        header('Location: ' . url('/seguranca/cofre-senhas'));
        exit;
    }

    /** AJAX -- decripta sob demanda e SEMPRE audita a leitura antes de responder. Nunca redireciona. */
    public function revelar(): void
    {
        AuthMiddleware::checkModuloRestrito('seguranca_cofre_senhas');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->revelar($id, $this->usuarioId());

        if ($resultado === null) {
            echo json_encode(['success' => false, 'message' => 'Segredo não encontrado ou sem permissão.']);
            return;
        }

        AuditService::registrar('COFRE_SENHAS', 'Visualizar segredo', "Segredo '{$resultado['nome']}' (id={$resultado['id']}) visualizado.");

        echo json_encode(['success' => true, 'senha' => $resultado['senha']]);
    }
}
