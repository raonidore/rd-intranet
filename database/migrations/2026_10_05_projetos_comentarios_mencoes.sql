-- @menções na conversa da tarefa: quem foi marcado em cada mensagem. Só
-- pessoas da própria tarefa (interno ou externo) podem ser marcadas.
-- Usado pro destaque "Você foi mencionado" (card, lista de Projetos, menu)
-- e pro assunto do e-mail.

CREATE TABLE IF NOT EXISTS `projetos_comentarios_mencoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `comentario_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `participante_externo_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_projetos_mencoes_comentario` (`comentario_id`),
  KEY `idx_projetos_mencoes_usuario` (`usuario_id`),
  CONSTRAINT `fk_projetos_mencoes_comentario` FOREIGN KEY (`comentario_id`) REFERENCES `projetos_comentarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projetos_mencoes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projetos_mencoes_externo` FOREIGN KEY (`participante_externo_id`) REFERENCES `projetos_participantes_externos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
