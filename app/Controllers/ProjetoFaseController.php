<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\PermissionService;
use App\Services\ProjetoFaseService;
use App\Services\ProjetoService;

class ProjetoFaseController extends Controller
{
    private ProjetoFaseService $service;
    private ProjetoService $projetoService;

    public function __construct()
    {
        $this->service = new ProjetoFaseService();
        $this->projetoService = new ProjetoService();
    }

    /** Garante que quem está mexendo na fase realmente enxerga o projeto dono dela -- senão sai pra fora. */
    private function garantirAcesso(int $projetoId): void
    {
        $projeto = $this->projetoService->buscar($projetoId);
        $usuarioId = (int)$_SESSION['usuario']['id'];
        $ehAdmin = PermissionService::ehAdmin() || PermissionService::temAcesso('projetos_gerenciar');

        if (!$projeto || !$this->projetoService->ehVisivelPara($projeto, $usuarioId, $ehAdmin)) {
            NotificationService::error('Você não tem acesso a esse projeto.');
            header('Location: ' . url('/projetos'));
            exit;
        }
    }

    public function criar(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');

        $projetoId = (int)($_POST['projeto_id'] ?? 0);
        $this->garantirAcesso($projetoId);

        $resultado = $this->service->criar(
            $projetoId,
            (string)($_POST['nome'] ?? ''),
            null,
            (string)($_POST['data_inicio'] ?? ''),
            (string)($_POST['data_fim_prevista'] ?? '')
        );

        AuditService::registrar('Projetos', 'Criar fase', "projeto #{$projetoId}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/projetos/ver?id=' . $projetoId));
        exit;
    }

    public function atualizar(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');

        $id = (int)($_POST['id'] ?? 0);
        $projetoId = (int)($_POST['projeto_id'] ?? 0);
        $this->garantirAcesso($projetoId);

        $resultado = $this->service->atualizar(
            $id,
            (string)($_POST['nome'] ?? ''),
            (int)($_POST['ordem'] ?? 0),
            (string)($_POST['data_inicio'] ?? ''),
            (string)($_POST['data_fim_prevista'] ?? '')
        );

        AuditService::registrar('Projetos', 'Editar fase', "#{$id}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/projetos/ver?id=' . $projetoId));
        exit;
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');

        $id = (int)($_POST['id'] ?? 0);
        $projetoId = (int)($_POST['projeto_id'] ?? 0);
        $this->garantirAcesso($projetoId);

        $resultado = $this->service->excluir($id);

        AuditService::registrar('Projetos', 'Excluir fase', "#{$id}: {$resultado['message']}");

        NotificationService::success($resultado['message']);
        header('Location: ' . url('/projetos/ver?id=' . $projetoId));
        exit;
    }
}
