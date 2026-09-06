<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\ProjetoService;

/**
 * Lixeira de Projetos -- soft-delete (projetos.excluido_em), 30 dias
 * pra restaurar antes da purga automática (`rd projetos:purgar-lixeira`).
 * Ação de admin do módulo, mesmo corte usado pra Áreas e pro link do
 * Modo TV -- gestor de área não gerencia a lixeira.
 */
class ProjetoLixeiraController extends Controller
{
    private ProjetoService $service;

    public function __construct()
    {
        $this->service = new ProjetoService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('projetos_gerenciar');

        $this->view('projetos/lixeira', [
            'projetos' => $this->service->listarLixeira(),
        ]);
    }

    public function restaurar(): void
    {
        AuthMiddleware::checkModulo('projetos_gerenciar');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->restaurar($id);

        AuditService::registrar('Projetos', 'Restaurar projeto', "#{$id}: {$resultado['message']}");

        NotificationService::success($resultado['message']);
        header('Location: ' . url('/projetos/lixeira'));
        exit;
    }

    public function excluirDefinitivo(): void
    {
        AuthMiddleware::checkModulo('projetos_gerenciar');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->excluirDefinitivo($id);

        AuditService::registrar('Projetos', 'Excluir projeto definitivamente', "#{$id}: {$resultado['message']}");

        NotificationService::success($resultado['message']);
        header('Location: ' . url('/projetos/lixeira'));
        exit;
    }
}
