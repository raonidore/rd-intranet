-- Schema base da RD Intranet, gerado a partir do banco de producao.
-- Usado apenas na instalacao de um servidor novo (scripts/install.sh):
-- cria todas as tabelas ja no estado final, sem precisar repetir o
-- historico incremental de database/migrations/ (algumas dessas
-- migrations usam ALTER TABLE, que nao e seguro reaplicar aqui).
-- Gerado em 2026-07-14 22:06:10.

-- Import nao respeita ordem de dependencia entre tabelas (algumas tem FK
-- pra tabelas que so aparecem depois neste arquivo) -- desliga a checagem
-- soh durante o import, como o proprio mysqldump faz.
SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------------------------------------------
-- antivirus_ameacas
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `antivirus_ameacas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `verificacao_id` int(11) NOT NULL,
  `caminho_original` varchar(500) NOT NULL,
  `caminho_quarentena` varchar(500) DEFAULT NULL,
  `assinatura` varchar(255) NOT NULL,
  `acao` enum('quarentena','ignorado','excluido') NOT NULL DEFAULT 'quarentena',
  `detectado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_antivirus_ameacas_verificacao` (`verificacao_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- antivirus_verificacoes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `antivirus_verificacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('manual','agendada') NOT NULL DEFAULT 'manual',
  `caminho` varchar(500) NOT NULL,
  `status` enum('executando','concluida','erro') NOT NULL DEFAULT 'executando',
  `arquivos_verificados` int(11) NOT NULL DEFAULT 0,
  `ameacas_encontradas` int(11) NOT NULL DEFAULT 0,
  `saida` text DEFAULT NULL,
  `iniciado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `finalizado_em` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('computador','monitor','impressora','switch','servidor') NOT NULL,
  `codigo_patrimonio` varchar(20) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `apelido` varchar(150) DEFAULT NULL,
  `marca` varchar(100) DEFAULT NULL,
  `modelo` varchar(100) DEFAULT NULL,
  `numero_serie` varchar(100) DEFAULT NULL,
  `responsavel` varchar(150) DEFAULT NULL,
  `setor_id` int(11) DEFAULT NULL,
  `localizacao_id` int(11) DEFAULT NULL,
  `status` enum('ativo','manutencao','estoque','baixado') NOT NULL DEFAULT 'ativo',
  `ip` varchar(45) DEFAULT NULL,
  `snmp_habilitado` tinyint(1) NOT NULL DEFAULT 0,
  `snmp_community` varchar(100) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `detalhes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`detalhes`)),
  `origem` enum('manual','agente','snmp') NOT NULL DEFAULT 'manual',
  `machine_guid` varchar(64) DEFAULT NULL,
  `mesh_device_id` varchar(160) DEFAULT NULL,
  `ultimo_checkin` timestamp NULL DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_patrimonio` (`codigo_patrimonio`),
  UNIQUE KEY `machine_guid` (`machine_guid`),
  KEY `idx_ativos_tipo` (`tipo`),
  KEY `idx_ativos_status` (`status`),
  KEY `fk_ativos_setor` (`setor_id`),
  KEY `fk_ativos_localizacao` (`localizacao_id`),
  CONSTRAINT `fk_ativos_localizacao` FOREIGN KEY (`localizacao_id`) REFERENCES `ativos_catalogos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ativos_setor` FOREIGN KEY (`setor_id`) REFERENCES `ativos_catalogos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_alertas
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_alertas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ativo_id` int(11) NOT NULL,
  `nivel` enum('erro','aviso','informacao') NOT NULL,
  `origem_evento` varchar(150) DEFAULT NULL,
  `mensagem` text NOT NULL,
  `ocorrido_em` timestamp NULL DEFAULT NULL,
  `coletado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ativos_alertas_ativo` (`ativo_id`),
  CONSTRAINT `fk_ativos_alertas_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_atualizacoes_windows
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_atualizacoes_windows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ativo_id` int(11) NOT NULL,
  `kb` varchar(20) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `instalado_em` date DEFAULT NULL,
  `coletado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ativos_atualizacoes_ativo` (`ativo_id`),
  CONSTRAINT `fk_ativos_atualizacoes_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_catalogos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_catalogos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('setor','localizacao') NOT NULL,
  `nome` varchar(150) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ativos_catalogos_tipo_nome` (`tipo`,`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_comandos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_comandos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ativo_id` int(11) NOT NULL,
  `comando` enum('desligar','reiniciar','desinstalar_atualizacao','desinstalar_programa') NOT NULL,
  `alvo` varchar(500) DEFAULT NULL,
  `alvo_label` varchar(255) DEFAULT NULL,
  `status` enum('pendente','entregue') NOT NULL DEFAULT 'pendente',
  `solicitado_por` varchar(150) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `entregue_em` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ativos_comandos_ativo` (`ativo_id`),
  CONSTRAINT `fk_ativos_comandos_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_memoria
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_memoria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ativo_id` int(11) NOT NULL,
  `fabricante` varchar(100) DEFAULT NULL,
  `modelo` varchar(150) DEFAULT NULL,
  `capacidade_gb` decimal(10,1) DEFAULT NULL,
  `frequencia_mhz` int(11) DEFAULT NULL,
  `numero_serie` varchar(100) DEFAULT NULL,
  `coletado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ativos_memoria_ativo` (`ativo_id`),
  CONSTRAINT `fk_ativos_memoria_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_portas
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_portas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ativo_id` int(11) NOT NULL,
  `tipo` varchar(30) NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `coletado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ativos_portas_ativo` (`ativo_id`),
  CONSTRAINT `fk_ativos_portas_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_portas_rede
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_portas_rede` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ativo_id` int(11) NOT NULL,
  `protocolo` enum('tcp','udp') NOT NULL,
  `porta_local` int(11) NOT NULL,
  `endereco_local` varchar(45) DEFAULT NULL,
  `processo` varchar(255) DEFAULT NULL,
  `pid` int(11) DEFAULT NULL,
  `coletado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ativos_portas_rede_ativo` (`ativo_id`),
  CONSTRAINT `fk_ativos_portas_rede_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_programas
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_programas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ativo_id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `versao` varchar(100) DEFAULT NULL,
  `data_instalacao` date DEFAULT NULL,
  `uninstall_string` varchar(500) DEFAULT NULL,
  `coletado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ativos_programas_ativo` (`ativo_id`),
  CONSTRAINT `fk_ativos_programas_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_redes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_redes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ativo_id` int(11) NOT NULL,
  `nome_adaptador` varchar(150) DEFAULT NULL,
  `mac` varchar(20) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `coletado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ativos_redes_ativo` (`ativo_id`),
  CONSTRAINT `fk_ativos_redes_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ativos_volumes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ativos_volumes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ativo_id` int(11) NOT NULL,
  `unidade` varchar(10) NOT NULL,
  `total_gb` decimal(10,1) DEFAULT NULL,
  `usado_gb` decimal(10,1) DEFAULT NULL,
  `modelo_disco` varchar(150) DEFAULT NULL,
  `fabricante_disco` varchar(100) DEFAULT NULL,
  `serial_disco` varchar(100) DEFAULT NULL,
  `coletado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ativos_volumes_ativo` (`ativo_id`),
  CONSTRAINT `fk_ativos_volumes_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- atualizacoes_log
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `atualizacoes_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('aplicar','reverter') NOT NULL,
  `commit_antes` varchar(40) DEFAULT NULL,
  `commit_depois` varchar(40) DEFAULT NULL,
  `sucesso` tinyint(1) NOT NULL DEFAULT 0,
  `saida` text DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- auditoria
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `auditoria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_nome` varchar(120) DEFAULT NULL,
  `modulo` varchar(60) NOT NULL,
  `acao` varchar(120) NOT NULL,
  `descricao` text DEFAULT NULL,
  `ip_origem` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- configuracao_deploy
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `configuracao_deploy` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `modulo` varchar(50) NOT NULL,
  `alteracoes_pendentes` tinyint(1) DEFAULT 0,
  `ultimo_deploy` datetime DEFAULT NULL,
  `ultimo_backup` varchar(255) DEFAULT NULL,
  `ultimo_usuario` varchar(100) DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_configuracao_deploy_modulo` (`modulo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- configuracoes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `configuracoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chave` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `atualizado_em` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `chave` (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- cron_jobs
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cron_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `expressao` varchar(60) NOT NULL,
  `usuario_execucao` varchar(60) NOT NULL DEFAULT 'root',
  `comando` text NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `ultima_execucao_em` timestamp NULL DEFAULT NULL,
  `ultima_execucao_sucesso` tinyint(1) DEFAULT NULL,
  `ultima_execucao_saida` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- db_conexoes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `db_conexoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) NOT NULL,
  `host` varchar(255) NOT NULL,
  `porta` int(11) NOT NULL DEFAULT 3306,
  `usuario` varchar(120) NOT NULL,
  `senha_cifrada` text NOT NULL,
  `banco_padrao` varchar(120) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ddns_contas
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ddns_contas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `provedor` enum('noip','dyndns','cloudflare','duckdns','freedns') NOT NULL,
  `apelido` varchar(100) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `credenciais` text NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `ultimo_ip` varchar(45) DEFAULT NULL,
  `ultima_verificacao_em` timestamp NULL DEFAULT NULL,
  `ultima_atualizacao_em` timestamp NULL DEFAULT NULL,
  `ultimo_sucesso` tinyint(1) DEFAULT NULL,
  `ultima_mensagem` varchar(500) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- ddns_historico
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ddns_historico` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conta_id` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `sucesso` tinyint(1) NOT NULL,
  `mensagem` varchar(500) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ddns_historico_conta` (`conta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- deploy_pendencias
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `deploy_pendencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `modulo` varchar(50) NOT NULL,
  `tipo` varchar(80) NOT NULL,
  `referencia` varchar(120) DEFAULT NULL,
  `descricao` text NOT NULL,
  `usuario` varchar(100) DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- iptables_log_eventos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `iptables_log_eventos` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `regra_id` int(11) NOT NULL,
  `ip_origem` varchar(45) DEFAULT NULL,
  `ip_destino` varchar(45) DEFAULT NULL,
  `protocolo` varchar(10) DEFAULT NULL,
  `porta_destino` varchar(10) DEFAULT NULL,
  `ocorrido_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_iptables_log_eventos_ip` (`ip_origem`,`ocorrido_em`),
  KEY `idx_iptables_log_eventos_ocorrido` (`ocorrido_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- iptables_regras
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `iptables_regras` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `tabela` enum('filter','nat') NOT NULL DEFAULT 'filter',
  `cadeia` enum('INPUT','OUTPUT','FORWARD','PREROUTING','POSTROUTING') NOT NULL DEFAULT 'INPUT',
  `acao` enum('ACCEPT','DROP','REJECT','MASQUERADE','DNAT','SNAT','LOG','NONE') NOT NULL DEFAULT 'ACCEPT',
  `protocolo` enum('tcp','udp','icmp','all') NOT NULL DEFAULT 'tcp',
  `porta_destino` varchar(20) DEFAULT NULL,
  `porta_origem` varchar(20) DEFAULT NULL,
  `ip_origem` varchar(64) DEFAULT NULL,
  `ip_destino` varchar(64) DEFAULT NULL,
  `interface_entrada` varchar(30) DEFAULT NULL,
  `interface_saida` varchar(30) DEFAULT NULL,
  `nat_destino` varchar(64) DEFAULT NULL,
  `extra` varchar(255) DEFAULT NULL,
  `registrar_log` tinyint(1) NOT NULL DEFAULT 0,
  `ordem` int(11) NOT NULL DEFAULT 100,
  `origem_template` varchar(60) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_iptables_tabela_cadeia_ordem` (`tabela`,`cadeia`,`ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- iptables_regras_historico
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `iptables_regras_historico` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `regra_id` int(11) NOT NULL,
  `pkts` bigint(20) unsigned NOT NULL DEFAULT 0,
  `bytes` bigint(20) unsigned NOT NULL DEFAULT 0,
  `coletado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_iptables_historico_regra_coletado` (`regra_id`,`coletado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- migrations_aplicadas
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `migrations_aplicadas` (
  `arquivo` varchar(180) NOT NULL,
  `aplicado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`arquivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- passos_manuais_confirmacoes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `passos_manuais_confirmacoes` (
  `chave` varchar(80) NOT NULL,
  `confirmado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `confirmado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- rede_trafego_historico
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rede_trafego_historico` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `interface` varchar(50) NOT NULL,
  `rx_bytes` bigint(20) unsigned NOT NULL DEFAULT 0,
  `tx_bytes` bigint(20) unsigned NOT NULL DEFAULT 0,
  `rx_packets` bigint(20) unsigned NOT NULL DEFAULT 0,
  `tx_packets` bigint(20) unsigned NOT NULL DEFAULT 0,
  `coletado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rede_trafego_interface_coletado` (`interface`,`coletado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- samba_compartilhamento_usuarios
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `samba_compartilhamento_usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `compartilhamento_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `leitura` tinyint(1) DEFAULT 1,
  `escrita` tinyint(1) DEFAULT 1,
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_share_usuario` (`compartilhamento_id`,`usuario_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `samba_compartilhamento_usuarios_ibfk_1` FOREIGN KEY (`compartilhamento_id`) REFERENCES `samba_compartilhamentos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `samba_compartilhamento_usuarios_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `samba_usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- samba_compartilhamentos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `samba_compartilhamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(80) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `caminho` varchar(255) NOT NULL,
  `grupo` varchar(80) NOT NULL,
  `somente_leitura` tinyint(1) NOT NULL DEFAULT 0,
  `lixeira` tinyint(1) NOT NULL DEFAULT 1,
  `bloqueio_extensoes` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('ativo','desativado') NOT NULL DEFAULT 'ativo',
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- samba_usuarios
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `samba_usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) NOT NULL,
  `login` varchar(60) NOT NULL,
  `departamento` varchar(80) NOT NULL,
  `ssh` tinyint(1) NOT NULL DEFAULT 0,
  `uid_linux` int(11) DEFAULT NULL,
  `status` enum('ativo','desativado') NOT NULL DEFAULT 'ativo',
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `login` (`login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- speedtest_historico
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `speedtest_historico` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status` enum('concluido','erro') NOT NULL,
  `download_mbps` decimal(10,2) DEFAULT NULL,
  `upload_mbps` decimal(10,2) DEFAULT NULL,
  `ping_ms` decimal(10,2) DEFAULT NULL,
  `jitter_ms` decimal(10,2) DEFAULT NULL,
  `servidor` varchar(255) DEFAULT NULL,
  `isp` varchar(255) DEFAULT NULL,
  `mensagem_erro` varchar(500) DEFAULT NULL,
  `executado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- usuario_modulos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuario_modulos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `modulo` varchar(60) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuario_modulo` (`usuario_id`,`modulo`),
  CONSTRAINT `fk_usuario_modulos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- usuarios
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) NOT NULL,
  `login` varchar(60) NOT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `perfil` enum('admin','ti','consulta') NOT NULL DEFAULT 'ti',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `login` (`login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- vpn_ikev2_clientes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vpn_ikev2_clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(64) NOT NULL,
  `senha` text NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `config_entregue` tinyint(1) NOT NULL DEFAULT 0,
  `config_entregue_em` timestamp NULL DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `revogado_em` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- vpn_ikev2_conexoes_saida
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vpn_ikev2_conexoes_saida` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(64) NOT NULL,
  `servidor_remoto` varchar(255) NOT NULL,
  `tipo_auth` enum('psk','eap') NOT NULL DEFAULT 'psk',
  `segredo` text NOT NULL,
  `usuario_eap` varchar(100) DEFAULT NULL,
  `subnet_remota` varchar(30) NOT NULL DEFAULT '0.0.0.0/0',
  `ca_remota` text DEFAULT NULL,
  `ativo_no_boot` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- vpn_ikev2_config
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vpn_ikev2_config` (
  `id` int(11) NOT NULL DEFAULT 1,
  `subnet_cidr` varchar(30) NOT NULL DEFAULT '10.10.0.0/24',
  `dns_push` varchar(100) DEFAULT NULL,
  `endpoint_publico` varchar(255) DEFAULT NULL,
  `pki_inicializada` tinyint(1) NOT NULL DEFAULT 0,
  `instalado` tinyint(1) NOT NULL DEFAULT 0,
  `exposto_internet` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- vpn_ikev2_trafego_historico
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vpn_ikev2_trafego_historico` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) NOT NULL,
  `rx_bytes` bigint(20) NOT NULL,
  `tx_bytes` bigint(20) NOT NULL,
  `coletado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vpn_ikev2_trafego_cliente` (`cliente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- vpn_openvpn_clientes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vpn_openvpn_clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(64) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `config_entregue` tinyint(1) NOT NULL DEFAULT 0,
  `config_entregue_em` timestamp NULL DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `revogado_em` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- vpn_openvpn_conexoes_saida
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vpn_openvpn_conexoes_saida` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(64) NOT NULL,
  `arquivo_ovpn` text NOT NULL,
  `ativo_no_boot` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- vpn_openvpn_config
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vpn_openvpn_config` (
  `id` int(11) NOT NULL DEFAULT 1,
  `porta` int(11) NOT NULL DEFAULT 1194,
  `protocolo` enum('udp','tcp') NOT NULL DEFAULT 'udp',
  `subnet_cidr` varchar(30) NOT NULL DEFAULT '10.9.0.0/24',
  `dns_push` varchar(100) DEFAULT NULL,
  `endpoint_publico` varchar(255) DEFAULT NULL,
  `redirect_gateway` tinyint(1) NOT NULL DEFAULT 0,
  `pki_inicializada` tinyint(1) NOT NULL DEFAULT 0,
  `instalado` tinyint(1) NOT NULL DEFAULT 0,
  `exposto_internet` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- vpn_openvpn_trafego_historico
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vpn_openvpn_trafego_historico` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) NOT NULL,
  `rx_bytes` bigint(20) NOT NULL,
  `tx_bytes` bigint(20) NOT NULL,
  `conectado_desde` timestamp NULL DEFAULT NULL,
  `coletado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vpn_ovpn_trafego_cliente` (`cliente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- vpn_wireguard_conexoes_saida
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vpn_wireguard_conexoes_saida` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(15) NOT NULL,
  `arquivo_conf` text NOT NULL,
  `ativo_no_boot` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- vpn_wireguard_config
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vpn_wireguard_config` (
  `id` int(11) NOT NULL DEFAULT 1,
  `interface_nome` varchar(20) NOT NULL DEFAULT 'wg0',
  `porta` int(11) NOT NULL DEFAULT 51820,
  `subnet_cidr` varchar(30) NOT NULL DEFAULT '10.8.0.0/24',
  `servidor_ip_interno` varchar(45) NOT NULL DEFAULT '10.8.0.1',
  `chave_privada` text DEFAULT NULL,
  `chave_publica` text DEFAULT NULL,
  `dns_push` varchar(100) DEFAULT NULL,
  `endpoint_publico` varchar(255) DEFAULT NULL,
  `mtu` int(11) DEFAULT NULL,
  `exposto_internet` tinyint(1) NOT NULL DEFAULT 0,
  `instalado` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- vpn_wireguard_peers
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vpn_wireguard_peers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `chave_publica` varchar(64) NOT NULL,
  `ip_atribuido` varchar(45) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `config_entregue` tinyint(1) NOT NULL DEFAULT 0,
  `config_entregue_em` timestamp NULL DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `revogado_em` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chave_publica` (`chave_publica`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------
-- vpn_wireguard_trafego_historico
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vpn_wireguard_trafego_historico` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `peer_id` int(11) NOT NULL,
  `rx_bytes` bigint(20) NOT NULL,
  `tx_bytes` bigint(20) NOT NULL,
  `ultimo_handshake` timestamp NULL DEFAULT NULL,
  `coletado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vpn_wg_trafego_peer` (`peer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS=1;
