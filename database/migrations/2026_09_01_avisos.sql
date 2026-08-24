-- Mural de Avisos -- informação rápida centralizada, visível no
-- Dashboard assim que o usuário loga. Direcionamento reaproveita
-- Grupos (já existe): um aviso pode ir pra Todos, um ou mais Grupos,
-- e/ou usuários específicos, tudo combinável no mesmo aviso.
CREATE TABLE IF NOT EXISTS `avisos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(200) NOT NULL,
  `conteudo` text NOT NULL,
  `severidade` enum('informativo','atencao','urgente') NOT NULL DEFAULT 'informativo',
  `fixado` tinyint(1) NOT NULL DEFAULT 0,
  `confirmacao_obrigatoria` tinyint(1) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_por` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_avisos_fixado` (`fixado`),
  KEY `idx_avisos_ativo` (`ativo`),
  CONSTRAINT `fk_avisos_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- destinatario_id é polimórfico (usuarios.id ou grupos.id, dependendo
-- de tipo) -- sem FK direta de propósito, mesmo raciocínio de
-- documentos_permissoes.sujeito_id. Vazio quando tipo='todos'.
CREATE TABLE IF NOT EXISTS `avisos_destinatarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aviso_id` int(11) NOT NULL,
  `tipo` enum('todos','grupo','usuario') NOT NULL,
  `destinatario_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_avisos_destinatarios_aviso` (`aviso_id`),
  KEY `idx_avisos_destinatarios_lookup` (`tipo`,`destinatario_id`),
  CONSTRAINT `fk_avisos_destinatarios_aviso` FOREIGN KEY (`aviso_id`) REFERENCES `avisos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- visto_em marca a primeira vez que o usuário abriu/viu o aviso (some
-- do contador de não lidos). confirmado_em só é preenchido quando o
-- aviso exige confirmação explícita e o usuário clica em "Confirmar
-- que li" -- são dois estados independentes de propósito.
CREATE TABLE IF NOT EXISTS `avisos_leituras` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aviso_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `visto_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `confirmado_em` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_avisos_leituras` (`aviso_id`,`usuario_id`),
  KEY `idx_avisos_leituras_usuario` (`usuario_id`),
  CONSTRAINT `fk_avisos_leituras_aviso` FOREIGN KEY (`aviso_id`) REFERENCES `avisos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_avisos_leituras_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, 'avisos_gerenciar' FROM usuarios u WHERE u.perfil = 'admin';
