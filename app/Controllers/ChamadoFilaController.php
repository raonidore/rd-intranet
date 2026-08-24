<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\ChamadoService;
use App\Services\ChamadoSetorService;
use App\Services\NotificationService;

class ChamadoFilaController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::checkModulo('chamados_fila');

        $usuario = $_SESSION['usuario'];
        $ehAdmin = ($usuario['perfil'] ?? '') === 'admin';
        $setorIds = $ehAdmin ? null : (new ChamadoSetorService())->idsSetoresDoUsuario((int)$usuario['id']);

        $this->view('chamados/fila', [
            'fila' => (new ChamadoService())->listarFila($setorIds),
        ]);
    }

    public function assumir(): void
    {
        AuthMiddleware::checkModulo('chamados_fila');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = (new ChamadoService())->assumir($id, (int)$_SESSION['usuario']['id']);

        AuditService::registrar('Chamados', 'Assumir chamado', "Chamado #{$id}: {$resultado['message']}");

        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
            header('Location: ' . url('/chamados/atendimentos/ver?id=' . $id));
            exit;
        }

        NotificationService::error($resultado['message']);
        header('Location: ' . url('/chamados/fila'));
        exit;
    }
}
