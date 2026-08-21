<?php

namespace App\Services;

use RuntimeException;

/**
 * Integração via API Oficial do WhatsApp (Meta Cloud API) -- diferente
 * do QR Code, é 100% HTTP/webhook, sem bridge nenhum: PHP manda mensagem
 * direto pra Graph API e recebe mensagem via webhook que a própria Meta
 * chama (configurado no painel do Meta for Developers com a URL de
 * WhatsAppWebhookController::verificarMeta()/receberMeta()).
 */
class WhatsAppApiOficialService
{
    private const CHAVE_PHONE_NUMBER_ID = 'whatsapp_meta_phone_number_id';
    private const CHAVE_ACCESS_TOKEN_CIFRADO = 'whatsapp_meta_access_token_cifrado';
    private const CHAVE_VERIFY_TOKEN = 'whatsapp_meta_verify_token';
    private const CHAVE_APP_SECRET_CIFRADO = 'whatsapp_meta_app_secret_cifrado';

    private const VERSAO_API = 'v20.0';

    public function phoneNumberId(): string
    {
        return trim((string)(ConfigService::get(self::CHAVE_PHONE_NUMBER_ID, '') ?: ''));
    }

    public function accessToken(): string
    {
        return $this->decriptarOuVazio(self::CHAVE_ACCESS_TOKEN_CIFRADO);
    }

    public function verifyToken(): string
    {
        return trim((string)(ConfigService::get(self::CHAVE_VERIFY_TOKEN, '') ?: ''));
    }

    /** Opcional -- se vazio, o webhook aceita sem validar assinatura (funciona, só menos seguro). */
    public function appSecret(): string
    {
        return $this->decriptarOuVazio(self::CHAVE_APP_SECRET_CIFRADO);
    }

    public function configurado(): bool
    {
        return $this->phoneNumberId() !== '' && $this->accessToken() !== '' && $this->verifyToken() !== '';
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function salvarConfig(string $phoneNumberId, string $accessToken, string $verifyToken, string $appSecret): array
    {
        $phoneNumberId = trim($phoneNumberId);
        $verifyToken = trim($verifyToken);
        $accessToken = trim($accessToken);
        $appSecret = trim($appSecret);

        if ($phoneNumberId === '' || $verifyToken === '') {
            return ['success' => false, 'message' => 'Preencha o Phone Number ID e o Verify Token.'];
        }

        ConfigService::set(self::CHAVE_PHONE_NUMBER_ID, $phoneNumberId);
        ConfigService::set(self::CHAVE_VERIFY_TOKEN, $verifyToken);

        if ($accessToken !== '') {
            ConfigService::set(self::CHAVE_ACCESS_TOKEN_CIFRADO, CryptoService::encriptar($accessToken));
        }

        if ($appSecret !== '') {
            ConfigService::set(self::CHAVE_APP_SECRET_CIFRADO, CryptoService::encriptar($appSecret));
        }

        if ($accessToken === '' && $this->accessToken() === '') {
            return ['success' => false, 'message' => 'Configuração salva, mas falta informar o Access Token pelo menos uma vez.'];
        }

        return ['success' => true, 'message' => 'Configuração da API Oficial salva.'];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function enviar(string $numero, string $texto): array
    {
        if (!$this->configurado()) {
            return ['success' => false, 'message' => 'API Oficial não configurada (Administração > Integrações > WhatsApp).'];
        }

        $numero = preg_replace('/\D+/', '', $numero) ?? '';

        $ch = curl_init('https://graph.facebook.com/' . self::VERSAO_API . '/' . $this->phoneNumberId() . '/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken(),
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'messaging_product' => 'whatsapp',
                'to' => $numero,
                'type' => 'text',
                'text' => ['body' => $texto],
            ]),
        ]);

        $resposta = curl_exec($ch);
        $erroConexao = curl_errno($ch) !== 0;
        $codigoHttp = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($erroConexao || $resposta === false) {
            return ['success' => false, 'message' => 'Falha de conexão com a API do WhatsApp (Meta).'];
        }

        $dados = json_decode($resposta, true);

        if ($codigoHttp >= 400) {
            $mensagemErro = $dados['error']['message'] ?? 'Erro desconhecido.';
            return ['success' => false, 'message' => "Falha ao enviar (Meta): {$mensagemErro}"];
        }

        return ['success' => true, 'message' => 'Mensagem enviada.'];
    }

    /**
     * Valida X-Hub-Signature-256 (HMAC-SHA256 do corpo bruto do POST com
     * o App Secret) -- confirma que o webhook realmente veio da Meta.
     * Sem App Secret configurado, aceita sem validar (o webhook segue
     * funcionando, só sem essa camada extra -- a Meta não exige o
     * secret pra funcionar, só recomenda).
     */
    public function assinaturaValida(string $corpoBruto, ?string $assinaturaRecebida): bool
    {
        $secret = $this->appSecret();

        if ($secret === '') {
            return true;
        }

        if ($assinaturaRecebida === null || !str_starts_with($assinaturaRecebida, 'sha256=')) {
            return false;
        }

        $esperada = 'sha256=' . hash_hmac('sha256', $corpoBruto, $secret);

        return hash_equals($esperada, $assinaturaRecebida);
    }

    private function decriptarOuVazio(string $chaveConfig): string
    {
        $cifrado = ConfigService::get($chaveConfig, '');

        if (!$cifrado) {
            return '';
        }

        try {
            return CryptoService::decriptar($cifrado);
        } catch (RuntimeException $e) {
            return '';
        }
    }
}
