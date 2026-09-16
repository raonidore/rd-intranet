-- Servidor DHCP (Infraestrutura > Servidor DHCP) -- isc-dhcp-server,
-- mesmo padrao de "servico de rede que este app instala/configura"
-- ja usado por WireGuard/Firewall (config gerada em PHP, aplicada com
-- dry-run + backup + reversao automatica agendada, ver
-- scripts/system/dhcp_aplicar_web.sh).
CREATE TABLE IF NOT EXISTS `dhcp_config` (
  `id` int(11) NOT NULL,
  `instalado` tinyint(1) NOT NULL DEFAULT 0,
  `interface` varchar(30) DEFAULT NULL,
  `servico_ativo` tinyint(1) NOT NULL DEFAULT 0,
  `dominio` varchar(150) DEFAULT NULL,
  `dns_primario` varchar(45) DEFAULT NULL,
  `dns_secundario` varchar(45) DEFAULT NULL,
  `lease_padrao_segundos` int(11) NOT NULL DEFAULT 43200,
  `lease_maximo_segundos` int(11) NOT NULL DEFAULT 86400,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `dhcp_config` (`id`) VALUES (1);

-- Uma "subnet" do dhcpd.conf -- normalmente só uma (a rede local), mas
-- suporta mais de uma pra quem tem VLAN/sub-rede adicional na mesma
-- interface (secundary IP) ou uma segunda interface.
CREATE TABLE IF NOT EXISTS `dhcp_subnets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `rede` varchar(45) NOT NULL COMMENT 'ex: 192.168.1.0',
  `mascara` varchar(45) NOT NULL COMMENT 'ex: 255.255.255.0',
  `faixa_inicio` varchar(45) NOT NULL,
  `faixa_fim` varchar(45) NOT NULL,
  `gateway` varchar(45) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Reserva estática (IP fixo por MAC) -- o motivo mais comum de alguém
-- precisar mexer no DHCP no dia a dia (impressora, servidor, camera
-- que precisa sempre do mesmo IP sem virar IP estático manual na
-- própria máquina).
CREATE TABLE IF NOT EXISTS `dhcp_reservas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subnet_id` int(11) NOT NULL,
  `mac_address` varchar(17) NOT NULL COMMENT 'formato aa:bb:cc:dd:ee:ff',
  `ip` varchar(45) NOT NULL,
  `descricao` varchar(150) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dhcp_reservas_mac` (`mac_address`),
  UNIQUE KEY `uq_dhcp_reservas_ip` (`ip`),
  CONSTRAINT `fk_dhcp_reservas_subnet` FOREIGN KEY (`subnet_id`) REFERENCES `dhcp_subnets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
