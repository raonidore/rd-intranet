<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\WhatsAppApiOficialService;
use App\Services\WhatsAppAtendimentoService;
use App\Services\WhatsAppConexaoService;
use App\Services\WhatsAppConfigService;
use App\Services\WhatsAppTwilioService;
use PDOException;

/**
 * Recebe mensagem nova de qualquer um dos 3 canais possíveis -- cada um
 * com seu próprio formato/autenticação, mas todos terminando no mesmo
 * WhatsAppAtendimentoService::receberMensagem():
 *
 * - receber(): bridge Node local (QR Code) -- chave compartilhada em
 *   X-Api-Key, mesmo esquema de AtivoAgenteController::checkin() pro
 *   agente Windows.
 * - verificarMeta()/receberMeta(): API Oficial (Meta Cloud API) --
 *   handshake de verificação (GET) + assinatura X-Hub-Signature-256
 *   opcional (só se o App Secret estiver configurado).
 * - receberTwilio(): Twilio -- form-encoded (não JSON!) + assinatura
 *   X-Twilio-Signature.
 *
 * Nenhum desses três é autenticado por sessão de usuário logado --
 * quem chama é sempre um processo/servidor externo, não um navegador.
 */
class WhatsAppWebhookController extends Controller
{
    public function receber(): void
    {
        header('Content-Type: application/json');

        $config = new WhatsAppConfigService();
        $chaveRecebida = $_SERVER['HTTP_X_API_KEY'] ?? '';

        // Autenticação em 2 camadas, as duas permanentes (não é só transição):
        // 1) identifica de qual conexão veio pela própria API key (poucas
        //    linhas, comparação segura em WhatsAppConexaoService::buscarPorApiKey());
        // 2) se não achou -- inclusive se `whatsapp_conexoes` ainda não existir,
        //    janela normal de deploy neste projeto (código novo sobe ANTES da
        //    migration rodar) -- cai pra chave global antiga. Sem isso, uma
        //    mensagem chegando nessa janela seria perdida de vez: o bridge Node
        //    não tem retry nenhum no webhook (avisarWebhook() só loga erro).
        $conexaoId = null;

        try {
            $conexao = (new WhatsAppConexaoService())->buscarPorApiKey($chaveRecebida);
            if ($conexao) {
                $conexaoId = (int)$conexao['id'];
            }
        } catch (PDOException $e) {
            // tabela ainda não existe -- segue pro fallback abaixo
        }

        if ($conexaoId === null && !hash_equals($config->bridgeApiKey(), $chaveRecebida)) {
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
        $midiaBase64 = isset($corpo['midia_base64']) ? (string)$corpo['midia_base64'] : null;
        $midiaMimetype = isset($corpo['midia_mimetype']) ? (string)$corpo['midia_mimetype'] : null;

        (new WhatsAppAtendimentoService())->receberMensagem($numero, $nomeContato, $texto, $tipo, $whatsappMessageId, $midiaBase64, $midiaMimetype, $conexaoId);

        echo json_encode(['success' => true]);
    }

    /**
     * Handshake de verificação que a Meta faz uma vez (GET), na hora que
     * você cola a URL do webhook no painel do Meta for Developers --
     * precisa devolver exatamente o "hub_challenge" recebido, em texto
     * puro, se o "hub_verify_token" bater com o configurado.
     */
    public function verificarMeta(): void
    {
        $modo = $_GET['hub_mode'] ?? '';
        $tokenRecebido = (string)($_GET['hub_verify_token'] ?? '');
        $desafio = (string)($_GET['hub_challenge'] ?? '');

        $apiOficial = new WhatsAppApiOficialService();

        if ($modo === 'subscribe' && $apiOficial->verifyToken() !== '' && hash_equals($apiOficial->verifyToken(), $tokenRecebido)) {
            echo $desafio;
            return;
        }

        http_response_code(403);
        echo 'Forbidden';
    }

    public function receberMeta(): void
    {
        header('Content-Type: application/json');

        $corpoBruto = file_get_contents('php://input') ?: '';
        $assinatura = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? null;

        if (!(new WhatsAppApiOficialService())->assinaturaValida($corpoBruto, $assinatura)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Assinatura inválida.']);
            return;
        }

        $corpo = json_decode($corpoBruto, true);
        $mensagens = $corpo['entry'][0]['changes'][0]['value']['messages'] ?? null;

        if (!is_array($mensagens)) {
            // callback de status (entregue/lido) ou payload sem mensagem
            // nenhuma -- não é erro, só não tem nada pra processar aqui
            echo json_encode(['success' => true]);
            return;
        }

        $contatos = $corpo['entry'][0]['changes'][0]['value']['contacts'] ?? [];
        $nomeContato = $contatos[0]['profile']['name'] ?? null;

        $atendimentoService = new WhatsAppAtendimentoService();

        foreach ($mensagens as $msg) {
            if (($msg['type'] ?? '') !== 'text') {
                continue; // mídia fica pra uma próxima fase (mesma limitação dos outros 2 canais)
            }

            $numero = trim((string)($msg['from'] ?? ''));
            $texto = (string)($msg['text']['body'] ?? '');
            $idMensagem = trim((string)($msg['id'] ?? '')) ?: null;

            if ($numero === '' || $texto === '') {
                continue;
            }

            $atendimentoService->receberMensagem($numero, $nomeContato, $texto, 'texto', $idMensagem);
        }

        echo json_encode(['success' => true]);
    }

    public function receberTwilio(): void
    {
        $twilio = new WhatsAppTwilioService();

        $esquema = ($_SERVER['HTTPS'] ?? '') === 'on' ? 'https' : 'http';
        $urlCompleta = $esquema . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
        $assinatura = $_SERVER['HTTP_X_TWILIO_SIGNATURE'] ?? null;

        header('Content-Type: text/xml');

        if (!$twilio->assinaturaValida($urlCompleta, $_POST, $assinatura)) {
            http_response_code(403);
            echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
            return;
        }

        $numero = preg_replace('/\D+/', '', (string)($_POST['From'] ?? '')) ?? '';
        $texto = trim((string)($_POST['Body'] ?? ''));
        $idMensagem = trim((string)($_POST['MessageSid'] ?? '')) ?: null;

        if ($numero !== '' && $texto !== '') {
            (new WhatsAppAtendimentoService())->receberMensagem($numero, null, $texto, 'texto', $idMensagem);
        }

        // TwiML vazio -- respostas são mandadas depois, assíncronas, via
        // WhatsAppMensagemService/REST API, não inline neste retorno.
        echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
    }
}
