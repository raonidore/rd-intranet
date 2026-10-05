-- Até qual comentário cada usuário já leu em cada tarefa -- é o que permite
-- o badge de "mensagens novas" no card da tarefa e no menu Projetos (a
-- conversa da tarefa não dependia de e-mail pra ninguém saber que havia
-- resposta; no Sidore, por exemplo, o e-mail nem está configurado).

CREATE TABLE IF NOT EXISTS `projetos_tarefas_leituras` (
  `tarefa_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `ultimo_comentario_id` int(11) NOT NULL DEFAULT 0,
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`tarefa_id`, `usuario_id`),
  KEY `idx_projetos_leituras_usuario` (`usuario_id`),
  CONSTRAINT `fk_projetos_leituras_tarefa` FOREIGN KEY (`tarefa_id`) REFERENCES `projetos_tarefas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projetos_leituras_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
