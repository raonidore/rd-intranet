-- Segurança > Trilha de acesso: quem entrou em qual máquina e quando, para
-- evidência de auditoria/acreditação. Duas origens: o log de segurança do
-- Windows coletado pelo agente (login, logoff, bloqueio/desbloqueio de tela,
-- falhas e acessos negados -- inclusive login sem internet) e o registro de
-- entradas do Entra ID (login no Windows autenticado na Microsoft). Guardado
-- aqui porque o Windows sobrescreve o log em semanas e o Entra retém só 30 dias.
CREATE TABLE IF NOT EXISTS `acessos_maquinas` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `origem` varchar(10) NOT NULL,
  `ref` varchar(80) NOT NULL,
  `ativo_id` int(11) DEFAULT NULL,
  `maquina` varchar(150) DEFAULT NULL,
  `evento` varchar(20) NOT NULL,
  `forma` varchar(40) DEFAULT NULL,
  `usuario` varchar(200) DEFAULT NULL,
  `upn` varchar(200) DEFAULT NULL,
  `sid` varchar(100) DEFAULT NULL,
  `logon_id` varchar(30) DEFAULT NULL,
  `ip` varchar(60) DEFAULT NULL,
  `detalhe` varchar(300) DEFAULT NULL,
  `codigo_evento` int(11) DEFAULT NULL,
  `ocorrido_em` datetime NOT NULL,
  `recebido_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_acesso_ref` (`origem`, `ref`),
  KEY `idx_acesso_quando` (`ocorrido_em`),
  KEY `idx_acesso_ativo` (`ativo_id`, `ocorrido_em`),
  KEY `idx_acesso_logon` (`ativo_id`, `logon_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
