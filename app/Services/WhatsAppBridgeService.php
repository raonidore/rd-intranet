<?php

namespace App\Services;

/**
 * Cliente HTTP do bridge Node local (whatsapp-bridge/, instalado em
 * /opt/rdtecnologia/whatsapp-bridge por
 * scripts/system/whatsapp_bridge_instalar_web.sh) -- mesmo padrão de
 * chamada de KbService::chamarCentral(), mas contra 127.0.0.1 em vez de
 * um servidor externo.
 */
class WhatsAppBridgeService
{
    private WhatsAppConfigService $config;

    public function __construct()
    {
        $this->config = new WhatsAppConfigService();
    }

    public function status(): array
    {
        return $this->chamar('/status');
    }

    public function qrcode(): array
    {
        return $this->chamar('/qrcode');
    }

    public function enviar(string $numero, string $texto): array
    {
        return $this->chamar('/enviar', 'POST', ['numero' => $numero, 'texto' => $texto]);
    }

    public function desconectar(): array
    {
        return $this->chamar('/logout', 'POST');
    }

    private function chamar(string $caminho, string $metodo = 'GET', array $corpo = []): array
    {
        $ch = curl_init('http://127.0.0.1:' . $this->config->bridgePorta() . $caminho);

        $opcoes = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 3,
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
            return ['success' => false, 'message' => 'Bridge do WhatsApp não está respondendo -- confira se o serviço foi instalado e está rodando.'];
        }

        $dados = json_decode($resposta, true);

        if (!is_array($dados)) {
            return ['success' => false, 'message' => 'Resposta inesperada do bridge do WhatsApp.'];
        }

        return $dados;
    }
}
