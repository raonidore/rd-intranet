<?php

namespace App\Services;

use RuntimeException;

/**
 * Integração via Twilio -- mesma ideia da API Oficial (100% HTTP/
 * webhook, sem bridge), mas usando a REST API da Twilio (Basic Auth
 * Account SID + Auth Token) e recebendo mensagem via webhook que a
 * própria Twilio chama (form-encoded, não JSON -- diferente da Meta),
 * configurado no console da Twilio com a URL de
 * WhatsAppWebhookController::receberTwilio().
 */
class WhatsAppTwilioService
{
    private const CHAVE_ACCOUNT_SID = 'whatsapp_twilio_account_sid';
    private const CHAVE_AUTH_TOKEN_CIFRADO = 'whatsapp_twilio_auth_token_cifrado';
    private const CHAVE_NUMERO = 'whatsapp_twilio_numero';

    public function accountSid(): string
    {
        return trim((string)(ConfigService::get(self::CHAVE_ACCOUNT_SID, '') ?: ''));
    }

    public function authToken(): string
    {
        $cifrado = ConfigService::get(self::CHAVE_AUTH_TOKEN_CIFRADO, '');

        if (!$cifrado) {
            return '';
        }

        try {
            return CryptoService::decriptar($cifrado);
        } catch (RuntimeException $e) {
            return '';
        }
    }

    /** Número do WhatsApp habilitado na Twilio, ex: +14155238886. */
    public function numero(): string
    {
        return trim((string)(ConfigService::get(self::CHAVE_NUMERO, '') ?: ''));
    }

    public function configurado(): bool
    {
        return $this->accountSid() !== '' && $this->authToken() !== '' && $this->numero() !== '';
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function salvarConfig(string $accountSid, string $authToken, string $numero): array
    {
        $accountSid = trim($accountSid);
        $numero = trim($numero);
        $authToken = trim($authToken);

        if ($accountSid === '' || $numero === '') {
            return ['success' => false, 'message' => 'Preencha o Account SID e o número do WhatsApp (Twilio).'];
        }

        ConfigService::set(self::CHAVE_ACCOUNT_SID, $accountSid);
        ConfigService::set(self::CHAVE_NUMERO, $numero);

        if ($authToken !== '') {
            ConfigService::set(self::CHAVE_AUTH_TOKEN_CIFRADO, CryptoService::encriptar($authToken));
        }

        if ($authToken === '' && $this->authToken() === '') {
            return ['success' => false, 'message' => 'Configuração salva, mas falta informar o Auth Token pelo menos uma vez.'];
        }

        return ['success' => true, 'message' => 'Configuração do Twilio salva.'];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function enviar(string $numero, string $texto): array
    {
        if (!$this->configurado()) {
            return ['success' => false, 'message' => 'Twilio não configurado (Administração > Integrações > WhatsApp).'];
        }

        $numero = preg_replace('/\D+/', '', $numero) ?? '';

        $ch = curl_init('https://api.twilio.com/2010-04-01/Accounts/' . $this->accountSid() . '/Messages.json');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_POST => true,
            CURLOPT_USERPWD => $this->accountSid() . ':' . $this->authToken(),
            CURLOPT_POSTFIELDS => http_build_query([
                'From' => $this->formatarWhatsapp($this->numero()),
                'To' => 'whatsapp:+' . $numero,
                'Body' => $texto,
            ]),
        ]);

        $resposta = curl_exec($ch);
        $erroConexao = curl_errno($ch) !== 0;
        $codigoHttp = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($erroConexao || $resposta === false) {
            return ['success' => false, 'message' => 'Falha de conexão com o Twilio.'];
        }

        $dados = json_decode($resposta, true);

        if ($codigoHttp >= 400) {
            $mensagemErro = $dados['message'] ?? 'Erro desconhecido.';
            return ['success' => false, 'message' => "Falha ao enviar (Twilio): {$mensagemErro}"];
        }

        return ['success' => true, 'message' => 'Mensagem enviada.'];
    }

    /**
     * Valida X-Twilio-Signature -- HMAC-SHA1 (base64) da URL completa
     * concatenada com os parâmetros do POST ordenados alfabeticamente
     * (chave+valor, sem separador), conforme o algoritmo documentado
     * pela Twilio pra validação de webhook.
     */
    public function assinaturaValida(string $urlCompleta, array $parametrosPost, ?string $assinaturaRecebida): bool
    {
        if ($assinaturaRecebida === null || $this->authToken() === '') {
            return false;
        }

        $dados = $urlCompleta;
        ksort($parametrosPost);

        foreach ($parametrosPost as $chave => $valor) {
            $dados .= $chave . $valor;
        }

        $esperada = base64_encode(hash_hmac('sha1', $dados, $this->authToken(), true));

        return hash_equals($esperada, $assinaturaRecebida);
    }

    private function formatarWhatsapp(string $numero): string
    {
        if (str_starts_with($numero, 'whatsapp:')) {
            return $numero;
        }

        return 'whatsapp:' . (str_starts_with($numero, '+') ? $numero : '+' . $numero);
    }
}
