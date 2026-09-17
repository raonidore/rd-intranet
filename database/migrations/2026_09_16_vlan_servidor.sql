-- Infraestrutura > VLANs -- sub-interfaces 802.1Q criadas neste próprio
-- servidor (netplan), cada uma virando o "gateway" de uma rede/VLAN.
-- Nasceu de um caso real: cliente perdeu o gateway UniFi (que fazia
-- roteamento entre VLANs + DHCP) e precisou emergencialmente que este
-- servidor assumisse as duas funções -- ver também 2026_09_16_dhcp_servidor.sql
-- (DHCP) e o módulo de Firewall existente (ip_forward + NAT/masquerade
-- pra rotear entre VLANs e liberar internet).
CREATE TABLE IF NOT EXISTS `vlans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `interface_pai` varchar(30) NOT NULL COMMENT 'interface física/trunk onde a VLAN é criada, ex: eth0',
  `vlan_id` smallint(6) NOT NULL COMMENT 'tag 802.1Q, 1-4094',
  `ip_endereco` varchar(45) NOT NULL COMMENT 'IP deste servidor nessa VLAN -- vira o gateway dos dispositivos dela',
  `prefixo` tinyint(3) unsigned NOT NULL DEFAULT 24 COMMENT 'máscara em CIDR, ex: 24 = /24',
  `descricao` varchar(255) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_vlan_interface` (`interface_pai`, `vlan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- DHCP precisa poder escutar em VÁRIAS interfaces ao mesmo tempo (a física
-- + cada sub-interface de VLAN que precisa de concessão) -- antes disso
-- guardava só um nome de interface (varchar(30)); agora guarda uma lista
-- separada por espaço (mesmo formato que INTERFACESv4 do isc-dhcp-server
-- já aceita nativamente).
ALTER TABLE `dhcp_config` MODIFY COLUMN `interface` VARCHAR(255) DEFAULT NULL;
