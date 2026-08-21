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
}
