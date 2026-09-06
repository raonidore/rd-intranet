<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\PermissionService;
use App\Services\ProjetoAreaService;
use App\Services\ProjetoComentarioService;
use App\Services\ProjetoAnexoService;
use App\Services\ProjetoEstatisticaService;
use App\Services\ProjetoFaseService;
use App\Services\ProjetoService;
use App\Services\ProjetoTarefaService;

class ProjetoController extends Controller
{
    private ProjetoService $service;

    public function __construct()
    {
        $this->service = new ProjetoService();
    }

    private function ehAdmin(): bool
    {
        return PermissionService::ehAdmin() || PermissionService::temAcesso('projetos_gerenciar');
    }

    /**
     * Busca o projeto e barra na hora quem não tem nenhum dos 3 papéis
     * (admin, gestor da área, responsável por alguma tarefa) -- sem
     * isso, a regra de visibilidade do menu/listagem não vale nada
     * contra alguém digitando um id de projeto alheio na URL.
     */
    private function projetoVisivelOuSair(int $id, bool $exigirGerenciar = false): array
    {
        $projeto = $this->service->buscar($id);
        if (!$projeto) {
            header('Location: ' . url('/projetos'));
            exit;
        }

        $usuarioId = (int)$_SESSION['usuario']['id'];
        $ehAdmin = $this->ehAdmin();
        $liberado = $exigirGerenciar
            ? $this->service->podeGerenciar($projeto, $usuarioId, $ehAdmin)
            : $this->service->ehVisivelPara($projeto, $usuarioId, $ehAdmin);

        if (!$liberado) {
            NotificationService::error('Você não tem acesso a esse projeto.');
            header('Location: ' . url('/projetos'));
            exit;
        }

        return $projeto;
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');

        $usuarioId = (int)$_SESSION['usuario']['id'];

        $filtros = array_filter([
            'status' => $_GET['status'] ?? null,
            'area_id' => $_GET['area_id'] ?? null,
        ]);

        $this->view('projetos/index', [
            'projetos' => $this->service->visivelPara($usuarioId, $this->ehAdmin(), $filtros),
            'areas' => (new ProjetoAreaService())->listarAtivas(),
            'filtros' => $filtros,
        ]);
    }

    public function novoForm(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');

        $this->view('projetos/form', [
            'projeto' => null,
            'areas' => (new ProjetoAreaService())->listarAtivas(),
        ]);
    }

    public function novo(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');

        $resultado = $this->service->criar($_POST, (int)$_SESSION['usuario']['id']);

        AuditService::registrar('Projetos', 'Criar projeto', $resultado['message']);

        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
            header('Location: ' . url('/projetos/ver?id=' . $resultado['id']));
            exit;
        }

        NotificationService::error($resultado['message']);
        header('Location: ' . url('/projetos/novo'));
        exit;
    }

    public function ver(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');

        $id = (int)($_GET['id'] ?? 0);
        $projeto = $this->projetoVisivelOuSair($id);

        $usuarioId = (int)$_SESSION['usuario']['id'];
        $tarefaService = new ProjetoTarefaService();

        $this->view('projetos/ver', [
            'projeto' => $projeto,
            'podeGerenciar' => $this->service->podeGerenciar($projeto, $usuarioId, $this->ehAdmin()),
            'fases' => (new ProjetoFaseService())->listar($id),
            'quadro' => $tarefaService->quadro($id),
            'resumo' => $tarefaService->resumo($id),
            'timeline' => (new ProjetoComentarioService())->timeline($id),
            'anexos' => (new ProjetoAnexoService())->porProjeto($id),
            'tarefaService' => $tarefaService,
        ]);
    }

    public function editar(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');

        $id = (int)($_POST['id'] ?? 0);
        $this->projetoVisivelOuSair($id, true);

        $resultado = $this->service->atualizar($id, $_POST);

        AuditService::registrar('Projetos', 'Editar projeto', "#{$id}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/projetos/ver?id=' . $id));
        exit;
    }

    public function mudarStatus(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');

        $id = (int)($_POST['id'] ?? 0);
        $this->projetoVisivelOuSair($id, true);

        $status = (string)($_POST['status'] ?? '');
        $resultado = $this->service->mudarStatus($id, $status, (int)$_SESSION['usuario']['id']);

        AuditService::registrar('Projetos', 'Mudar status', "#{$id}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/projetos/ver?id=' . $id));
        exit;
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');

        $id = (int)($_POST['id'] ?? 0);
        $this->projetoVisivelOuSair($id, true);

        $resultado = $this->service->excluir($id);

        AuditService::registrar('Projetos', 'Excluir projeto', "#{$id}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/projetos'));
        exit;
    }

    public function duplicar(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');

        $id = (int)($_POST['id'] ?? 0);
        $this->projetoVisivelOuSair($id, true);

        $novoTitulo = (string)($_POST['novo_titulo'] ?? '');
        $resultado = $this->service->duplicar($id, $novoTitulo, (int)$_SESSION['usuario']['id']);

        AuditService::registrar('Projetos', 'Duplicar projeto', "#{$id}: {$resultado['message']}");

        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
            header('Location: ' . url('/projetos/ver?id=' . $resultado['id']));
            exit;
        }

        NotificationService::error($resultado['message']);
        header('Location: ' . url('/projetos/ver?id=' . $id));
        exit;
    }

    public function estatisticas(): void
    {
        AuthMiddleware::checkModulo('projetos_estatisticas');

        $service = new ProjetoEstatisticaService();

        $this->view('projetos/estatisticas', [
            'resumo' => $service->resumo(),
            'porArea' => $service->porArea(),
            'tarefasPorColuna' => $service->tarefasPorColuna(),
            'porMes' => $service->porMes(),
        ]);
    }
}
