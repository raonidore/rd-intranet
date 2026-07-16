-- Unidades de rede mapeadas (ex: Z: -> \\servidor\pasta) reportadas pelo
-- agente Windows junto dos volumes locais -- mesma tabela, so marcadas
-- como "rede" pra distinguir na tela (sem medidor de disco fisico, com
-- o caminho UNC por tras da letra).
ALTER TABLE ativos_volumes
    ADD COLUMN rede TINYINT(1) NOT NULL DEFAULT 0 AFTER serial_disco,
    ADD COLUMN caminho_rede VARCHAR(260) NULL DEFAULT NULL AFTER rede;
