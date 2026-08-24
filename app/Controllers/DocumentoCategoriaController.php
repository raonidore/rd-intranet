<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\DocumentoCategoriaService;
use App\Services\DocumentoPermissaoService;
use App\Services\GrupoService;
use App\Services\NotificationService;
use App\Services\UserService;

class DocumentoCategoriaController extends Controller
{
    private DocumentoCategoriaService $service;
    private DocumentoPermissaoService $permissaoService;

    public function __construct()
    {
        $this->service = new DocumentoCategoriaService();
        $this->permissaoService = new DocumentoPermissaoService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('documentos_categorias');

        $categorias = $this->service->listar();
        $permissoesPorCategoria = [];
        foreach ($categorias as $c) {
            $permissoesPorCategoria[$c['id']] = $this->permissaoService->listarDaCategoria($c['id']);
        }

        $this->view('documentos/categorias', [
            'categorias' => $categorias,
            'usuarios' => (new UserService())->listar(),
            'grupos' => (new GrupoService())->listar(),
            'permissoesPorCategoria' => $permissoesPorCategoria,
        ]);
    }

    public function criar(): void
    {
        AuthMiddleware::checkModulo('documentos_categorias');

        $resultado = $this->service->criar((string)($_POST['nome'] ?? ''), (string)($_POST['descricao'] ?? ''));

        AuditService::registrar('Documentos', 'Criar categoria', $resultado['message']);

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/documentos/categorias'));
        exit;
    }

    public function atualizar(): void
    {
        AuthMiddleware::checkModulo('documentos_categorias');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->atualizar($id, (string)($_POST['nome'] ?? ''), (string)($_POST['descricao'] ?? ''), !empty($_POST['ativo']));

        AuditService::registrar('Documentos', 'Editar categoria', "#{$id}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/documentos/categorias'));
        exit;
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('documentos_categorias');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->excluir($id);

        AuditService::registrar('Documentos', 'Excluir categoria', "#{$id}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/documentos/categorias'));
        exit;
    }

    public function salvarPermissoes(): void
    {
        AuthMiddleware::checkModulo('documentos_categorias');
        header('Content-Type: application/json');

        $categoriaId = (int)($_POST['categoria_id'] ?? 0);
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

        $this->permissaoService->salvarDaCategoria($categoriaId, $concessoes);

        AuditService::registrar('Documentos', 'Salvar permissões da categoria', "Categoria #{$categoriaId}");

        echo json_encode(['success' => true, 'message' => 'Permissões salvas.']);
    }
}
