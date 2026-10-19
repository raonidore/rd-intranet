-- Entra > Acesso às Máquinas > Administradores destas máquinas: quem o portal
-- colocou no grupo Administradores de cada máquina (grupo do Entra expandido nas
-- pessoas -- o Windows não traz grupo no token). sids = SIDs que o PORTAL pôs lá:
-- na reaplicação só esses podem sair (nunca o Administrador interno nem papéis).
CREATE TABLE IF NOT EXISTS `entra_admins_maquinas` (
  `ativo_id` int(11) NOT NULL,
  `autorizados` text DEFAULT NULL,
  `usuarios_hash` char(40) DEFAULT NULL,
  `sids` text DEFAULT NULL,
  `aplicado_por` varchar(150) DEFAULT NULL,
  `aplicado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ativo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
