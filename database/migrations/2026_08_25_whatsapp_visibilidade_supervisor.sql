-- Visibilidade de setor pra equipe (colegas do mesmo setor passam a
-- ver, em Atendimentos, as conversas que outros colegas do setor estão
-- atendendo -- hoje cada um só vê o que já assumiu pra si), papel de
-- supervisor por setor (pode "Assumir atendimento" de um colega) e
-- rastreio leve de "usuário online agora" (pra transferência direta
-- pra um colega, evitando mandar pra quem está offline).
ALTER TABLE `whatsapp_setores`
  ADD COLUMN `visivel_equipe` tinyint(1) NOT NULL DEFAULT 0 AFTER `nps_ativo`;

ALTER TABLE `whatsapp_setor_usuarios`
  ADD COLUMN `supervisor` tinyint(1) NOT NULL DEFAULT 0;

-- Sem tabela de sessão nenhuma no sistema hoje -- "online" é medido por
-- última atividade (qualquer request autenticado, incluindo os
-- pollers de badge que já rodam a cada poucos segundos com a aba
-- aberta), igual todo indicador "online" leve de app sem websocket.
ALTER TABLE `usuarios`
  ADD COLUMN `ultimo_acesso` timestamp NULL DEFAULT NULL;
