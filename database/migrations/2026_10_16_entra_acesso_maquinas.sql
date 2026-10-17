-- Entra > Acesso às Máquinas: último pedido de restrição de login enviado a
-- cada máquina (quem foi autorizado -- grupos/usuários do Entra -- quando e por
-- quem). autorizados NULL = restrição removida (login liberado de novo).
CREATE TABLE IF NOT EXISTS `entra_acesso_maquinas` (
  `ativo_id` int(11) NOT NULL,
  `autorizados` text DEFAULT NULL,
  `aplicado_por` varchar(150) DEFAULT NULL,
  `aplicado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ativo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
