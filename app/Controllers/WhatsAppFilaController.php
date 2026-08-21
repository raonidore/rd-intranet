<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\WhatsAppAtendimentoService;
use App\Services\WhatsAppSetorService;

class WhatsAppFilaController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::checkModulo('whatsapp_fila');

        $usuario = $_SESSION['usuario'];
        $ehAdmin = ($usuario['perfil'] ?? '') === 'admin';
        $setorIds = $ehAdmin ? null : (new WhatsAppSetorService())->idsSetoresDoUsuario((int)$usuario['id']);

        $this->view('whatsapp/fila', [
            'fila' => (new WhatsAppAtendimentoService())->listarFila($setorIds),
        ]);
    }

    public function assumir(): void
    {
        AuthMiddleware::checkModulo('whatsapp_fila');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = (new WhatsAppAtendimentoService())->assumir($id, (int)$_SESSION['usuario']['id']);

        AuditService::registrar('WhatsApp', 'Assumir atendimento', "Atendimento #{$id}: {$resultado['message']}");

        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
            header('Location: ' . url('/whatsapp/atendimentos/ver?id=' . $id));
            exit;
        }

        NotificationService::error($resultado['message']);
        header('Location: ' . url('/whatsapp/fila'));
        exit;
    }
}
