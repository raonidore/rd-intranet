<?php

namespace App\Services;

/**
 * Ponto único de envio de mensagem, independente do tipo de integração
 * ativo -- quem manda mensagem (WhatsAppAtendimentoController::responder(),
 * WhatsAppChatbotService) chama só isso, nunca WhatsAppBridgeService/
 * WhatsAppApiOficialService/WhatsAppTwilioService diretamente. Trocar o
 * tipo de integração em Administração > Integrações não exige mexer em
 * mais nada.
 *
 * `$conexaoId` só importa pro tipo 'qrcode' (múltiplas conexões
 * possíveis, uma por número/departamento) -- api_oficial/twilio
 * continuam singleton, o parâmetro é ignorado nesses casos. `null` (o
 * default -- resposta avulsa, sem atendimento em andamento) resolve pra
 * conexão marcada `padrao` em `whatsapp_conexoes`; se ela não existir
 * (estado inconsistente), a mensagem falha explicitamente em vez de
 * escolher uma conexão qualquer e mandar pelo número errado.
 */
class WhatsAppMensagemService
{
    /**
     * @return array{success: bool, message: string}
     */
    public function enviar(string $numero, string $texto, ?int $conexaoId = null): array
    {
        return match ((new WhatsAppConfigService())->tipoIntegracao()) {
            'api_oficial' => (new WhatsAppApiOficialService())->enviar($numero, $texto),
            'twilio' => (new WhatsAppTwilioService())->enviar($numero, $texto),
            default => $this->comConexao($conexaoId, fn (array $conexao) => (new WhatsAppBridgeService($conexao))->enviar($numero, $texto)),
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
    public function enviarMidia(string $numero, string $caminhoArquivo, string $mimetype, string $tipoMidia, ?string $legenda, ?string $nomeArquivo, ?int $conexaoId = null): array
    {
        return match ((new WhatsAppConfigService())->tipoIntegracao()) {
            'api_oficial', 'twilio' => ['success' => false, 'message' => 'Envio de anexo ainda não é suportado nesse tipo de integração -- só via QR Code, por enquanto.'],
            default => $this->comConexao(
                $conexaoId,
                fn (array $conexao) => (new WhatsAppBridgeService($conexao))->enviarMidia($numero, $caminhoArquivo, $mimetype, $tipoMidia, $legenda, $nomeArquivo)
            ),
        };
    }

    /** @param callable(array): array{success: bool, message: string} $acao */
    private function comConexao(?int $conexaoId, callable $acao): array
    {
        $servico = new WhatsAppConexaoService();
        $conexao = $conexaoId !== null ? $servico->buscar($conexaoId) : $servico->conexaoPadrao();

        if (!$conexao) {
            return ['success' => false, 'message' => 'Nenhuma conexão de WhatsApp válida encontrada pra mandar essa mensagem.'];
        }

        return $acao($conexao);
    }
}
