-- Chat interno -- Fase 3 (anexos, reações, menções). Threads/canais
-- ficam pra uma fase futura -- escopo consciente, não esquecido (ver
-- decisão documentada no commit).
--
-- Anexo é coluna na própria mensagem (tipo/midia_path), igual
-- whatsapp_mensagens -- uma mensagem de chat tem no máximo um anexo,
-- então não compensa uma tabela separada (diferente de chamados_anexos,
-- que precisa aceitar vários por chamado).
ALTER TABLE `chat_mensagens`
  ADD COLUMN `tipo` enum('texto','imagem','audio','documento') NOT NULL DEFAULT 'texto' AFTER `usuario_id`,
  ADD COLUMN `midia_path` varchar(255) DEFAULT NULL AFTER `tipo`;

CREATE TABLE IF NOT EXISTS `chat_reacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mensagem_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `emoji` varchar(16) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chat_reacao` (`mensagem_id`,`usuario_id`,`emoji`),
  KEY `idx_chat_reacoes_usuario` (`usuario_id`),
  CONSTRAINT `fk_chat_reacoes_mensagem` FOREIGN KEY (`mensagem_id`) REFERENCES `chat_mensagens` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chat_reacoes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Menção -- extraída do texto ao enviar (@login), uma linha por pessoa
-- mencionada. Alimenta um badge próprio ("menções pra mim"), separado
-- da contagem geral de não lidas.
CREATE TABLE IF NOT EXISTS `chat_mencoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mensagem_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chat_mencoes_usuario` (`usuario_id`),
  CONSTRAINT `fk_chat_mencoes_mensagem` FOREIGN KEY (`mensagem_id`) REFERENCES `chat_mensagens` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chat_mencoes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
