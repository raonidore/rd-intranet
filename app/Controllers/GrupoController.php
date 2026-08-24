<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\GrupoService;
use App\Services\ModuloCatalogo;
use App\Services\NotificationService;
use App\Services\UserService;

class GrupoController extends Controller
{
    private GrupoService $service;

    public function __construct()
    {
        $this->service = new GrupoService();
    }

    public function index(): void
    {
        AuthMiddleware::checkAdmin();

        $grupos = $this->service->listar();

        $usuariosAtivos = array_values(array_filter(
            (new UserService())->listar(),
            fn (array $u) => (bool)$u['ativo']
        ));

        $usuariosPorGrupo = [];
        $modulosPorGrupo = [];
        foreach ($grupos as $grupo) {
            $usuariosPorGrupo[$grupo['id']] = $this->service->idsUsuariosDoGrupo((int)$grupo['id']);
            $modulosPorGrupo[$grupo['id']] = $this->service->modulosDoGrupo((int)$grupo['id']);
        }

        $this->view('administracao/grupos', [
            'grupos' => $grupos,
            'usuariosAtivos' => $usuariosAtivos,
            'usuariosPorGrupo' => $usuariosPorGrupo,
            'modulosPorGrupo' => $modulosPorGrupo,
            'modulosAgrupados' => ModuloCatalogo::agrupados(),
        ]);
    }

    public function criar(): void
    {
        AuthMiddleware::checkAdmin();

        $nome = trim($_POST['nome'] ?? '');
        $resultado = $this->service->criar($nome, $_POST['descricao'] ?? '');

        AuditService::registrar('Grupos', 'Criar grupo', "Grupo \"{$nome}\": {$resultado['message']}");

        $this->notificarEVoltar($resultado);
    }

    public function atualizar(): void
    {
        AuthMiddleware::checkAdmin();

        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $resultado = $this->service->atualizar($id, $nome, $_POST['descricao'] ?? '');

        AuditService::registrar('Grupos', 'Atualizar grupo', "Grupo #{$id}: {$resultado['message']}");

        $this->notificarEVoltar($resultado);
    }

    public function excluir(): void
    {
        AuthMiddleware::checkAdmin();

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->excluir($id);

        AuditService::registrar('Grupos', 'Excluir grupo', "Grupo #{$id} removido.");

        $this->notificarEVoltar($resultado);
    }

    public function salvarUsuarios(): void
    {
        AuthMiddleware::checkAdmin();

        $grupoId = (int)($_POST['grupo_id'] ?? 0);
        $usuarios = $_POST['usuarios'] ?? [];
        $resultado = $this->service->salvarUsuariosDoGrupo($grupoId, is_array($usuarios) ? $usuarios : []);

        AuditService::registrar('Grupos', 'Usuários do grupo', "Grupo #{$grupoId}: {$resultado['message']}");

        $this->notificarEVoltar($resultado);
    }

    public function salvarModulos(): void
    {
        AuthMiddleware::checkAdmin();

        $grupoId = (int)($_POST['grupo_id'] ?? 0);
        $modulos = $_POST['modulos'] ?? [];
        $resultado = $this->service->salvarModulosDoGrupo($grupoId, is_array($modulos) ? $modulos : []);

        AuditService::registrar('Grupos', 'Módulos do grupo', "Grupo #{$grupoId}: {$resultado['message']}");

        $this->notificarEVoltar($resultado);
    }

    private function notificarEVoltar(array $resultado): void
    {
        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/administracao/grupos'));
        exit;
    }
}
