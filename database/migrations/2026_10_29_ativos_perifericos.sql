-- Periféricos ligados a um computador (monitor, impressora, teclado, mouse).
--
-- conectado_a_id: o ativo (computador/servidor) em que este periférico
-- está ligado. Preenchido ao cadastrar pela ficha do computador, à mão no
-- formulário, ou sozinho no check-in quando o agente vê um periférico já
-- cadastrado (mesmo nº de série) noutra máquina -- o monitor "muda" junto.
ALTER TABLE `ativos`
  ADD COLUMN IF NOT EXISTS `conectado_a_id` int(11) DEFAULT NULL AFTER `localizacao_id`,
  ADD KEY IF NOT EXISTS `fk_ativos_conectado_a` (`conectado_a_id`);

ALTER TABLE `ativos`
  ADD CONSTRAINT `fk_ativos_conectado_a` FOREIGN KEY IF NOT EXISTS (`conectado_a_id`) REFERENCES `ativos` (`id`) ON DELETE SET NULL;

-- O que o agente (1.0.94+) viu ligado na máquina no último check-in.
-- Substituído a cada check-in, igual a ativos_portas.
CREATE TABLE IF NOT EXISTS `ativos_perifericos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ativo_id` int(11) NOT NULL,
  `tipo` varchar(20) NOT NULL,
  `fabricante` varchar(100) DEFAULT NULL,
  `modelo` varchar(150) DEFAULT NULL,
  `numero_serie` varchar(100) DEFAULT NULL,
  `conexao` varchar(60) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `detalhe` varchar(255) DEFAULT NULL,
  `coletado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ativos_perifericos_ativo` (`ativo_id`),
  CONSTRAINT `fk_ativos_perifericos_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
