-- Heartbeat de "ligado/desligado" em tempo real, separado da coleta completa
-- (que continua rodando a cada N minutos, configuravel). O agente Windows
-- passa a mandar um ping bem leve (so o machine_guid) num intervalo curto,
-- configuravel em segundos -- ve-se em `checkin_solicitado_em` se um admin
-- pediu, pelo portal, uma coleta completa fora do ciclo normal.
ALTER TABLE ativos
    ADD COLUMN ultimo_heartbeat TIMESTAMP NULL DEFAULT NULL AFTER ultimo_checkin,
    ADD COLUMN checkin_solicitado_em TIMESTAMP NULL DEFAULT NULL AFTER ultimo_heartbeat;
