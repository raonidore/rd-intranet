-- Redesenha o NPS pra 2 perguntas separadas (nota do atendente 1-5 +
-- resolveu sim/não) em vez de uma nota unica 0-10 -- feature nova o
-- suficiente (nenhuma resposta real ainda) que da pra so trocar a
-- coluna, sem migrar dado antigo. O atendimento passa por dois status
-- novos entre "encerrar" e "encerrado de verdade":
-- aguardando_nps_atendente -> aguardando_nps_resolucao -> encerrado.

ALTER TABLE `whatsapp_atendimentos`
  MODIFY COLUMN `status` enum('bot','fila','em_atendimento','aguardando_nps_atendente','aguardando_nps_resolucao','encerrado') NOT NULL DEFAULT 'bot';

ALTER TABLE `whatsapp_nps_respostas`
  DROP COLUMN `nota`,
  ADD COLUMN `nota_atendente` tinyint(4) DEFAULT NULL AFTER `usuario_id`,
  ADD COLUMN `resolvido` tinyint(1) DEFAULT NULL AFTER `nota_atendente`;
