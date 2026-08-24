<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\ChamadoExternoCategoriaService;
use App\Services\NotificationService;

class ChamadoExternoCategoriaController extends Controller
{
    private ChamadoExternoCategoriaService $service;

    public function __construct()
    {
        $this->service = new ChamadoExternoCategoriaService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('chamados_externos_categorias');

        $this->view('chamados_externos/categorias', ['categorias' => $this->service->listar()]);
    }

    public function criar(): void
    {
        AuthMiddleware::checkModulo('chamados_externos_categorias');

        $resultado = $this->service->criar((string)($_POST['nome'] ?? ''));

        AuditService::registrar('Chamados Externos', 'Criar categoria', $resultado['message']);

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/chamados-externos/categorias'));
        exit;
    }

    public function atualizar(): void
    {
        AuthMiddleware::checkModulo('chamados_externos_categorias');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->atualizar($id, (string)($_POST['nome'] ?? ''), !empty($_POST['ativo']));

        AuditService::registrar('Chamados Externos', 'Editar categoria', "#{$id}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/chamados-externos/categorias'));
        exit;
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('chamados_externos_categorias');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->excluir($id);

        AuditService::registrar('Chamados Externos', 'Excluir categoria', "#{$id}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/chamados-externos/categorias'));
        exit;
    }
}
