<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\ProjetoAreaService;
use App\Services\ProjetoPainelTvTokenService;
use App\Services\UserService;

class ProjetoAreaController extends Controller
{
    private ProjetoAreaService $service;

    public function __construct()
    {
        $this->service = new ProjetoAreaService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('projetos_gerenciar');

        $areas = $this->service->listar();
        foreach ($areas as &$area) {
            $area['gestores'] = $this->service->gestores((int)$area['id']);
            $area['paineis_tv'] = (new ProjetoPainelTvTokenService())->listarPorArea((int)$area['id']);
        }
        unset($area);

        $this->view('projetos/areas', ['areas' => $areas]);
    }

    public function criar(): void
    {
        AuthMiddleware::checkModulo('projetos_gerenciar');

        $resultado = $this->service->criar((string)($_POST['nome'] ?? ''));

        AuditService::registrar('Projetos', 'Criar área', $resultado['message']);

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/projetos/areas'));
        exit;
    }

    public function atualizar(): void
    {
        AuthMiddleware::checkModulo('projetos_gerenciar');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->atualizar($id, (string)($_POST['nome'] ?? ''), !empty($_POST['ativo']));

        AuditService::registrar('Projetos', 'Editar área', "#{$id}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/projetos/areas'));
        exit;
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('projetos_gerenciar');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->excluir($id);

        AuditService::registrar('Projetos', 'Excluir área', "#{$id}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/projetos/areas'));
        exit;
    }

    public function gestorAdicionar(): void
    {
        AuthMiddleware::checkModulo('projetos_gerenciar');

        $areaId = (int)($_POST['area_id'] ?? 0);
        $usuarioId = (int)($_POST['usuario_id'] ?? 0);
        $resultado = $this->service->adicionarGestor($areaId, $usuarioId);

        AuditService::registrar('Projetos', 'Adicionar gestor de área', "área #{$areaId}: usuário #{$usuarioId}");

        NotificationService::success($resultado['message']);
        header('Location: ' . url('/projetos/areas'));
        exit;
    }

    public function gestorRemover(): void
    {
        AuthMiddleware::checkModulo('projetos_gerenciar');

        $gestorId = (int)($_POST['gestor_id'] ?? 0);
        $resultado = $this->service->removerGestor($gestorId);

        AuditService::registrar('Projetos', 'Remover gestor de área', "#{$gestorId}");

        NotificationService::success($resultado['message']);
        header('Location: ' . url('/projetos/areas'));
        exit;
    }

    /** JSON leve pro autocomplete de usuário no seletor de gestor. */
    public function usuariosBuscarApi(): void
    {
        AuthMiddleware::checkModulo('projetos_gerenciar');
        header('Content-Type: application/json');

        $termo = trim($_GET['q'] ?? '');
        if (strlen($termo) < 2) {
            echo json_encode(['success' => true, 'usuarios' => []]);
            return;
        }

        $usuarios = (new UserService())->buscarPorTermo($termo);

        echo json_encode([
            'success' => true,
            'usuarios' => array_map(fn (array $u) => [
                'id' => (int)$u['id'],
                'nome' => $u['nome'],
                'email' => $u['email'],
            ], $usuarios),
        ]);
    }
}
