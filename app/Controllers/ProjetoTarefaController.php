<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\PermissionService;
use App\Services\ProjetoParticipanteExternoService;
use App\Services\ProjetoService;
use App\Services\ProjetoTarefaService;
use App\Services\UserService;

class ProjetoTarefaController extends Controller
{
    private ProjetoTarefaService $service;
    private ProjetoService $projetoService;

    public function __construct()
    {
        $this->service = new ProjetoTarefaService();
        $this->projetoService = new ProjetoService();
    }

    /** Garante que quem está mexendo na tarefa realmente enxerga o projeto dono dela -- senão sai pra fora. */
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

    /** Igual garantirAcesso(), mas responde JSON em vez de redirecionar -- usado pelo endpoint AJAX do drag-and-drop. */
    private function garantirAcessoJson(int $projetoId): void
    {
        $projeto = $this->projetoService->buscar($projetoId);
        $usuarioId = (int)$_SESSION['usuario']['id'];
        $ehAdmin = PermissionService::ehAdmin() || PermissionService::temAcesso('projetos_gerenciar');

        if (!$projeto || !$this->projetoService->ehVisivelPara($projeto, $usuarioId, $ehAdmin)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Você não tem acesso a esse projeto.']);
            exit;
        }
    }

    public function criar(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');

        $projetoId = (int)($_POST['projeto_id'] ?? 0);
        $this->garantirAcesso($projetoId);

        $resultado = $this->service->criar($projetoId, $_POST, (int)$_SESSION['usuario']['id']);

        AuditService::registrar('Projetos', 'Criar tarefa', "projeto #{$projetoId}: {$resultado['message']}");

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

        $resultado = $this->service->atualizar($id, $_POST);

        AuditService::registrar('Projetos', 'Editar tarefa', "#{$id}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/projetos/ver?id=' . $projetoId));
        exit;
    }

    /** Endpoint AJAX do drag-and-drop do Kanban -- chamado pelo onEnd do SortableJS. */
    public function mover(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');

        $id = (int)($_POST['id'] ?? 0);
        $coluna = (string)($_POST['coluna'] ?? '');
        $posicao = (int)($_POST['posicao'] ?? 0);

        $tarefa = $this->service->buscar($id);
        if (!$tarefa) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Tarefa não encontrada.']);
            return;
        }
        $this->garantirAcessoJson((int)$tarefa['projeto_id']);

        header('Content-Type: application/json');
        $resultado = $this->service->mover($id, $coluna, $posicao, (int)$_SESSION['usuario']['id']);

        echo json_encode($resultado);
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');

        $id = (int)($_POST['id'] ?? 0);
        $projetoId = (int)($_POST['projeto_id'] ?? 0);
        $this->garantirAcesso($projetoId);

        $resultado = $this->service->excluir($id);

        AuditService::registrar('Projetos', 'Excluir tarefa', "#{$id}: {$resultado['message']}");

        NotificationService::success($resultado['message']);
        header('Location: ' . url('/projetos/ver?id=' . $projetoId));
        exit;
    }

    /** JSON leve pro autocomplete de responsável interno -- mesmo padrão de /chamados/atendimentos/usuarios-buscar. */
    public function usuariosBuscarApi(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');
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

    public function responsavelAdicionar(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');

        $tarefaId = (int)($_POST['tarefa_id'] ?? 0);
        $projetoId = (int)($_POST['projeto_id'] ?? 0);
        $this->garantirAcesso($projetoId);

        $usuarioId = (int)($_POST['usuario_id'] ?? 0);
        $resultado = $this->service->adicionarResponsavel($tarefaId, $usuarioId, (int)$_SESSION['usuario']['id']);

        NotificationService::success($resultado['message']);
        header('Location: ' . url('/projetos/ver?id=' . $projetoId));
        exit;
    }

    public function responsavelRemover(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');

        $tarefaId = (int)($_POST['tarefa_id'] ?? 0);
        $projetoId = (int)($_POST['projeto_id'] ?? 0);
        $this->garantirAcesso($projetoId);

        $usuarioId = (int)($_POST['usuario_id'] ?? 0);
        $resultado = $this->service->removerResponsavel($tarefaId, $usuarioId);

        NotificationService::success($resultado['message']);
        header('Location: ' . url('/projetos/ver?id=' . $projetoId));
        exit;
    }

    /** JSON leve pro autocomplete de participante externo -- busca por nome/e-mail/empresa. */
    public function externosBuscarApi(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');
        header('Content-Type: application/json');

        $termo = trim($_GET['q'] ?? '');
        if (strlen($termo) < 2) {
            echo json_encode(['success' => true, 'participantes' => []]);
            return;
        }

        $participantes = (new ProjetoParticipanteExternoService())->buscarPorTermo($termo);

        echo json_encode([
            'success' => true,
            'participantes' => array_map(fn (array $p) => [
                'id' => (int)$p['id'],
                'nome' => $p['nome'],
                'email' => $p['email'],
                'empresa' => $p['empresa'],
            ], $participantes),
        ]);
    }

    public function externoAdicionar(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');

        $tarefaId = (int)($_POST['tarefa_id'] ?? 0);
        $projetoId = (int)($_POST['projeto_id'] ?? 0);
        $this->garantirAcesso($projetoId);

        if (!empty($_POST['participante_externo_id'])) {
            $participanteId = (int)$_POST['participante_externo_id'];
        } else {
            $nome = trim($_POST['nome'] ?? '');
            if ($nome === '') {
                NotificationService::error('Informe o nome do participante externo.');
                header('Location: ' . url('/projetos/ver?id=' . $projetoId));
                exit;
            }
            $participante = (new ProjetoParticipanteExternoService())->buscarOuCriar(
                $nome,
                (string)($_POST['email'] ?? '') ?: null,
                (string)($_POST['telefone'] ?? '') ?: null,
                (string)($_POST['empresa'] ?? '') ?: null
            );
            $participanteId = (int)$participante['id'];
        }

        $resultado = $this->service->adicionarExterno($tarefaId, $participanteId);

        AuditService::registrar('Projetos', 'Adicionar participante externo', "tarefa #{$tarefaId}: participante #{$participanteId}");

        NotificationService::success($resultado['message']);
        header('Location: ' . url('/projetos/ver?id=' . $projetoId));
        exit;
    }

    public function externoRemover(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');

        $tarefaId = (int)($_POST['tarefa_id'] ?? 0);
        $projetoId = (int)($_POST['projeto_id'] ?? 0);
        $this->garantirAcesso($projetoId);

        $participanteId = (int)($_POST['participante_externo_id'] ?? 0);
        $resultado = $this->service->removerExterno($tarefaId, $participanteId);

        NotificationService::success($resultado['message']);
        header('Location: ' . url('/projetos/ver?id=' . $projetoId));
        exit;
    }
}
