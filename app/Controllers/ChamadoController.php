<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\ChamadoCategoriaService;
use App\Services\ChamadoService;
use App\Services\ChamadoSetorService;
use App\Services\NotificationService;
use App\Services\UnidadeService;

class ChamadoController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::checkModulo('chamados_atendimentos');

        $service = new ChamadoService();
        $usuarioId = (int)$_SESSION['usuario']['id'];

        $aba = $_GET['aba'] ?? 'andamento';

        $this->view('chamados/atendimentos', [
            'aba' => $aba,
            'chamados' => $service->listarDoUsuario($usuarioId),
            'encerrados' => $aba === 'encerrados' ? $service->listarEncerradosDoUsuario($usuarioId) : [],
        ]);
    }

    public function novoForm(): void
    {
        AuthMiddleware::checkModulo('chamados_atendimentos');

        $this->view('chamados/novo', [
            'categorias' => (new ChamadoCategoriaService())->listarAtivas(),
            'setores' => (new ChamadoSetorService())->listarAtivos(),
            'unidades' => (new UnidadeService())->listarAtivas(),
        ]);
    }

    public function novo(): void
    {
        AuthMiddleware::checkModulo('chamados_atendimentos');

        $resultado = (new ChamadoService())->abrir($_POST);

        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
            header('Location: ' . url('/chamados/atendimentos/ver?id=' . $resultado['id']));
            exit;
        }

        NotificationService::error($resultado['message']);
        header('Location: ' . url('/chamados/atendimentos/novo'));
        exit;
    }

    public function ver(): void
    {
        AuthMiddleware::checkModulo('chamados_atendimentos');

        $id = (int)($_GET['id'] ?? 0);
        $service = new ChamadoService();
        $chamado = $service->buscar($id);

        if (!$chamado) {
            NotificationService::error('Chamado não encontrado.');
            header('Location: ' . url('/chamados/atendimentos'));
            exit;
        }

        $this->view('chamados/ver', [
            'chamado' => $chamado,
            'comentarios' => $service->comentarios($id),
            'historico' => $service->historico($id),
            'somenteLeitura' => in_array($chamado['status'], ['resolvido', 'fechado'], true),
        ]);
    }

    public function responder(): void
    {
        AuthMiddleware::checkModulo('chamados_atendimentos');

        $id = (int)($_POST['id'] ?? 0);
        $tipo = ($_POST['tipo'] ?? '') === 'interna' ? 'interna' : 'publica';

        $resultado = (new ChamadoService())->responder($id, $_POST['conteudo'] ?? '', $tipo, (int)$_SESSION['usuario']['id']);

        if (!$resultado['success']) {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/chamados/atendimentos/ver?id=' . $id));
        exit;
    }

    /** Contagem de "aguardando resposta" -- badge do menu e alerta sonoro. */
    public function contadorApi(): void
    {
        AuthMiddleware::checkModulo('chamados_atendimentos');
        header('Content-Type: application/json');

        $usuarioId = (int)$_SESSION['usuario']['id'];
        $service = new ChamadoService();

        echo json_encode([
            'success' => true,
            'aguardando' => $service->contarAguardandoResposta($usuarioId),
            'ultimoId' => $service->ultimoIdAguardandoResposta($usuarioId),
        ]);
    }

    public function mudarStatus(): void
    {
        AuthMiddleware::checkModulo('chamados_atendimentos');

        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';

        $resultado = (new ChamadoService())->mudarStatus($id, $status, (int)$_SESSION['usuario']['id']);

        AuditService::registrar('Chamados', 'Mudar status', "Chamado #{$id}: {$resultado['message']}");

        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/chamados/atendimentos/ver?id=' . $id));
        exit;
    }
}
