<?php

namespace App\Services;

/**
 * Ponto único de envio de mensagem, independente do tipo de integração
 * ativo -- quem manda mensagem (WhatsAppAtendimentoController::responder(),
 * WhatsAppChatbotService) chama só isso, nunca WhatsAppBridgeService/
 * WhatsAppApiOficialService/WhatsAppTwilioService diretamente. Trocar o
 * tipo de integração em Administração > Integrações não exige mexer em
 * mais nada.
 */
class WhatsAppMensagemService
{
    /**
     * @return array{success: bool, message: string}
     */
    public function enviar(string $numero, string $texto): array
    {
        return match ((new WhatsAppConfigService())->tipoIntegracao()) {
            'api_oficial' => (new WhatsAppApiOficialService())->enviar($numero, $texto),
            'twilio' => (new WhatsAppTwilioService())->enviar($numero, $texto),
            default => (new WhatsAppBridgeService())->enviar($numero, $texto),
        };
    }

    /**
     * Anexo (imagem/áudio/documento) -- só implementado pra QR Code
     * (bridge) por enquanto; API Oficial e Twilio têm fluxos de mídia
     * próprios e bem diferentes (upload prévio num endpoint deles),
     * fica pra quando alguém realmente usar um desses dois em produção.
     *
     * @return array{success: bool, message: string}
     */
    public function enviarMidia(string $numero, string $caminhoArquivo, string $mimetype, string $tipoMidia, ?string $legenda, ?string $nomeArquivo): array
    {
        return match ((new WhatsAppConfigService())->tipoIntegracao()) {
            'api_oficial', 'twilio' => ['success' => false, 'message' => 'Envio de anexo ainda não é suportado nesse tipo de integração -- só via QR Code, por enquanto.'],
            default => (new WhatsAppBridgeService())->enviarMidia($numero, $caminhoArquivo, $mimetype, $tipoMidia, $legenda, $nomeArquivo),
        };
    }
}
