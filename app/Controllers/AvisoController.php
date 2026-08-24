<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\AvisoService;
use App\Services\GrupoService;
use App\Services\ModuloCatalogo;
use App\Services\NotificationService;
use App\Services\PermissionService;
use App\Services\UserService;

class AvisoController extends Controller
{
    private AvisoService $service;

    public function __construct()
    {
        $this->service = new AvisoService();
    }

    /*
     |---------------------------------------------------------
     | Mural -- qualquer usuário logado, sem exigir módulo específico
     | (mesmo critério do Dashboard: AuthMiddleware::check()).
     |---------------------------------------------------------
     */

    public function mural(): void
    {
        AuthMiddleware::check();

        if (!ModuloCatalogo::grupoHabilitado('Avisos')) {
            header('Location: ' . url('/dashboard'));
            exit;
        }

        $usuarioId = (int)$_SESSION['usuario']['id'];

        $this->view('avisos/mural', [
            'avisos' => $this->service->listarVisiveisParaUsuario($usuarioId),
            'podeGerenciar' => PermissionService::temAcesso('avisos_gerenciar'),
        ]);
    }

    /** Chamado via JS quando o usuário clica/abre um aviso -- marca "visto" e some do contador de não lidos. */
    public function marcarVisto(): void
    {
        AuthMiddleware::check();
        header('Content-Type: application/json');

        $avisoId = (int)($_POST['id'] ?? 0);
        $this->service->marcarVisto($avisoId, (int)$_SESSION['usuario']['id']);

        echo json_encode(['success' => true]);
    }

    public function confirmar(): void
    {
        AuthMiddleware::check();

        $avisoId = (int)($_POST['id'] ?? 0);
        $usuarioId = (int)$_SESSION['usuario']['id'];

        $resultado = $this->service->confirmarLeitura($avisoId, $usuarioId);

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/avisos'));
        exit;
    }

    public function contadorApi(): void
    {
        AuthMiddleware::check();
        header('Content-Type: application/json');

        if (!ModuloCatalogo::grupoHabilitado('Avisos')) {
            echo json_encode(['success' => true, 'nao_lidos' => 0]);
            return;
        }

        $naoLidos = $this->service->contarNaoLidos((int)$_SESSION['usuario']['id']);

        echo json_encode(['success' => true, 'nao_lidos' => $naoLidos]);
    }

    /*
     |---------------------------------------------------------
     | Gerenciar -- publicar/editar/excluir, atrás do módulo
     | avisos_gerenciar (TI, RH, Comunicação -- concedido via Grupos).
     |---------------------------------------------------------
     */

    public function gerenciar(): void
    {
        AuthMiddleware::checkModulo('avisos_gerenciar');

        $this->view('avisos/gerenciar', [
            'avisos' => $this->service->listar(),
            'usuarios' => (new UserService())->listar(),
            'grupos' => (new GrupoService())->listar(),
        ]);
    }

    public function criar(): void
    {
        AuthMiddleware::checkModulo('avisos_gerenciar');

        $dados = $_POST;
        $dados['usuario_id'] = (int)$_SESSION['usuario']['id'];

        $resultado = $this->service->criar($dados, $this->destinatariosDoPost());

        AuditService::registrar('Avisos', 'Publicar', $resultado['message']);

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/avisos/gerenciar'));
        exit;
    }

    public function atualizar(): void
    {
        AuthMiddleware::checkModulo('avisos_gerenciar');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->atualizar($id, $_POST, $this->destinatariosDoPost());

        AuditService::registrar('Avisos', 'Editar', "#{$id}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/avisos/gerenciar'));
        exit;
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('avisos_gerenciar');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->excluir($id);

        AuditService::registrar('Avisos', 'Excluir', "#{$id}: {$resultado['message']}");

        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/avisos/gerenciar'));
        exit;
    }

    public function destinatariosApi(): void
    {
        AuthMiddleware::checkModulo('avisos_gerenciar');
        header('Content-Type: application/json');

        $id = (int)($_GET['id'] ?? 0);

        echo json_encode(['success' => true, 'destinatarios' => $this->service->destinatariosDoAviso($id)]);
    }

    public function relatorioApi(): void
    {
        AuthMiddleware::checkModulo('avisos_gerenciar');
        header('Content-Type: application/json');

        $id = (int)($_GET['id'] ?? 0);
        $aviso = $this->service->buscar($id);

        echo json_encode([
            'success' => true,
            'confirmacao_obrigatoria' => (bool)($aviso['confirmacao_obrigatoria'] ?? false),
            'relatorio' => $this->service->relatorioLeitura($id),
        ]);
    }

    /** @return array<int, array{tipo:string, id?:int}> */
    private function destinatariosDoPost(): array
    {
        $destinatarios = [];

        if (!empty($_POST['destinatario_todos'])) {
            $destinatarios[] = ['tipo' => 'todos'];
        }
        foreach ($_POST['destinatario_grupos'] ?? [] as $grupoId) {
            $destinatarios[] = ['tipo' => 'grupo', 'id' => (int)$grupoId];
        }
        foreach ($_POST['destinatario_usuarios'] ?? [] as $usuarioId) {
            $destinatarios[] = ['tipo' => 'usuario', 'id' => (int)$usuarioId];
        }

        return $destinatarios;
    }
}
