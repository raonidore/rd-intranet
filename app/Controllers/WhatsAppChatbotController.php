<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\WhatsAppChatbotService;
use App\Services\WhatsAppConfigService;
use App\Services\WhatsAppMensagemRapidaService;
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

        $raiz = $this->service->raiz();
        $noAtual = null;
        $caminho = [];
        $opcoes = [];

        if ($raiz) {
            $noPaiId = (int)($_GET['no_pai_id'] ?? $raiz['id']);
            $noAtual = $this->service->no($noPaiId) ?? $raiz;
            $caminho = $this->service->caminhoAteRaiz((int)$noAtual['id']);
            $opcoes = $this->service->filhos((int)$noAtual['id']);
        }

        $setoresAtivos = array_values(array_filter(
            (new WhatsAppSetorService())->listar(),
            fn (array $s) => (bool)$s['ativo']
        ));

        $config = new WhatsAppConfigService();

        $this->view('whatsapp/chatbot', [
            'aba' => $_GET['aba'] ?? 'fluxo',
            'raiz' => $raiz,
            'noAtual' => $noAtual,
            'caminho' => $caminho,
            'opcoes' => $opcoes,
            'setoresAtivos' => $setoresAtivos,
            'timeoutMinutos' => $config->timeoutMinutos(),
            'encerramentoNormal' => $config->encerramentoNormal(),
            'encerramentoInatividade' => $config->encerramentoInatividade(),
            'mensagensRapidas' => (new WhatsAppMensagemRapidaService())->listar(),
        ]);
    }

    public function salvarRaiz(): void
    {
        AuthMiddleware::checkModulo('whatsapp_chatbot');

        $resultado = $this->service->salvarRaiz($_POST['mensagem'] ?? '', isset($_POST['ativo']));

        AuditService::registrar('WhatsApp', 'Chatbot - boas-vindas', $resultado['message']);

        $this->notificarEVoltar($resultado, ['aba' => 'fluxo']);
    }

    public function salvarOpcoes(): void
    {
        AuthMiddleware::checkModulo('whatsapp_chatbot');

        $noPaiId = (int)($_POST['no_pai_id'] ?? 0);

        $rotulos = $_POST['rotulo'] ?? [];
        $tipos = $_POST['tipo'] ?? [];
        $setores = $_POST['setor_destino_id'] ?? [];
        $mensagens = $_POST['mensagem'] ?? [];
        $ids = $_POST['id'] ?? [];

        $linhas = [];
        foreach ($rotulos as $indice => $rotulo) {
            $linhas[] = [
                'id' => $ids[$indice] ?? null,
                'rotulo' => (string)$rotulo,
                'tipo' => (string)($tipos[$indice] ?? ''),
                'setor_destino_id' => $setores[$indice] ?? null,
                'mensagem' => (string)($mensagens[$indice] ?? ''),
            ];
        }

        $resultado = $this->service->salvarOpcoes($noPaiId, $linhas);

        AuditService::registrar('WhatsApp', 'Chatbot - fluxo', "Nível #{$noPaiId}: {$resultado['message']}");

        $this->notificarEVoltar($resultado, ['aba' => 'fluxo', 'no_pai_id' => $noPaiId]);
    }

    public function salvarFinalizacao(): void
    {
        AuthMiddleware::checkModulo('whatsapp_chatbot');

        $resultado = (new WhatsAppConfigService())->salvarFinalizacao(
            (int)($_POST['timeout_minutos'] ?? 0),
            $_POST['encerramento_normal'] ?? '',
            $_POST['encerramento_inatividade'] ?? ''
        );

        AuditService::registrar('WhatsApp', 'Chatbot - finalização', $resultado['message']);

        $this->notificarEVoltar($resultado, ['aba' => 'finalizacao']);
    }

    private function notificarEVoltar(array $resultado, array $query): void
    {
        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/whatsapp/chatbot') . '?' . http_build_query($query));
        exit;
    }
}
