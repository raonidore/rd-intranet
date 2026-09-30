-- Abertura de chamado pelo agente Windows (módulo Chamados do agente).
-- canal 'agente' identifica a origem; agente_sessoes guarda o login do
-- usuário do RD Intranet feito DENTRO do agente (token opaco, só o hash
-- fica no banco, revogável); agente_login_falhas limita tentativas de
-- senha por máquina+login (o agente usa a chave de instalação, que está
-- em toda máquina -- não pode virar um oráculo de senha sem limite).

ALTER TABLE chamados MODIFY canal_abertura enum('painel','email','whatsapp','portal','sistema','agente') NOT NULL DEFAULT 'painel';

CREATE TABLE IF NOT EXISTS `agente_sessoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `ativo_id` int(11) DEFAULT NULL,
  `token_hash` char(64) NOT NULL,
  `usuario_windows` varchar(150) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultimo_uso_em` timestamp NULL DEFAULT NULL,
  `expira_em` datetime NOT NULL,
  `revogado` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_agente_sessoes_token` (`token_hash`),
  KEY `idx_agente_sessoes_usuario` (`usuario_id`),
  CONSTRAINT `fk_agente_sessoes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `agente_login_falhas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ativo_id` int(11) DEFAULT NULL,
  `login` varchar(60) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_agente_login_falhas` (`login`, `criado_em`),
  KEY `idx_agente_login_falhas_ativo` (`ativo_id`, `criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
