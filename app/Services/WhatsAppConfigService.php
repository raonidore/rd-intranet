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
    public const TIPOS_DISPONIVEIS = ['qrcode', 'api_oficial', 'twilio'];

    private const PORTA_PADRAO = 3300;

    private const CHAVE_TIMEOUT_MINUTOS = 'whatsapp_chatbot_timeout_minutos';
    private const CHAVE_ENCERRAMENTO_NORMAL = 'whatsapp_chatbot_encerramento_normal';
    private const CHAVE_ENCERRAMENTO_INATIVIDADE = 'whatsapp_chatbot_encerramento_inatividade';

    private const TIMEOUT_MINUTOS_PADRAO = 40;
    private const ENCERRAMENTO_NORMAL_PADRAO = 'Atendimento finalizado. Qualquer dúvida, é só chamar novamente!';
    private const ENCERRAMENTO_INATIVIDADE_PADRAO = 'Este atendimento foi encerrado automaticamente por falta de interação. Caso precise de algo, é só mandar uma mensagem que a gente recomeça.';

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
            return ['success' => false, 'message' => 'Esse tipo de integração ainda não está disponível.'];
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

    public function timeoutMinutos(): int
    {
        $valor = (int)(ConfigService::get(self::CHAVE_TIMEOUT_MINUTOS, (string)self::TIMEOUT_MINUTOS_PADRAO) ?: self::TIMEOUT_MINUTOS_PADRAO);

        return $valor > 0 ? $valor : self::TIMEOUT_MINUTOS_PADRAO;
    }

    public function encerramentoNormal(): string
    {
        return (string)(ConfigService::get(self::CHAVE_ENCERRAMENTO_NORMAL, self::ENCERRAMENTO_NORMAL_PADRAO) ?: self::ENCERRAMENTO_NORMAL_PADRAO);
    }

    public function encerramentoInatividade(): string
    {
        return (string)(ConfigService::get(self::CHAVE_ENCERRAMENTO_INATIVIDADE, self::ENCERRAMENTO_INATIVIDADE_PADRAO) ?: self::ENCERRAMENTO_INATIVIDADE_PADRAO);
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function salvarFinalizacao(int $timeoutMinutos, string $encerramentoNormal, string $encerramentoInatividade): array
    {
        if ($timeoutMinutos < 5 || $timeoutMinutos > 1440) {
            return ['success' => false, 'message' => 'Informe um tempo de inatividade entre 5 e 1440 minutos.'];
        }

        $encerramentoNormal = trim($encerramentoNormal);
        $encerramentoInatividade = trim($encerramentoInatividade);

        if ($encerramentoNormal === '' || $encerramentoInatividade === '') {
            return ['success' => false, 'message' => 'Preencha as duas mensagens de encerramento.'];
        }

        ConfigService::set(self::CHAVE_TIMEOUT_MINUTOS, (string)$timeoutMinutos);
        ConfigService::set(self::CHAVE_ENCERRAMENTO_NORMAL, $encerramentoNormal);
        ConfigService::set(self::CHAVE_ENCERRAMENTO_INATIVIDADE, $encerramentoInatividade);

        return ['success' => true, 'message' => 'Configuração de finalização salva.'];
    }
}
