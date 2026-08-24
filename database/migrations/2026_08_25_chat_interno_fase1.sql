-- Chat interno -- Fase 1 (MVP por polling, ver artefato da proposta).
-- Conversa direta (1:1) e em grupo; presenca reaproveita
-- usuarios.ultimo_acesso (UsuarioOnlineService), sem tabela nova pra
-- isso. "Nao lida" e medido por chat_participantes.ultima_leitura_em
-- comparado contra chat_mensagens.criado_em -- sem tabela de leitura
-- por mensagem (isso fica pra fase de recursos ricos, se algum dia
-- precisar de recibo de leitura por pessoa).
CREATE TABLE IF NOT EXISTS `chat_conversas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('direta','grupo') NOT NULL DEFAULT 'direta',
  `nome` varchar(150) DEFAULT NULL,
  `criado_por` int(11) DEFAULT NULL,
  `ultima_mensagem_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_chat_conversas_criador` (`criado_por`),
  CONSTRAINT `fk_chat_conversas_criador` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_participantes` (
  `conversa_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `ultima_leitura_em` timestamp NULL DEFAULT NULL,
  `entrou_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`conversa_id`,`usuario_id`),
  KEY `idx_chat_participantes_usuario` (`usuario_id`),
  CONSTRAINT `fk_chat_participantes_conversa` FOREIGN KEY (`conversa_id`) REFERENCES `chat_conversas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chat_participantes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_mensagens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversa_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `conteudo` text NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chat_mensagens_conversa` (`conversa_id`,`id`),
  KEY `fk_chat_mensagens_usuario` (`usuario_id`),
  CONSTRAINT `fk_chat_mensagens_conversa` FOREIGN KEY (`conversa_id`) REFERENCES `chat_conversas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chat_mensagens_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Acesso de administrador ja habilitado (mesmo padrao dos demais
-- modulos -- outros perfis que ja existirem antes desta migration nao
-- ganham acesso automatico, precisam ser liberados manualmente em
-- Usuarios, igual todo modulo novo neste sistema).
INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, 'chat_conversas'
FROM usuarios u
WHERE u.perfil = 'admin';
