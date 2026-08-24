<?php

namespace App\Services;

use RuntimeException;

/**
 * Config do chat-bridge (Fase 2 -- tempo real via WebSocket), mesmo
 * padrão do WhatsAppConfigService: porta fixa com default (sem scan de
 * porta livre, o sistema inteiro não faz isso em lugar nenhum), API
 * key gerada uma vez e guardada cifrada com CryptoService.
 */
class ChatConfigService
{
    private const CHAVE_BRIDGE_PORTA = 'chat_bridge_porta';
    private const PORTA_PADRAO = 3301;
    private const CHAVE_BRIDGE_API_KEY_CIFRADA = 'chat_bridge_api_key_cifrada';
    private const CHAVE_BRIDGE_INSTALADO = 'chat_bridge_instalado';

    public function bridgePorta(): int
    {
        return (int)(ConfigService::get(self::CHAVE_BRIDGE_PORTA, (string)self::PORTA_PADRAO) ?: self::PORTA_PADRAO);
    }

    public function bridgeApiKey(): string
    {
        $cifrada = ConfigService::get(self::CHAVE_BRIDGE_API_KEY_CIFRADA, '');

        if ($cifrada) {
            try {
                return CryptoService::decriptar($cifrada);
            } catch (RuntimeException $e) {
                // chave corrompida/ilegível -- gera outra
            }
        }

        $chave = bin2hex(random_bytes(24));
        ConfigService::set(self::CHAVE_BRIDGE_API_KEY_CIFRADA, CryptoService::encriptar($chave));

        return $chave;
    }

    public function bridgeInstalado(): bool
    {
        return ConfigService::get(self::CHAVE_BRIDGE_INSTALADO, '0') === '1';
    }

    public function marcarBridgeInstalado(): void
    {
        ConfigService::set(self::CHAVE_BRIDGE_INSTALADO, '1');
    }
}
