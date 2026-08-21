<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\WhatsAppChatbotService;
use App\Services\WhatsAppSetorService;

class WhatsAppChatbotController extends Controller
{
    private WhatsAppChatbotService $service;

    public function __construct()
    {
        $this->service = new WhatsAppChatbotService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('whatsapp_chatbot');

        $setoresAtivos = array_values(array_filter(
            (new WhatsAppSetorService())->listar(),
            fn (array $s) => (bool)$s['ativo']
        ));

        $this->view('whatsapp/chatbot', [
            'raiz' => $this->service->raiz(),
            'opcoes' => $this->service->arvoreDeOpcoes(),
            'setoresAtivos' => $setoresAtivos,
        ]);
    }

    public function salvarRaiz(): void
    {
        AuthMiddleware::checkModulo('whatsapp_chatbot');

        $resultado = $this->service->salvarRaiz($_POST['mensagem'] ?? '', isset($_POST['ativo']));

        AuditService::registrar('WhatsApp', 'Chatbot - boas-vindas', $resultado['message']);

        $this->notificarEVoltar($resultado);
    }

    public function criar(): void
    {
        AuthMiddleware::checkModulo('whatsapp_chatbot');

        $noPaiId = (int)($_POST['no_pai_id'] ?? 0);
        $setorDestinoId = !empty($_POST['setor_destino_id']) ? (int)$_POST['setor_destino_id'] : null;

        $resultado = $this->service->criarNo(
            $noPaiId,
            $_POST['rotulo'] ?? '',
            $_POST['mensagem'] ?? '',
            $_POST['tipo'] ?? '',
            $setorDestinoId
        );

        AuditService::registrar('WhatsApp', 'Chatbot - nova opção', $resultado['message']);

        $this->notificarEVoltar($resultado);
    }

    public function atualizar(): void
    {
        AuthMiddleware::checkModulo('whatsapp_chatbot');

        $id = (int)($_POST['id'] ?? 0);
        $setorDestinoId = !empty($_POST['setor_destino_id']) ? (int)$_POST['setor_destino_id'] : null;

        $resultado = $this->service->atualizarNo(
            $id,
            $_POST['rotulo'] ?? '',
            $_POST['mensagem'] ?? '',
            $_POST['tipo'] ?? '',
            $setorDestinoId,
            isset($_POST['ativo'])
        );

        AuditService::registrar('WhatsApp', 'Chatbot - opção atualizada', "Opção #{$id}: {$resultado['message']}");

        $this->notificarEVoltar($resultado);
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('whatsapp_chatbot');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->excluirNo($id);

        AuditService::registrar('WhatsApp', 'Chatbot - opção removida', "Opção #{$id} removida.");

        $this->notificarEVoltar($resultado);
    }

    private function notificarEVoltar(array $resultado): void
    {
        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/whatsapp/chatbot'));
        exit;
    }
}
