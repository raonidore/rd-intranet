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

        if ($resultado['success'] && $tarefaId !== null) {
            (new ProjetoNotificacaoService())->notificarComentario((int)$resultado['id']);
        }

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/projetos/ver?id=' . $projetoId));
        exit;
    }
}
