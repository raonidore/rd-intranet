<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\WhatsAppAtendimentoService;
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
        $atendimentoService = new WhatsAppAtendimentoService();

        $emEspera = $atendimentoService->listarEmEsperaChatbot();

        // Só deixa selecionar (e ver a conversa inteira de) quem está
        // realmente na lista "em espera" agora -- evita vazar mensagens
        // de qualquer atendimento só forjando o id na URL.
        $emEsperaSelecionadoId = (int)($_GET['atendimento_id'] ?? 0);
        $emEsperaSelecionado = null;
        $emEsperaMensagens = [];

        foreach ($emEspera as $item) {
            if ((int)$item['id'] === $emEsperaSelecionadoId) {
                $emEsperaSelecionado = $item;
                $emEsperaMensagens = $atendimentoService->mensagens($emEsperaSelecionadoId);
                break;
            }
        }

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
            'emEspera' => $emEspera,
            'emEsperaSelecionado' => $emEsperaSelecionado,
            'emEsperaMensagens' => $emEsperaMensagens,
            'expedienteAtivo' => $config->expedienteAtivo(),
            'expedienteInicio' => $config->expedienteInicio(),
            'expedienteFim' => $config->expedienteFim(),
            'mensagemForaExpediente' => $config->mensagemForaExpediente(),
        ]);
    }

    /**
     * Atendente clica "Atender" numa conversa que ainda está com o bot --
     * intervém e assume na hora, mesmo padrão de WhatsAppFilaController::assumir().
     */
    public function atender(): void
    {
        AuthMiddleware::checkModulo('whatsapp_chatbot');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = (new WhatsAppAtendimentoService())->assumirDoBot($id, (int)$_SESSION['usuario']['id']);

        AuditService::registrar('WhatsApp', 'Assumir do chatbot', "Atendimento #{$id}: {$resultado['message']}");

        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
            header('Location: ' . url('/whatsapp/atendimentos/ver?id=' . $id));
            exit;
        }

        NotificationService::error($resultado['message']);
        header('Location: ' . url('/whatsapp/chatbot?aba=espera'));
        exit;
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

    public function salvarExpediente(): void
    {
        AuthMiddleware::checkModulo('whatsapp_chatbot');

        $resultado = (new WhatsAppConfigService())->salvarExpediente(
            isset($_POST['ativo']),
            trim($_POST['inicio'] ?? ''),
            trim($_POST['fim'] ?? ''),
            $_POST['mensagem'] ?? ''
        );

        AuditService::registrar('WhatsApp', 'Chatbot - expediente', $resultado['message']);

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
