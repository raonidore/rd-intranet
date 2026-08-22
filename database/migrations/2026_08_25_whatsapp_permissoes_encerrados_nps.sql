-- Controle fino de quem ve o que dentro de Atendimentos > Encerrados:
-- (1) quem pode ver a aba Encerrados de verdade, (2) quem, entre esses,
-- ve tambem as mensagens da pesquisa de satisfacao (NPS) dentro da
-- conversa. As duas coisas sao independentes uma da outra e desligadas
-- por padrao (ninguem restrito -- mesmo comportamento de hoje).
--
-- `contexto` marca quais mensagens pertencem ao "mini-fluxo" de NPS
-- (pergunta/resposta/agradecimento) pra dar pra filtrar elas fora da
-- conversa sem apagar nada do historico de verdade.

ALTER TABLE `whatsapp_mensagens`
  ADD COLUMN `contexto` enum('atendimento','nps') NOT NULL DEFAULT 'atendimento' AFTER `tipo`;

CREATE TABLE IF NOT EXISTS `whatsapp_permissao_encerrados` (
  `usuario_id` int(11) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`usuario_id`),
  CONSTRAINT `fk_whatsapp_permissao_encerrados_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `whatsapp_permissao_nps` (
  `usuario_id` int(11) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`usuario_id`),
  CONSTRAINT `fk_whatsapp_permissao_nps_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
