-- App Android do RD.Intranet (módulo Chamados para a equipe de TI).
-- app_sessoes guarda o login feito no celular (token opaco, só o hash fica
-- no banco, revogável por aparelho); app_login_falhas limita tentativas de
-- senha por login e por IP. Canal 'app' identifica chamados abertos pelo app.

ALTER TABLE chamados MODIFY canal_abertura enum('painel','email','whatsapp','portal','sistema','agente','app') NOT NULL DEFAULT 'painel';

CREATE TABLE IF NOT EXISTS `app_sessoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `dispositivo` varchar(150) DEFAULT NULL,
  `versao_app` varchar(20) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultimo_uso_em` timestamp NULL DEFAULT NULL,
  `expira_em` datetime NOT NULL,
  `revogado` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_app_sessoes_token` (`token_hash`),
  KEY `idx_app_sessoes_usuario` (`usuario_id`),
  CONSTRAINT `fk_app_sessoes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `app_login_falhas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `login` varchar(60) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_app_login_falhas_login` (`login`, `criado_em`),
  KEY `idx_app_login_falhas_ip` (`ip`, `criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
