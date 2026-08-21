<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\WhatsAppAtendimentoService;
use App\Services\WhatsAppChatbotService;
use App\Services\WhatsAppConfigService;
use App\Services\WhatsAppContatoService;

/**
 * Recebe do bridge Node local (whatsapp-bridge/) as mensagens que
 * chegaram no WhatsApp -- autenticado por chave compartilhada (mesmo
 * esquema de AtivoAgenteController::checkin() pro agente Windows),
 * nunca por sessão de usuário logado, já que quem chama é um processo
 * local, não um navegador.
 */
class WhatsAppWebhookController extends Controller
{
    public function receber(): void
    {
        header('Content-Type: application/json');

        $config = new WhatsAppConfigService();
        $chaveRecebida = $_SERVER['HTTP_X_API_KEY'] ?? '';

        if (!hash_equals($config->bridgeApiKey(), $chaveRecebida)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Chave inválida.']);
            return;
        }

        $corpo = json_decode(file_get_contents('php://input') ?: '', true);

        if (!is_array($corpo)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Corpo inválido.']);
            return;
        }

        $numero = trim((string)($corpo['numero'] ?? ''));

        if ($numero === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Número não informado.']);
            return;
        }

        $nomeContato = trim((string)($corpo['nome'] ?? '')) ?: null;
        $texto = (string)($corpo['texto'] ?? '');
        $tipo = (string)($corpo['tipo'] ?? 'texto');
        $whatsappMessageId = $corpo['id_mensagem'] ?? null;
        $whatsappMessageId = $whatsappMessageId !== null ? (string)$whatsappMessageId : null;

        $contato = (new WhatsAppContatoService())->buscarOuCriarPorNumero($numero, $nomeContato);

        $atendimentoService = new WhatsAppAtendimentoService();
        $atendimento = $atendimentoService->abrirOuReaproveitar((int)$contato['id']);

        $resultado = $atendimentoService->registrarMensagemEntrada((int)$atendimento['id'], $texto, $tipo, $whatsappMessageId);

        // $resultado === null quer dizer "já tinha essa mensagem" (retry
        // do bridge) -- não roda o bot de novo pra não mandar a mesma
        // resposta duplicada. Bot só reage a texto (mídia é só logada
        // por enquanto, ver comentário equivalente em whatsapp-bridge/index.js).
        if ($resultado !== null && $atendimento['status'] === 'bot' && $tipo === 'texto') {
            (new WhatsAppChatbotService())->processarMensagem($atendimento, $numero, $texto);
        }

        echo json_encode(['success' => true]);
    }
}
