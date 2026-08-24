<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\ContratoService;
use App\Services\FornecedorService;
use App\Services\FornecedorTipoServicoService;
use App\Services\NotificationService;

class FornecedorController extends Controller
{
    private FornecedorService $service;
    private FornecedorTipoServicoService $tipoServicoService;

    public function __construct()
    {
        $this->service = new FornecedorService();
        $this->tipoServicoService = new FornecedorTipoServicoService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('fornecedores_gerenciar');

        $this->view('fornecedores/index', [
            'fornecedores' => $this->service->listar(),
        ]);
    }

    public function novoForm(): void
    {
        AuthMiddleware::checkModulo('fornecedores_gerenciar');

        $this->view('fornecedores/form', [
            'fornecedor' => null,
            'tiposServico' => $this->tipoServicoService->listarAtivos(),
        ]);
    }

    public function novo(): void
    {
        AuthMiddleware::checkModulo('fornecedores_gerenciar');

        $resultado = $this->service->criar($_POST);

        AuditService::registrar('Fornecedores', 'Criar', $resultado['message']);

        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
            header('Location: ' . url('/fornecedores/ver?id=' . $resultado['id']));
            exit;
        }

        NotificationService::error($resultado['message']);
        header('Location: ' . url('/fornecedores/novo'));
        exit;
    }

    public function editarForm(): void
    {
        AuthMiddleware::checkModulo('fornecedores_gerenciar');

        $id = (int)($_GET['id'] ?? 0);
        $fornecedor = $this->service->buscar($id);

        if (!$fornecedor) {
            header('Location: ' . url('/fornecedores'));
            exit;
        }

        $this->view('fornecedores/form', [
            'fornecedor' => $fornecedor,
            'tiposServico' => $this->tipoServicoService->listarAtivos(),
        ]);
    }

    public function editar(): void
    {
        AuthMiddleware::checkModulo('fornecedores_gerenciar');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->atualizar($id, $_POST);

        AuditService::registrar('Fornecedores', 'Editar', "Fornecedor #{$id}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/fornecedores/ver?id=' . $id));
        exit;
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('fornecedores_gerenciar');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->excluir($id);

        AuditService::registrar('Fornecedores', 'Excluir', "Fornecedor #{$id}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url($resultado['success'] ? '/fornecedores' : '/fornecedores/ver?id=' . $id));
        exit;
    }

    public function ver(): void
    {
        AuthMiddleware::checkModulo('fornecedores_gerenciar');

        $id = (int)($_GET['id'] ?? 0);
        $fornecedor = $this->service->buscar($id);

        if (!$fornecedor) {
            header('Location: ' . url('/fornecedores'));
            exit;
        }

        $this->view('fornecedores/ver', [
            'fornecedor' => $fornecedor,
            'contratos' => (new ContratoService())->listarPorFornecedor($id),
        ]);
    }

    public function tiposServicoApi(): void
    {
        AuthMiddleware::checkModulo('fornecedores_gerenciar');
        header('Content-Type: application/json');

        echo json_encode(['success' => true, 'tipos' => $this->tipoServicoService->listar()]);
    }

    public function tiposServicoCriar(): void
    {
        AuthMiddleware::checkModulo('fornecedores_gerenciar');
        header('Content-Type: application/json');

        $resultado = $this->tipoServicoService->criar((string)($_POST['nome'] ?? ''));
        if ($resultado['success']) {
            AuditService::registrar('Fornecedores', 'Criar tipo de serviço', $resultado['message']);
        }

        echo json_encode($resultado);
    }

    public function tiposServicoAtualizar(): void
    {
        AuthMiddleware::checkModulo('fornecedores_gerenciar');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->tipoServicoService->atualizar($id, (string)($_POST['nome'] ?? ''), !empty($_POST['ativo']));
        if ($resultado['success']) {
            AuditService::registrar('Fornecedores', 'Editar tipo de serviço', "#{$id}: {$resultado['message']}");
        }

        echo json_encode($resultado);
    }

    public function tiposServicoExcluir(): void
    {
        AuthMiddleware::checkModulo('fornecedores_gerenciar');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->tipoServicoService->excluir($id);
        if ($resultado['success']) {
            AuditService::registrar('Fornecedores', 'Excluir tipo de serviço', "#{$id}: {$resultado['message']}");
        }

        echo json_encode($resultado);
    }
}
