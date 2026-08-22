<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\WhatsAppMensagemRapidaService;

class WhatsAppMensagemRapidaController extends Controller
{
    private WhatsAppMensagemRapidaService $service;

    public function __construct()
    {
        $this->service = new WhatsAppMensagemRapidaService();
    }

    public function criar(): void
    {
        AuthMiddleware::checkModulo('whatsapp_chatbot');

        $resultado = $this->service->criar($_POST['comando'] ?? '', $_POST['mensagem'] ?? '');

        AuditService::registrar('WhatsApp', 'Mensagem rápida criada', $resultado['message']);

        $this->notificarEVoltar($resultado);
    }

    public function atualizar(): void
    {
        AuthMiddleware::checkModulo('whatsapp_chatbot');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->atualizar($id, $_POST['comando'] ?? '', $_POST['mensagem'] ?? '');

        AuditService::registrar('WhatsApp', 'Mensagem rápida atualizada', "#{$id}: {$resultado['message']}");

        $this->notificarEVoltar($resultado);
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('whatsapp_chatbot');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->excluir($id);

        AuditService::registrar('WhatsApp', 'Mensagem rápida removida', "#{$id} removida.");

        $this->notificarEVoltar($resultado);
    }

    /**
     * Usado pelo autocomplete de "/" na caixa de resposta do chat --
     * mesmo módulo/permissão do atendimento em si, não do chatbot
     * (quem atende precisa ver, não só quem configura o bot).
     */
    public function buscar(): void
    {
        AuthMiddleware::checkModulo('whatsapp_atendimentos');
        header('Content-Type: application/json');

        echo json_encode(['success' => true, 'mensagens' => $this->service->listar()]);
    }

    private function notificarEVoltar(array $resultado): void
    {
        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/whatsapp/chatbot') . '?aba=mensagens-rapidas');
        exit;
    }
}
