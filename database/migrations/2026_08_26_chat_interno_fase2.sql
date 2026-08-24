-- Chat interno -- Fase 2 (tempo real via WebSocket, chat-bridge Node).
-- Token de curtissima duracao (60s, uso unico) so pra autenticar o
-- handshake do WebSocket -- mesmo padrao hash+expiracao ja usado em
-- PasswordResetService/ChamadoSolicitanteTokenService.
CREATE TABLE IF NOT EXISTS `chat_socket_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expira_em` datetime NOT NULL,
  `usado_em` datetime DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chat_socket_tokens_hash` (`token_hash`),
  KEY `idx_chat_socket_tokens_usuario` (`usuario_id`),
  CONSTRAINT `fk_chat_socket_tokens_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
