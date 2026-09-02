-- Fluxo conversacional de avaliacao por WhatsApp (nota 1-5 depois
-- resolvido sim/nao, direto no chat) -- arquitetura isolada, sem tocar
-- em whatsapp_atendimentos: o estado da pergunta pendente fica aqui
-- mesmo, numa linha "pendente" (nota/resolvido ainda NULL) criada antes
-- de mandar a 1a pergunta. NULL = nao ha pergunta pendente via WhatsApp
-- (nunca convidado por esse canal, ou ja finalizou).

ALTER TABLE `chamados_avaliacoes`
  ADD COLUMN `pergunta_estado` enum('aguardando_nota','aguardando_resolvido') DEFAULT NULL AFTER `solicitante_id`;
