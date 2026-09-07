-- Tipo "Roteador/Gateway" -- usado pro UniFi Cloud Gateway (UCG) e outros
-- gateways/roteadores gerenciados via API (mesma ideia do tipo "Ponto de
-- Acesso" criado antes: precisa de slug pra CAMPOS_DETALHES conseguir
-- mostrar os dados coletados na ficha do ativo).
INSERT INTO `ativos_tipos` (`slug`, `nome`, `sigla`, `icone`, `snmp_elegivel`)
VALUES ('roteador', 'Roteador/Gateway', 'GW', 'bi-router', 0)
ON DUPLICATE KEY UPDATE slug = 'roteador';
