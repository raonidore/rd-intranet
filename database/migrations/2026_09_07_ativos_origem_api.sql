-- Dado coletado via API externa (ex: UniFi Network Controller) nao e
-- SNMP -- merece sua propria proveniencia em vez de ser gravado como
-- 'snmp' incorretamente.
ALTER TABLE ativos MODIFY origem ENUM('manual','agente','snmp','api') NOT NULL DEFAULT 'manual';
