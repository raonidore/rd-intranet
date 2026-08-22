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

    private const CHAVE_EXPEDIENTE_ATIVO = 'whatsapp_expediente_ativo';
    private const CHAVE_EXPEDIENTE_INICIO = 'whatsapp_expediente_inicio';
    private const CHAVE_EXPEDIENTE_FIM = 'whatsapp_expediente_fim';
    private const CHAVE_MENSAGEM_FORA_EXPEDIENTE = 'whatsapp_mensagem_fora_expediente';

    private const EXPEDIENTE_INICIO_PADRAO = '08:00';
    private const EXPEDIENTE_FIM_PADRAO = '18:00';
    private const MENSAGEM_FORA_EXPEDIENTE_PADRAO = 'Olá, {nome}! Nosso horário de atendimento está encerrado no momento. Retornaremos assim que estivermos disponíveis.';

    private const CHAVE_NPS_MENSAGEM_ESPERA = 'whatsapp_nps_mensagem_espera';
    private const CHAVE_NPS_PERGUNTA_ATENDENTE = 'whatsapp_nps_pergunta_atendente';
    private const CHAVE_NPS_PERGUNTA_RESOLUCAO = 'whatsapp_nps_pergunta_resolucao';
    private const CHAVE_NPS_AGRADECIMENTO = 'whatsapp_nps_agradecimento';
    private const CHAVE_NPS_TIMEOUT_MINUTOS = 'whatsapp_nps_timeout_minutos';

    private const NPS_MENSAGEM_ESPERA_PADRAO = 'Peço que por gentileza aguarde só mais um momento para avaliar meu atendimento, prometo que será bem rápido, fico grato.';
    private const NPS_PERGUNTA_ATENDENTE_PADRAO = "Sobre a forma como você foi atendido, como você avalia nosso(a) atendente? 👇\nDê uma nota entre 1 e 5 onde:";
    private const NPS_PERGUNTA_RESOLUCAO_PADRAO = 'Ao final deste atendimento, resolvemos a sua solicitação?';
    private const NPS_AGRADECIMENTO_PADRAO = 'Muito obrigado pela sua avaliação, {nome}!';
    private const NPS_TIMEOUT_MINUTOS_PADRAO = 10;

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

    public function expedienteAtivo(): bool
    {
        return ConfigService::get(self::CHAVE_EXPEDIENTE_ATIVO, '') === '1';
    }

    public function expedienteInicio(): string
    {
        return (string)(ConfigService::get(self::CHAVE_EXPEDIENTE_INICIO, self::EXPEDIENTE_INICIO_PADRAO) ?: self::EXPEDIENTE_INICIO_PADRAO);
    }

    public function expedienteFim(): string
    {
        return (string)(ConfigService::get(self::CHAVE_EXPEDIENTE_FIM, self::EXPEDIENTE_FIM_PADRAO) ?: self::EXPEDIENTE_FIM_PADRAO);
    }

    public function mensagemForaExpediente(): string
    {
        return (string)(ConfigService::get(self::CHAVE_MENSAGEM_FORA_EXPEDIENTE, self::MENSAGEM_FORA_EXPEDIENTE_PADRAO) ?: self::MENSAGEM_FORA_EXPEDIENTE_PADRAO);
    }

    /**
     * Só considera o expediente pra quem ainda está com o bot (não
     * atrapalha quem já está na fila ou já com um atendente -- essas
     * conversas continuam normalmente fora do horário).
     */
    public function dentroDoExpediente(): bool
    {
        if (!$this->expedienteAtivo()) {
            return true;
        }

        $agora = date('H:i');

        return $agora >= $this->expedienteInicio() && $agora <= $this->expedienteFim();
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function salvarExpediente(bool $ativo, string $inicio, string $fim, string $mensagem): array
    {
        $mensagem = trim($mensagem);

        if ($ativo) {
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $inicio) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $fim)) {
                return ['success' => false, 'message' => 'Informe os horários de início e fim no formato HH:MM.'];
            }

            if ($inicio >= $fim) {
                return ['success' => false, 'message' => 'O horário de início precisa ser antes do horário de fim.'];
            }

            if ($mensagem === '') {
                return ['success' => false, 'message' => 'Preencha a mensagem de fora do expediente.'];
            }
        }

        ConfigService::set(self::CHAVE_EXPEDIENTE_ATIVO, $ativo ? '1' : '0');
        ConfigService::set(self::CHAVE_EXPEDIENTE_INICIO, $inicio);
        ConfigService::set(self::CHAVE_EXPEDIENTE_FIM, $fim);
        ConfigService::set(self::CHAVE_MENSAGEM_FORA_EXPEDIENTE, $mensagem);

        return ['success' => true, 'message' => 'Horário de expediente salvo.'];
    }

    public function npsMensagemEspera(): string
    {
        return (string)(ConfigService::get(self::CHAVE_NPS_MENSAGEM_ESPERA, self::NPS_MENSAGEM_ESPERA_PADRAO) ?: self::NPS_MENSAGEM_ESPERA_PADRAO);
    }

    public function npsPerguntaAtendente(): string
    {
        return (string)(ConfigService::get(self::CHAVE_NPS_PERGUNTA_ATENDENTE, self::NPS_PERGUNTA_ATENDENTE_PADRAO) ?: self::NPS_PERGUNTA_ATENDENTE_PADRAO);
    }

    public function npsPerguntaResolucao(): string
    {
        return (string)(ConfigService::get(self::CHAVE_NPS_PERGUNTA_RESOLUCAO, self::NPS_PERGUNTA_RESOLUCAO_PADRAO) ?: self::NPS_PERGUNTA_RESOLUCAO_PADRAO);
    }

    public function npsAgradecimento(): string
    {
        return (string)(ConfigService::get(self::CHAVE_NPS_AGRADECIMENTO, self::NPS_AGRADECIMENTO_PADRAO) ?: self::NPS_AGRADECIMENTO_PADRAO);
    }

    public function npsTimeoutMinutos(): int
    {
        $valor = (int)(ConfigService::get(self::CHAVE_NPS_TIMEOUT_MINUTOS, (string)self::NPS_TIMEOUT_MINUTOS_PADRAO) ?: self::NPS_TIMEOUT_MINUTOS_PADRAO);

        return $valor > 0 ? $valor : self::NPS_TIMEOUT_MINUTOS_PADRAO;
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function salvarNps(string $mensagemEspera, string $perguntaAtendente, string $perguntaResolucao, string $agradecimento, int $timeoutMinutos): array
    {
        $mensagemEspera = trim($mensagemEspera);
        $perguntaAtendente = trim($perguntaAtendente);
        $perguntaResolucao = trim($perguntaResolucao);
        $agradecimento = trim($agradecimento);

        if ($mensagemEspera === '' || $perguntaAtendente === '' || $perguntaResolucao === '' || $agradecimento === '') {
            return ['success' => false, 'message' => 'Preencha todas as mensagens do NPS.'];
        }

        if ($timeoutMinutos < 1 || $timeoutMinutos > 1440) {
            return ['success' => false, 'message' => 'Informe um tempo limite entre 1 e 1440 minutos.'];
        }

        ConfigService::set(self::CHAVE_NPS_MENSAGEM_ESPERA, $mensagemEspera);
        ConfigService::set(self::CHAVE_NPS_PERGUNTA_ATENDENTE, $perguntaAtendente);
        ConfigService::set(self::CHAVE_NPS_PERGUNTA_RESOLUCAO, $perguntaResolucao);
        ConfigService::set(self::CHAVE_NPS_AGRADECIMENTO, $agradecimento);
        ConfigService::set(self::CHAVE_NPS_TIMEOUT_MINUTOS, (string)$timeoutMinutos);

        return ['success' => true, 'message' => 'Mensagens de NPS salvas.'];
    }
}
