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

    /**
     * Anexo (imagem/áudio/documento) -- manda o arquivo em base64 no
     * corpo (bridge roda como outro usuário de sistema, num diretório
     * diferente do app; é mais simples transportar os bytes pela
     * própria chamada HTTP local do que tentar compartilhar caminho de
     * disco entre processos com dono diferente). Timeout maior que o
     * padrão porque codificar+mandar+o bridge subir pro WhatsApp pode
     * levar mais que os 10s normais de uma mensagem de texto.
     *
     * @return array{success: bool, message: string}
     */
    public function enviarMidia(string $numero, string $caminhoArquivo, string $mimetype, string $tipoMidia, ?string $legenda, ?string $nomeArquivo): array
    {
        if (!is_file($caminhoArquivo)) {
            return ['success' => false, 'message' => 'Arquivo não encontrado.'];
        }

        $base64 = base64_encode((string)file_get_contents($caminhoArquivo));

        return $this->chamar('/enviar', 'POST', [
            'numero' => $numero,
            'midia_base64' => $base64,
            'midia_mimetype' => $mimetype,
            'midia_tipo' => $tipoMidia,
            'legenda' => $legenda,
            'nome_arquivo' => $nomeArquivo,
        ], 60);
    }

    public function desconectar(): array
    {
        return $this->chamar('/logout', 'POST');
    }

    private function chamar(string $caminho, string $metodo = 'GET', array $corpo = [], int $timeout = 10): array
    {
        $ch = curl_init('http://127.0.0.1:' . $this->config->bridgePorta() . $caminho);

        $opcoes = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
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
