-- Tipo "DVR/NVR" -- gravadores de câmeras de segurança (ex: Intelbras
-- achado via IP Scanner em 192.168.1.157) não tinham tipo próprio pra
-- cadastrar como Ativo. Mesma ideia dos tipos "Roteador/Gateway" e
-- "Ponto de Acesso" criados antes.
INSERT INTO `ativos_tipos` (`slug`, `nome`, `sigla`, `icone`, `snmp_elegivel`)
VALUES ('dvr_nvr', 'DVR/NVR', 'DVR', 'bi-camera-video', 1)
ON DUPLICATE KEY UPDATE slug = 'dvr_nvr';
