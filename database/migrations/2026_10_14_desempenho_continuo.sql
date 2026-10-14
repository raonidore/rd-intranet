-- Coleta contínua de desempenho (agente 1.0.61+): liga/desliga e intervalo
-- por máquina (NULL = segue o padrão global em configuracoes:
-- seguranca_modulo_padrao_desempenho / _desempenho_intervalo). O resultado
-- (histórico, picos, consumidores, tráfego por IP) chega como retrato
-- "desempenho" em ativos_seguranca_retratos.
ALTER TABLE ativos_seguranca_modulos
    ADD COLUMN IF NOT EXISTS desempenho_habilitado TINYINT(1) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS desempenho_intervalo_segundos INT NULL DEFAULT NULL;
