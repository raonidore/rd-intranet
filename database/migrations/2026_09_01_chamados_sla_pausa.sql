-- Pausa a contagem de SLA enquanto o chamado esta fora do expediente
-- configurado (Chamados > Configuracoes) ou em "aguardando_cliente" --
-- ChamadoConfigService::dentroDoExpediente() ja existia mas nunca era
-- usado; agora ChamadoService::sincronizarPausaSlaLinha() mantem esta
-- coluna e desloca sla_resposta_prazo/sla_resolucao_prazo pra frente
-- ao retomar.

ALTER TABLE `chamados`
  ADD COLUMN `sla_pausado_em` datetime DEFAULT NULL AFTER `sla_resolucao_prazo`;
