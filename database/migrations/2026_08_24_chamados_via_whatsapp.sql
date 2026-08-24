-- Fase 3 (abertura de chamado via WhatsApp) -- novo tipo de nó no
-- chatbot ('abrir_chamado'), puramente aditivo: nenhum nó existente
-- tem esse tipo, então o motor do bot não muda de comportamento pra
-- ninguém até um admin cadastrar uma opção nova com esse tipo.
--
-- categoria_chamado_id: qual categoria de chamados_categorias esse nó
-- usa ao abrir o chamado (setor_destino_id, que já existe na tabela,
-- continua sendo o setor do WhatsApp pra onde a conversa é encaminhada
-- depois -- são cadastros diferentes, chamados_setores x whatsapp_setores).
ALTER TABLE `whatsapp_chatbot_nos`
  MODIFY COLUMN `tipo` enum('menu','resposta_final','encaminhar_setor','abrir_chamado') NOT NULL DEFAULT 'menu',
  ADD COLUMN `categoria_chamado_id` int(11) DEFAULT NULL AFTER `setor_destino_id`,
  ADD CONSTRAINT `fk_whatsapp_chatbot_nos_categoria_chamado` FOREIGN KEY (`categoria_chamado_id`) REFERENCES `chamados_categorias` (`id`) ON DELETE SET NULL;

-- Link pro chamado aberto a partir desse atendimento -- evita abrir
-- duplicado se o fluxo passar pelo nó de novo, e deixa quem está
-- atendendo pular direto pro chamado vinculado.
ALTER TABLE `whatsapp_atendimentos`
  ADD COLUMN `chamado_id` int(11) DEFAULT NULL AFTER `no_bot_atual_id`,
  ADD CONSTRAINT `fk_whatsapp_atendimentos_chamado` FOREIGN KEY (`chamado_id`) REFERENCES `chamados` (`id`) ON DELETE SET NULL;
