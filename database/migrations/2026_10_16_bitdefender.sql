-- Integração Bitdefender GravityZone (Administração > Integrações; tela em
-- Segurança > Bitdefender). Cópia local do que o console sabe de cada
-- máquina (sincronizada por cron) e da quarentena -- itens novos viram alerta
-- na Central de Segurança. Origem: Enzilab NTO (BEST 8.26, 7 máquinas).
CREATE TABLE IF NOT EXISTS `bitdefender_endpoints` (
  `endpoint_id` varchar(40) NOT NULL,
  `ativo_id` int(11) DEFAULT NULL,
  `nome` varchar(150) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `macs` varchar(500) DEFAULT NULL,
  `dados` mediumtext NOT NULL,
  `visto_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`endpoint_id`),
  KEY `idx_bd_endpoints_ativo` (`ativo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bitdefender_quarentena` (
  `item_id` varchar(40) NOT NULL,
  `endpoint_id` varchar(40) NOT NULL,
  `ativo_id` int(11) DEFAULT NULL,
  `endpoint_nome` varchar(150) DEFAULT NULL,
  `ameaca` varchar(200) DEFAULT NULL,
  `arquivo` varchar(1000) DEFAULT NULL,
  `quarentenado_em` datetime DEFAULT NULL,
  `pode_restaurar` tinyint(1) NOT NULL DEFAULT 0,
  `pode_remover` tinyint(1) NOT NULL DEFAULT 0,
  `evento_id` int(11) DEFAULT NULL,
  `visto_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`item_id`),
  KEY `idx_bd_quarentena_endpoint` (`endpoint_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
