-- Até qual comentário cada usuário já viu nas NOTAS GERAIS do projeto (as
-- que não são de tarefa nenhuma). Só @menção em nota geral gera aviso; abrir
-- o projeto marca como visto. A conversa das tarefas usa
-- projetos_tarefas_leituras.

CREATE TABLE IF NOT EXISTS `projetos_leituras` (
  `projeto_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `ultimo_comentario_id` int(11) NOT NULL DEFAULT 0,
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`projeto_id`, `usuario_id`),
  KEY `idx_projetos_leit_proj_usuario` (`usuario_id`),
  CONSTRAINT `fk_projetos_leit_proj_projeto` FOREIGN KEY (`projeto_id`) REFERENCES `projetos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projetos_leit_proj_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
