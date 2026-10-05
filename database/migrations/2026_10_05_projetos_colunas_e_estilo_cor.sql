-- Colunas do quadro por projeto (nome, ordem e quantas quiser). Cada coluna
-- aponta pra uma "situação" fixa -- é o que estatísticas, "atrasadas",
-- "aguardando terceiro", progresso de fase e aviso de prazo continuam
-- lendo em projetos_tarefas.coluna. Ex.: "Instalação" e "Testes" podem ser
-- duas colunas de situação "em_andamento".
--
-- Também: cor livre no cartão (#rrggbb além das cores prontas) e
-- cor_estilo -- pinta só a lateral (como era) ou o cartão inteiro.

CREATE TABLE IF NOT EXISTS `projetos_colunas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `projeto_id` int(11) NOT NULL,
  `nome` varchar(60) NOT NULL,
  `situacao` enum('a_fazer','em_andamento','aguardando_terceiro','concluido') NOT NULL DEFAULT 'em_andamento',
  `posicao` int(11) NOT NULL DEFAULT 0,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_projetos_colunas_projeto` (`projeto_id`, `posicao`),
  CONSTRAINT `fk_projetos_colunas_projeto` FOREIGN KEY (`projeto_id`) REFERENCES `projetos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `projetos_tarefas`
  ADD COLUMN IF NOT EXISTS `coluna_id` int(11) DEFAULT NULL AFTER `coluna`,
  ADD COLUMN IF NOT EXISTS `cor_estilo` enum('lateral','inteiro') NOT NULL DEFAULT 'lateral' AFTER `cor`,
  ADD KEY IF NOT EXISTS `idx_projetos_tarefas_coluna_id` (`coluna_id`);

SET @fk_existe = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'projetos_tarefas' AND CONSTRAINT_NAME = 'fk_projetos_tarefas_coluna_quadro');
SET @sql = IF(@fk_existe = 0,
  'ALTER TABLE `projetos_tarefas` ADD CONSTRAINT `fk_projetos_tarefas_coluna_quadro` FOREIGN KEY (`coluna_id`) REFERENCES `projetos_colunas` (`id`) ON DELETE SET NULL',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Projetos que já existem ganham as 4 colunas de sempre...
INSERT INTO `projetos_colunas` (`projeto_id`, `nome`, `situacao`, `posicao`)
SELECT p.id, d.nome, d.situacao, d.posicao
FROM `projetos` p
CROSS JOIN (
  SELECT 'A fazer' AS nome, 'a_fazer' AS situacao, 0 AS posicao
  UNION ALL SELECT 'Em andamento', 'em_andamento', 1
  UNION ALL SELECT 'Aguardando terceiro', 'aguardando_terceiro', 2
  UNION ALL SELECT 'Concluído', 'concluido', 3
) d
WHERE NOT EXISTS (SELECT 1 FROM `projetos_colunas` c WHERE c.projeto_id = p.id);

-- ...e cada tarefa fica na coluna equivalente a onde já estava.
UPDATE `projetos_tarefas` t
JOIN `projetos_colunas` c ON c.projeto_id = t.projeto_id AND c.situacao = t.coluna
SET t.coluna_id = c.id
WHERE t.coluna_id IS NULL;
