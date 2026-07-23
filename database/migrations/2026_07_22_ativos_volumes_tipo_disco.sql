-- SSD x HDD por volume -- coletado via MSFT_PhysicalDisk.MediaType no
-- agente Windows (namespace root\Microsoft\Windows\Storage, só existe a
-- partir do Windows 8/Server 2012). NULL até a máquina atualizar o
-- agente ou em namespaces mais antigos onde a consulta não existe --
-- usado no gráfico "Panorama da Frota" (Ativos > Lista).
ALTER TABLE ativos_volumes
    ADD COLUMN tipo_disco VARCHAR(20) NULL DEFAULT NULL AFTER serial_disco;
