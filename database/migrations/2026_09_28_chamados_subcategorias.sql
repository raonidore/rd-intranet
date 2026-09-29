-- Subcategorias de chamado (ex.: Software > Excel). Segundo nível fixo,
-- preso a uma categoria. Cada subcategoria pode ter setor próprio e
-- escolher entre herdar o SLA da categoria ou usar prazos próprios
-- (chamados_subcategoria_slas, semeada com os prazos da categoria na
-- criação). Chamados antigos ficam com subcategoria_id NULL.

CREATE TABLE IF NOT EXISTS `chamados_subcategorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `categoria_id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `setor_padrao_id` int(11) DEFAULT NULL,
  `sla_proprio` tinyint(1) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chamados_subcategoria_nome` (`categoria_id`, `nome`),
  KEY `idx_chamados_subcategoria_setor` (`setor_padrao_id`),
  CONSTRAINT `fk_chamados_subcategoria_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `chamados_categorias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chamados_subcategoria_setor` FOREIGN KEY (`setor_padrao_id`) REFERENCES `chamados_setores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chamados_subcategoria_slas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subcategoria_id` int(11) NOT NULL,
  `prioridade` enum('baixa','media','alta','urgente') NOT NULL,
  `tempo_primeira_resposta_min` int(11) NOT NULL,
  `tempo_resolucao_min` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chamados_subcategoria_slas` (`subcategoria_id`, `prioridade`),
  CONSTRAINT `fk_chamados_subcategoria_slas_sub` FOREIGN KEY (`subcategoria_id`) REFERENCES `chamados_subcategorias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `chamados_categorias`
  ADD COLUMN IF NOT EXISTS `exige_subcategoria` tinyint(1) NOT NULL DEFAULT 0 AFTER `setor_padrao_id`;

ALTER TABLE `chamados`
  ADD COLUMN IF NOT EXISTS `subcategoria_id` int(11) DEFAULT NULL AFTER `categoria_id`,
  ADD KEY IF NOT EXISTS `idx_chamados_subcategoria` (`subcategoria_id`);

-- FK à parte: ADD CONSTRAINT não tem IF NOT EXISTS confiável em todas as versões do MariaDB.
SET @fk_existe = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'chamados' AND CONSTRAINT_NAME = 'fk_chamados_subcategoria');
SET @sql = IF(@fk_existe = 0,
  'ALTER TABLE `chamados` ADD CONSTRAINT `fk_chamados_subcategoria` FOREIGN KEY (`subcategoria_id`) REFERENCES `chamados_subcategorias` (`id`) ON DELETE SET NULL',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
