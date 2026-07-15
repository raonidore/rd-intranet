-- Versao do agente Windows (.exe) reportada no proprio checkin -- pra
-- confirmar, direto pela lista de ativos, se uma maquina ja pegou a
-- atualizacao mais recente ou ainda esta rodando uma versao antiga.
ALTER TABLE ativos
    ADD COLUMN agente_versao VARCHAR(20) NULL DEFAULT NULL AFTER origem;
