<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\WhatsAppAtendimentoService;
use App\Services\WhatsAppBridgeService;

class WhatsAppAtendimentoController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::checkModulo('whatsapp_atendimentos');

        $this->view('whatsapp/atendimentos', [
            'atendimentos' => (new WhatsAppAtendimentoService())->listarDoUsuario((int)$_SESSION['usuario']['id']),
        ]);
    }

    public function ver(): void
    {
        AuthMiddleware::checkModulo('whatsapp_atendimentos');

        $id = (int)($_GET['id'] ?? 0);
        $service = new WhatsAppAtendimentoService();
        $atendimento = $service->buscarComContato($id);

        if (!$this->pertenceAoUsuarioLogado($atendimento)) {
            NotificationService::error('Atendimento não encontrado ou não é seu.');
            header('Location: ' . url('/whatsapp/atendimentos'));
            exit;
        }

        $this->view('whatsapp/atendimento_chat', [
            'atendimento' => $atendimento,
            'mensagens' => $service->mensagens($id),
        ]);
    }

    public function responder(): void
    {
        AuthMiddleware::checkModulo('whatsapp_atendimentos');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $texto = trim($_POST['texto'] ?? '');

        if ($texto === '') {
            echo json_encode(['success' => false, 'message' => 'Digite uma mensagem.']);
            return;
        }

        $service = new WhatsAppAtendimentoService();
        $atendimento = $service->buscarComContato($id);

        if (!$this->pertenceAoUsuarioLogado($atendimento)) {
            echo json_encode(['success' => false, 'message' => 'Atendimento não encontrado ou não é seu.']);
            return;
        }

        $envio = (new WhatsAppBridgeService())->enviar($atendimento['numero'], $texto);

        if (!$envio['success']) {
            echo json_encode(['success' => false, 'message' => $envio['message'] ?? 'Falha ao enviar mensagem pelo WhatsApp.']);
            return;
        }

        $service->registrarMensagemSaida($id, $texto, 'usuario', (int)$_SESSION['usuario']['id']);

        echo json_encode(['success' => true]);
    }

    public function mensagensApi(): void
    {
        AuthMiddleware::checkModulo('whatsapp_atendimentos');
        header('Content-Type: application/json');

        $id = (int)($_GET['id'] ?? 0);
        $desde = (int)($_GET['desde'] ?? 0);

        $service = new WhatsAppAtendimentoService();
        $atendimento = $service->buscar($id);

        if (!$this->pertenceAoUsuarioLogado($atendimento)) {
            echo json_encode(['success' => false, 'message' => 'Atendimento não encontrado ou não é seu.']);
            return;
        }

        echo json_encode(['success' => true, 'mensagens' => $service->mensagens($id, $desde)]);
    }

    public function encerrar(): void
    {
        AuthMiddleware::checkModulo('whatsapp_atendimentos');

        $id = (int)($_POST['id'] ?? 0);
        $service = new WhatsAppAtendimentoService();
        $atendimento = $service->buscar($id);

        if (!$this->pertenceAoUsuarioLogado($atendimento)) {
            NotificationService::error('Atendimento não encontrado ou não é seu.');
            header('Location: ' . url('/whatsapp/atendimentos'));
            exit;
        }

        $resultado = $service->encerrar($id);

        AuditService::registrar('WhatsApp', 'Encerrar atendimento', "Atendimento #{$id} encerrado.");

        NotificationService::success($resultado['message']);
        header('Location: ' . url('/whatsapp/atendimentos'));
        exit;
    }

    private function pertenceAoUsuarioLogado(?array $atendimento): bool
    {
        return $atendimento !== null
            && (int)($atendimento['usuario_id'] ?? 0) === (int)($_SESSION['usuario']['id'] ?? 0);
    }
}
