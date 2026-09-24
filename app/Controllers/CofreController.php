<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\CofrePermissaoService;
use App\Services\CofreService;
use App\Services\GrupoService;
use App\Services\NotificationService;
use App\Services\UserService;

class CofreController extends Controller
{
    private CofreService $service;
    private CofrePermissaoService $permissaoService;

    public function __construct()
    {
        $this->service = new CofreService();
        $this->permissaoService = new CofrePermissaoService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModuloRestrito('seguranca_cofre_senhas_gerenciar');

        $cofres = $this->service->listar();
        $permissoesPorCofre = [];
        foreach ($cofres as $c) {
            $permissoesPorCofre[$c['id']] = $this->permissaoService->listarDoCofre($c['id']);
        }

        $this->view('cofres/index', [
            'cofres' => $cofres,
            'usuarios' => (new UserService())->listar(),
            'grupos' => (new GrupoService())->listar(),
            'permissoesPorCofre' => $permissoesPorCofre,
        ]);
    }

    public function criar(): void
    {
        AuthMiddleware::checkModuloRestrito('seguranca_cofre_senhas_gerenciar');

        $resultado = $this->service->criar(
            (string)($_POST['nome'] ?? ''),
            (string)($_POST['descricao'] ?? ''),
            (int)($_SESSION['usuario']['id'] ?? 0)
        );

        AuditService::registrar('COFRE_SENHAS', 'Criar cofre', $resultado['message']);
        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);

        header('Location: ' . url('/seguranca/cofres'));
        exit;
    }

    public function atualizar(): void
    {
        AuthMiddleware::checkModuloRestrito('seguranca_cofre_senhas_gerenciar');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->atualizar($id, (string)($_POST['nome'] ?? ''), (string)($_POST['descricao'] ?? ''));

        AuditService::registrar('COFRE_SENHAS', 'Editar cofre', "#{$id}: {$resultado['message']}");
        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);

        header('Location: ' . url('/seguranca/cofres'));
        exit;
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModuloRestrito('seguranca_cofre_senhas_gerenciar');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->excluir($id);

        AuditService::registrar('COFRE_SENHAS', 'Excluir cofre', "#{$id}: {$resultado['message']}");
        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);

        header('Location: ' . url('/seguranca/cofres'));
        exit;
    }

    public function salvarPermissoes(): void
    {
        AuthMiddleware::checkModuloRestrito('seguranca_cofre_senhas_gerenciar');
        header('Content-Type: application/json');

        $cofreId = (int)($_POST['cofre_id'] ?? 0);
        $concessoes = [];

        foreach ($_POST['usuarios'] ?? [] as $usuarioId => $perm) {
            $concessoes[] = [
                'sujeito_tipo' => 'usuario',
                'sujeito_id' => (int)$usuarioId,
                'pode_visualizar' => !empty($perm['visualizar']),
                'pode_editar' => !empty($perm['editar']),
                'pode_excluir' => !empty($perm['excluir']),
            ];
        }

        foreach ($_POST['grupos'] ?? [] as $grupoId => $perm) {
            $concessoes[] = [
                'sujeito_tipo' => 'grupo',
                'sujeito_id' => (int)$grupoId,
                'pode_visualizar' => !empty($perm['visualizar']),
                'pode_editar' => !empty($perm['editar']),
                'pode_excluir' => !empty($perm['excluir']),
            ];
        }

        $this->permissaoService->salvarDoCofre($cofreId, $concessoes);

        AuditService::registrar('COFRE_SENHAS', 'Salvar permissões do cofre', "Cofre #{$cofreId}");

        echo json_encode(['success' => true, 'message' => 'Permissões salvas.']);
    }
}
