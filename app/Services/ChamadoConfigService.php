<?php

namespace App\Services;

/**
 * Config leve do módulo Chamados (key-value via ConfigService, mesmo
 * padrão do WhatsAppConfigService) -- por ora só o expediente usado
 * pra pausar a contagem de SLA fora do horário de atendimento. Horário
 * próprio, independente do expediente do WhatsApp (podem ser
 * diferentes -- ex: suporte por chamado 24/7, WhatsApp só em horário
 * comercial).
 */
class ChamadoConfigService
{
    private const CHAVE_EXPEDIENTE_ATIVO = 'chamados_expediente_ativo';
    private const CHAVE_EXPEDIENTE_INICIO = 'chamados_expediente_inicio';
    private const CHAVE_EXPEDIENTE_FIM = 'chamados_expediente_fim';

    private const EXPEDIENTE_INICIO_PADRAO = '08:00';
    private const EXPEDIENTE_FIM_PADRAO = '18:00';

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

    /** Fora do expediente (quando o expediente está ativo), o relógio de SLA fica parado -- ver ChamadoSlaService/ChamadoService::calcularPrazos(). */
    public function dentroDoExpediente(): bool
    {
        if (!$this->expedienteAtivo()) {
            return true;
        }

        $agora = date('H:i');

        return $agora >= $this->expedienteInicio() && $agora <= $this->expedienteFim();
    }

    /** @return array{success: bool, message: string} */
    public function salvarExpediente(bool $ativo, string $inicio, string $fim): array
    {
        if ($ativo) {
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $inicio) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $fim)) {
                return ['success' => false, 'message' => 'Informe os horários de início e fim no formato HH:MM.'];
            }

            if ($inicio >= $fim) {
                return ['success' => false, 'message' => 'O horário de início precisa ser antes do horário de fim.'];
            }
        }

        ConfigService::set(self::CHAVE_EXPEDIENTE_ATIVO, $ativo ? '1' : '0');
        ConfigService::set(self::CHAVE_EXPEDIENTE_INICIO, $inicio);
        ConfigService::set(self::CHAVE_EXPEDIENTE_FIM, $fim);

        return ['success' => true, 'message' => 'Horário de expediente salvo.'];
    }
}
