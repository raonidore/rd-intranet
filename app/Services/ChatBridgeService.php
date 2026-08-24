<?php

namespace App\Services;

/**
 * Cliente HTTP do chat-bridge Node local (chat-bridge/, instalado em
 * /opt/rdtecnologia/chat-bridge por scripts/system/chat_bridge_instalar_web.sh)
 * -- mesmo padrão de App\Services\WhatsAppBridgeService, contra
 * 127.0.0.1 e sempre "fire and forget": se o bridge não estiver
 * instalado/rodando, a mensagem já foi salva no banco pelo ChatService
 * antes de chegar aqui -- só o empurrão em tempo real deixa de
 * acontecer, o polling de sempre continua entregando em até 3s.
 */
class ChatBridgeService
{
    private ChatConfigService $config;

    public function __construct()
    {
        $this->config = new ChatConfigService();
    }

    public function status(): array
    {
        return $this->chamar('/status');
    }

    /**
     * Avisa o bridge pra empurrar um evento, via WebSocket, pra quem
     * estiver com socket aberto entre os usuarioIds informados --
     * silencioso em qualquer falha (bridge fora do ar, não instalado,
     * timeout), nunca deixa a requisição que chamou isso mais lenta que
     * o timeout curto abaixo.
     *
     * @param int[] $usuarioIds
     */
    public function notificar(array $usuarioIds, string $evento, array $dados): void
    {
        if (empty($usuarioIds)) {
            return;
        }

        $this->chamar('/notificar', 'POST', [
            'usuarioIds' => array_values(array_map('intval', $usuarioIds)),
            'evento' => $evento,
            'dados' => $dados,
        ], 3);
    }

    private function chamar(string $caminho, string $metodo = 'GET', array $corpo = [], int $timeout = 5): array
    {
        $ch = curl_init('http://127.0.0.1:' . $this->config->bridgePorta() . $caminho);

        $opcoes = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_HTTPHEADER => [
                'X-Api-Key: ' . $this->config->bridgeApiKey(),
                'Content-Type: application/json',
            ],
        ];

        if ($metodo === 'POST') {
            $opcoes[CURLOPT_POST] = true;
            $opcoes[CURLOPT_POSTFIELDS] = json_encode($corpo);
        }

        curl_setopt_array($ch, $opcoes);
        $resposta = curl_exec($ch);
        $erroConexao = curl_errno($ch) !== 0;
        curl_close($ch);

        if ($erroConexao || $resposta === false) {
            return ['success' => false, 'message' => 'Chat-bridge não está respondendo.'];
        }

        $dados = json_decode($resposta, true);

        return is_array($dados) ? $dados : ['success' => false, 'message' => 'Resposta inesperada do chat-bridge.'];
    }
}
