<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\NotificationService;
use App\Services\PermissionService;
use App\Services\ProjetoAnexoService;
use App\Services\ProjetoComentarioService;
use App\Services\ProjetoNotificacaoService;
use App\Services\ProjetoService;

class ProjetoComentarioController extends Controller
{
    private ProjetoService $projetoService;

    public function __construct()
    {
        $this->projetoService = new ProjetoService();
    }

    /**
     * Composer único (texto + foto/câmera + localização opcionais) --
     * usado tanto no desktop quanto no celular, mesma requisição.
     */
    public function comentar(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');

        $projetoId = (int)($_POST['projeto_id'] ?? 0);
        $projeto = $this->projetoService->buscar($projetoId);
        $usuarioId = (int)$_SESSION['usuario']['id'];
        $ehAdmin = PermissionService::ehAdmin() || PermissionService::temAcesso('projetos_gerenciar');

        if (!$projeto || !$this->projetoService->ehVisivelPara($projeto, $usuarioId, $ehAdmin)) {
            NotificationService::error('Você não tem acesso a esse projeto.');
            header('Location: ' . url('/projetos'));
            exit;
        }

        $tarefaId = !empty($_POST['tarefa_id']) ? (int)$_POST['tarefa_id'] : null;

        $latitude = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? (float)$_POST['latitude'] : null;
        $longitude = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;

        $resultado = (new ProjetoComentarioService())->comentar($projetoId, $tarefaId, (string)($_POST['conteudo'] ?? ''), $usuarioId, null, $latitude, $longitude);

        if ($resultado['success'] && !empty($_FILES['arquivo']['name'])) {
            (new ProjetoAnexoService())->anexarUploadComComentario($projetoId, $tarefaId, (int)$resultado['id'], $_FILES['arquivo'], $usuarioId, null);
        }

        if ($resultado['success']) {
            $comentarios = new ProjetoComentarioService();
            $marcados = $_POST['mencoes'] ?? [];
            if (is_array($marcados) && $marcados) {
                $comentarios->salvarMencoes((int)$resultado['id'], $tarefaId, (string)($_POST['conteudo'] ?? ''), $marcados, $projetoId);
            }
            $tarefaId !== null ? $comentarios->marcarLido($tarefaId, $usuarioId) : $comentarios->marcarLidoProjeto($projetoId, $usuarioId);
            (new ProjetoNotificacaoService())->notificarComentario((int)$resultado['id']);
        }

        // Comentou de dentro da janela da tarefa: volta com ela aberta, no fim da conversa.
        if ($tarefaId !== null && !empty($_POST['voltar_tarefa'])) {
            if (!$resultado['success']) {
                NotificationService::error($resultado['message']);
            }
            header('Location: ' . url('/projetos/ver?id=' . $projetoId . '&tarefa=' . $tarefaId));
            exit;
        }

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/projetos/ver?id=' . $projetoId));
        exit;
    }

    /** A janela da tarefa foi aberta: as mensagens dela deixam de contar como novas. */
    public function marcarLido(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');
        header('Content-Type: application/json');

        $tarefaId = (int)($_POST['tarefa_id'] ?? 0);
        $usuarioId = (int)$_SESSION['usuario']['id'];
        $tarefa = (new \App\Services\ProjetoTarefaService())->buscar($tarefaId);
        $projeto = $tarefa ? $this->projetoService->buscar((int)$tarefa['projeto_id']) : null;
        $ehAdmin = PermissionService::ehAdmin() || PermissionService::temAcesso('projetos_gerenciar');

        if (!$projeto || !$this->projetoService->ehVisivelPara($projeto, $usuarioId, $ehAdmin)) {
            echo json_encode(['success' => false]);
            return;
        }

        $comentarios = new ProjetoComentarioService();
        $comentarios->marcarLido($tarefaId, $usuarioId);
        $resumo = $comentarios->resumoNaoLidas($usuarioId);

        echo json_encode(['success' => true, 'total_nao_lidas' => $resumo['total'], 'mencoes' => $resumo['mencoes']]);
    }

    /** Consultado sozinho pelo menu (a cada 20 s): mensagens novas e menções nas tarefas do usuário. */
    public function contador(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');
        header('Content-Type: application/json');

        echo json_encode(['success' => true] + (new ProjetoComentarioService())->resumoNaoLidas((int)$_SESSION['usuario']['id']));
    }
}
