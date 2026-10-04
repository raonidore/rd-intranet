-- Setor do solicitante: de qual setor DA EMPRESA (Financeiro, RH, Vendas...)
-- o chamado veio. Cadastro separado de chamados_setores, que são as
-- EQUIPES que atendem (Suporte Técnico, TI...) -- o cliente Sidore cadastrou
-- os setores da empresa como se fossem equipes. Opcional: sem nenhum setor
-- cadastrado aqui, o campo nem aparece na abertura. unidade_id NULL = vale
-- para todas as unidades.

CREATE TABLE IF NOT EXISTS `chamados_setores_solicitantes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `unidade_id` int(11) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chamados_setor_solic_unidade` (`unidade_id`),
  CONSTRAINT `fk_chamados_setor_solic_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `chamados`
  ADD COLUMN IF NOT EXISTS `setor_solicitante_id` int(11) DEFAULT NULL AFTER `unidade_id`,
  ADD KEY IF NOT EXISTS `idx_chamados_setor_solicitante` (`setor_solicitante_id`);

-- FK à parte: ADD CONSTRAINT não tem IF NOT EXISTS confiável em todas as versões do MariaDB.
SET @fk_existe = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'chamados' AND CONSTRAINT_NAME = 'fk_chamados_setor_solicitante');
SET @sql = IF(@fk_existe = 0,
  'ALTER TABLE `chamados` ADD CONSTRAINT `fk_chamados_setor_solicitante` FOREIGN KEY (`setor_solicitante_id`) REFERENCES `chamados_setores_solicitantes` (`id`) ON DELETE SET NULL',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
