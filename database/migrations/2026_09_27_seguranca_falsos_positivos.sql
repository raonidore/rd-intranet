-- Como cada evento de segurança foi encerrado: 'falso_positivo',
-- 'ataque_confirmado' ou 'resolvido' (genérico, o comportamento antigo).
-- Alimenta a aba "Exceções e falsos positivos" da Central de Segurança,
-- que mostra onde os detectores erram pra ajustar as exceções.
ALTER TABLE ativos_eventos_seguranca
    ADD COLUMN IF NOT EXISTS resolucao VARCHAR(20) NULL DEFAULT NULL AFTER resolvido_por,
    ADD COLUMN IF NOT EXISTS resolucao_nota VARCHAR(255) NULL DEFAULT NULL AFTER resolucao;
