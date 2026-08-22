-- Marca se a ultima mensagem de um atendimento foi do cliente (aguardando
-- resposta do atendente) ou nossa (ja respondido) -- usado pro contador
-- no menu lateral e pro alerta sonoro em Atendimentos, sem precisar
-- recalcular a partir do historico de mensagens toda vez.

ALTER TABLE `whatsapp_atendimentos`
  ADD COLUMN `aguardando_resposta` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`;

UPDATE `whatsapp_atendimentos` a
SET a.aguardando_resposta = 1
WHERE a.status = 'em_atendimento'
  AND EXISTS (
    SELECT 1 FROM whatsapp_mensagens m
    WHERE m.atendimento_id = a.id AND m.direcao = 'entrada'
      AND m.id = (SELECT MAX(m2.id) FROM whatsapp_mensagens m2 WHERE m2.atendimento_id = a.id)
  );
