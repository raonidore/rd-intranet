-- Fase 2 do modulo de Ativos: coleta via SNMP. `snmp_habilitado` liga a
-- coleta pra aquele ativo especifico; `snmp_community` sobrescreve a
-- community padrao (guardada em `configuracoes`) so quando o dispositivo
-- usa uma diferente da rede em geral.
ALTER TABLE ativos
    ADD COLUMN snmp_habilitado TINYINT(1) NOT NULL DEFAULT 0 AFTER ip,
    ADD COLUMN snmp_community VARCHAR(100) NULL AFTER snmp_habilitado;

INSERT IGNORE INTO configuracoes (chave, valor) VALUES ('ativos_snmp_community_padrao', 'public');
