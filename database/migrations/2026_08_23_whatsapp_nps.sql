-- NPS (Net Promoter Score): pesquisa de satisfacao pos-atendimento,
-- opcional por setor. Quando ativa pro setor do atendimento, o botao
-- "Encerrar" do atendente passa a mandar uma pergunta 0-10 pro
-- cliente em vez de fechar na hora -- soh fecha de verdade depois da
-- resposta (atendimento fica em 'aguardando_nps' entre as duas
-- coisas; se o cliente nunca responder, o cron generico de
-- inatividade ja cuida de encerrar sozinho, igual qualquer outro
-- status nao-encerrado).

ALTER TABLE `whatsapp_setores`
  ADD COLUMN `nps_ativo` tinyint(1) NOT NULL DEFAULT 0 AFTER `ativo`;

ALTER TABLE `whatsapp_atendimentos`
  MODIFY COLUMN `status` enum('bot','fila','em_atendimento','aguardando_nps','encerrado') NOT NULL DEFAULT 'bot';

CREATE TABLE IF NOT EXISTS `whatsapp_nps_respostas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `atendimento_id` int(11) NOT NULL,
  `contato_id` int(11) NOT NULL,
  `setor_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `nota` tinyint(4) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_whatsapp_nps_setor` (`setor_id`),
  KEY `idx_whatsapp_nps_atendimento` (`atendimento_id`),
  CONSTRAINT `fk_whatsapp_nps_atendimento` FOREIGN KEY (`atendimento_id`) REFERENCES `whatsapp_atendimentos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_whatsapp_nps_contato` FOREIGN KEY (`contato_id`) REFERENCES `whatsapp_contatos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_whatsapp_nps_setor` FOREIGN KEY (`setor_id`) REFERENCES `whatsapp_setores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_whatsapp_nps_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
