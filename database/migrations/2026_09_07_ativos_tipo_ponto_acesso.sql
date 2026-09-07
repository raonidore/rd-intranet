-- Tipo de ativo "Ponto de Acesso" (AP wifi) -- faltava um SLUG de verdade
-- pra isso; em instalações onde o admin já tinha criado um tipo custom
-- pra AP na unha (nasce com slug NULL -- não aparece em
-- AtivoService::CAMPOS_DETALHES, que é indexado por slug, então nenhum
-- dado técnico coletado, SNMP ou API, jamais teria como aparecer na tela
-- desses ativos), o "ON DUPLICATE KEY UPDATE" só completa o slug que
-- faltava nesse tipo já existente (preserva nome/ícone que o admin já
-- escolheu); em instalação nova, cria o tipo do zero já com slug.
INSERT INTO `ativos_tipos` (`slug`, `nome`, `sigla`, `icone`, `snmp_elegivel`)
VALUES ('ponto_acesso', 'Ponto de Acesso', 'AP', 'bi-wifi', 0)
ON DUPLICATE KEY UPDATE slug = 'ponto_acesso';

UPDATE ativos
SET tipo_id = (SELECT id FROM ativos_tipos WHERE slug = 'ponto_acesso')
WHERE codigo_patrimonio = 'EP-UN-AP-000001';
