-- Unidades (filiais/sites) da empresa -- cadastro novo, usado no código
-- de patrimônio dos ativos e (mais adiante) na abertura de chamados.
-- Empresa com uma sede só cadastra 1 unidade e nunca mais mexe nisso;
-- quem tem várias filiais cadastra uma linha por filial.
CREATE TABLE IF NOT EXISTS `unidades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) NOT NULL,
  `sigla` varchar(6) NOT NULL,
  `padrao` tinyint(1) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_unidades_sigla` (`sigla`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Unidade padrão: existe pra todo ativo já cadastrado ter uma unidade
-- pra apontar sem fricção nenhuma -- renomeie/troque a sigla em
-- Administração > Empresa antes de cadastrar ativo novo, se fizer sentido.
INSERT INTO `unidades` (`nome`, `sigla`, `padrao`, `ativo`) VALUES ('Unidade Padrão', 'UN', 1, 1);

-- Tipo de ativo -- vira cadastro de verdade (era ENUM fixo + array no
-- código), no mesmo espírito de Setor/Localização em ativos_catalogos.
-- `slug` existe só pra manter compatibilidade com AtivoService::CAMPOS_DETALHES
-- (schema de campos técnicos extras por tipo, que continua definido no
-- código -- só nome/sigla/ícone/elegibilidade SNMP viram editáveis pelo
-- admin); tipo cadastrado pelo admin a partir de agora não tem slug e
-- fica sem campos técnicos extras, que é o comportamento correto.
CREATE TABLE IF NOT EXISTS `ativos_tipos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(40) DEFAULT NULL,
  `nome` varchar(100) NOT NULL,
  `sigla` varchar(6) NOT NULL,
  `icone` varchar(40) NOT NULL DEFAULT 'bi-box-seam',
  `snmp_elegivel` tinyint(1) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ativos_tipos_slug` (`slug`),
  UNIQUE KEY `uq_ativos_tipos_nome` (`nome`),
  UNIQUE KEY `uq_ativos_tipos_sigla` (`sigla`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `ativos_tipos` (`slug`, `nome`, `sigla`, `icone`, `snmp_elegivel`) VALUES
  ('computador', 'Computador', 'PC', 'bi-pc-display', 1),
  ('servidor', 'Servidor', 'SRV', 'bi-hdd-rack', 1),
  ('monitor', 'Monitor', 'MON', 'bi-display', 0),
  ('impressora', 'Impressora', 'IMP', 'bi-printer', 1),
  ('switch', 'Switch', 'SW', 'bi-hdd-network', 1);

-- Contador do número sequencial do código de patrimônio, por tipo +
-- unidade (cada unidade tem sua própria contagem por tipo) -- gerado de
-- forma atômica (INSERT ... ON DUPLICATE KEY UPDATE ... LAST_INSERT_ID
-- no código), substitui a lógica antiga de "pegar o último código
-- cadastrado e somar 1", que tinha corrida entre gravações simultâneas.
CREATE TABLE IF NOT EXISTS `ativos_contadores` (
  `tipo_id` int(11) NOT NULL,
  `unidade_id` int(11) NOT NULL,
  `ultimo_numero` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`tipo_id`, `unidade_id`),
  CONSTRAINT `fk_ativos_contadores_tipo` FOREIGN KEY (`tipo_id`) REFERENCES `ativos_tipos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ativos_contadores_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Continua a numeração de onde cada tipo já estava (na unidade padrão),
-- em vez de reiniciar em 1 e confundir quem gerencia o inventário.
INSERT INTO `ativos_contadores` (`tipo_id`, `unidade_id`, `ultimo_numero`)
SELECT t.id, (SELECT id FROM unidades WHERE padrao = 1 LIMIT 1),
       COALESCE(MAX(CAST(REGEXP_SUBSTR(a.codigo_patrimonio, '[0-9]+$') AS UNSIGNED)), 0)
FROM ativos_tipos t
LEFT JOIN ativos a ON a.tipo = t.slug
GROUP BY t.id;

-- ativos: troca `tipo` (ENUM) por `tipo_id` (FK) e ganha `unidade_id`.
-- Nenhum codigo_patrimonio já existente muda de valor -- são etiquetas
-- físicas já impressas; o formato novo (EMPRESA-UNIDADE-TIPO-NUMERO)
-- vale só pra ativo cadastrado a partir de agora.
ALTER TABLE `ativos`
  ADD COLUMN `tipo_id` int(11) DEFAULT NULL AFTER `tipo`,
  ADD COLUMN `unidade_id` int(11) DEFAULT NULL AFTER `tipo_id`,
  MODIFY COLUMN `codigo_patrimonio` varchar(48) NOT NULL;

UPDATE `ativos` a
JOIN `ativos_tipos` t ON t.slug = a.tipo
SET a.tipo_id = t.id;

UPDATE `ativos` SET `unidade_id` = (SELECT id FROM unidades WHERE padrao = 1 LIMIT 1);

ALTER TABLE `ativos`
  MODIFY COLUMN `tipo_id` int(11) NOT NULL,
  MODIFY COLUMN `unidade_id` int(11) NOT NULL,
  DROP COLUMN `tipo`,
  ADD KEY `fk_ativos_tipo` (`tipo_id`),
  ADD KEY `fk_ativos_unidade` (`unidade_id`),
  ADD CONSTRAINT `fk_ativos_tipo` FOREIGN KEY (`tipo_id`) REFERENCES `ativos_tipos` (`id`),
  ADD CONSTRAINT `fk_ativos_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`);
