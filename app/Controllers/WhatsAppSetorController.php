<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\UserService;
use App\Services\WhatsAppSetorService;

class WhatsAppSetorController extends Controller
{
    private WhatsAppSetorService $service;

    public function __construct()
    {
        $this->service = new WhatsAppSetorService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('whatsapp_setores');

        $setores = $this->service->listar();

        $usuariosAtivos = array_values(array_filter(
            (new UserService())->listar(),
            fn (array $u) => (bool)$u['ativo']
        ));

        $usuariosPorSetor = [];
        foreach ($setores as $setor) {
            $usuariosPorSetor[$setor['id']] = $this->service->idsUsuariosDoSetor((int)$setor['id']);
        }

        $this->view('whatsapp/setores', [
            'setores' => $setores,
            'usuariosAtivos' => $usuariosAtivos,
            'usuariosPorSetor' => $usuariosPorSetor,
        ]);
    }

    public function criar(): void
    {
        AuthMiddleware::checkModulo('whatsapp_setores');

        $nome = trim($_POST['nome'] ?? '');
        $resultado = $this->service->criar($nome);

        AuditService::registrar('WhatsApp', 'Criar setor', "Setor \"{$nome}\": {$resultado['message']}");

        $this->notificarEVoltar($resultado);
    }

    public function atualizar(): void
    {
        AuthMiddleware::checkModulo('whatsapp_setores');

        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $resultado = $this->service->atualizar($id, $nome, isset($_POST['ativo']));

        AuditService::registrar('WhatsApp', 'Atualizar setor', "Setor #{$id}: {$resultado['message']}");

        $this->notificarEVoltar($resultado);
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('whatsapp_setores');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->excluir($id);

        AuditService::registrar('WhatsApp', 'Excluir setor', "Setor #{$id} removido.");

        $this->notificarEVoltar($resultado);
    }

    public function salvarUsuarios(): void
    {
        AuthMiddleware::checkModulo('whatsapp_setores');

        $setorId = (int)($_POST['setor_id'] ?? 0);
        $usuarios = $_POST['usuarios'] ?? [];
        $resultado = $this->service->salvarUsuariosDoSetor($setorId, is_array($usuarios) ? $usuarios : []);

        AuditService::registrar('WhatsApp', 'Usuários do setor', "Setor #{$setorId}: {$resultado['message']}");

        $this->notificarEVoltar($resultado);
    }

    private function notificarEVoltar(array $resultado): void
    {
        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/whatsapp/setores'));
        exit;
    }
}
