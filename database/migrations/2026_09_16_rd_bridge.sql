-- RD.Bridge -- coletor instalado numa unidade remota (atras de NAT),
-- fala com este servidor por conexao de saida (mesmo espirito do agente
-- Windows: checkin/heartbeat, header de chave, nunca porta aberta).
-- Um coletor por unidade fisica; a chave (token) e o que autentica as
-- chamadas de API dele, nunca o IP (que pode nem ser fixo/roteavel).
CREATE TABLE IF NOT EXISTS `rd_bridge_coletores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `unidade_id` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `token` varchar(64) NOT NULL,
  `modo` enum('http','vpn') NOT NULL DEFAULT 'http',
  `versao` varchar(20) DEFAULT NULL,
  `ip_ultimo_checkin` varchar(45) DEFAULT NULL,
  `ultimo_checkin_em` datetime DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rd_bridge_token` (`token`),
  CONSTRAINT `fk_rd_bridge_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
