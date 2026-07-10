-- Schema base da RD Intranet, gerado a partir do banco de producao.
-- Usado apenas na instalacao de um servidor novo (scripts/install.sh):
-- cria todas as tabelas ja no estado final, sem precisar repetir o
-- historico incremental de database/migrations/ (algumas dessas
-- migrations usam ALTER TABLE, que nao e seguro reaplicar aqui).
-- Gerado em 2026-07-10 02:56:51.

-- Import nao respeita ordem de dependencia entre tabelas (algumas tem FK
-- pra tabelas que so aparecem depois neste arquivo) -- desliga a checagem
-- soh durante o import, como o proprio mysqldump faz.
SET FOREIGN_KEY_CHECKS=0;

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
  PRIMARY KEY (`id`)
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

SET FOREIGN_KEY_CHECKS=1;

