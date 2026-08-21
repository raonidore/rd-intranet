<?php

namespace App\Services;

use RuntimeException;

/**
 * Config do módulo WhatsApp (Sistema > Integrações > WhatsApp) -- mesmo
 * esquema key-value via ConfigService já usado pelo EmailService, chave
 * do bridge cifrada com CryptoService igual à senha SMTP.
 */
class WhatsAppConfigService
{
    private const CHAVE_TIPO = 'whatsapp_tipo_integracao';
    private const CHAVE_BRIDGE_PORTA = 'whatsapp_bridge_porta';
    private const CHAVE_BRIDGE_API_KEY_CIFRADA = 'whatsapp_bridge_api_key_cifrada';
    private const CHAVE_BRIDGE_INSTALADO = 'whatsapp_bridge_instalado';

    public const TIPOS_VALIDOS = ['qrcode', 'api_oficial', 'twilio'];
    public const TIPOS_DISPONIVEIS = ['qrcode'];

    private const PORTA_PADRAO = 3300;

    public function tipoIntegracao(): string
    {
        $tipo = ConfigService::get(self::CHAVE_TIPO, 'qrcode') ?: 'qrcode';

        return in_array($tipo, self::TIPOS_VALIDOS, true) ? $tipo : 'qrcode';
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function salvarTipoIntegracao(string $tipo): array
    {
        if (!in_array($tipo, self::TIPOS_VALIDOS, true)) {
            return ['success' => false, 'message' => 'Tipo de integração inválido.'];
        }

        if (!in_array($tipo, self::TIPOS_DISPONIVEIS, true)) {
            return ['success' => false, 'message' => 'Esse tipo de integração ainda não está disponível -- só QR Code por enquanto.'];
        }

        ConfigService::set(self::CHAVE_TIPO, $tipo);

        return ['success' => true, 'message' => 'Tipo de integração salvo.'];
    }

    public function bridgeInstalado(): bool
    {
        return ConfigService::get(self::CHAVE_BRIDGE_INSTALADO, '') === '1';
    }

    public function marcarBridgeInstalado(): void
    {
        ConfigService::set(self::CHAVE_BRIDGE_INSTALADO, '1');
    }

    public function bridgePorta(): int
    {
        return (int)(ConfigService::get(self::CHAVE_BRIDGE_PORTA, (string)self::PORTA_PADRAO) ?: self::PORTA_PADRAO);
    }

    /**
     * Chave compartilhada entre o PHP e o bridge Node local -- gerada na
     * primeira vez que é pedida (instalação) e reaproveitada depois
     * (trocar a cada request quebraria a autenticação do bridge já
     * rodando). Cifrada em repouso, igual à senha SMTP do EmailService.
     */
    public function bridgeApiKey(): string
    {
        $cifrada = ConfigService::get(self::CHAVE_BRIDGE_API_KEY_CIFRADA, '');

        if ($cifrada) {
            try {
                return CryptoService::decriptar($cifrada);
            } catch (RuntimeException $e) {
                // chave corrompida/ilegível -- gera outra (bridge vai
                // precisar ser reinstalado/reautenticado de qualquer forma)
            }
        }

        $chave = bin2hex(random_bytes(24));
        ConfigService::set(self::CHAVE_BRIDGE_API_KEY_CIFRADA, CryptoService::encriptar($chave));

        return $chave;
    }
}
