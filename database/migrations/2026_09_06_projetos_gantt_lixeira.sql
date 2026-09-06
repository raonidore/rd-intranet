-- Cronograma de Gantt (projetos_tarefas ganha data_inicio -- sem
-- início não dá pra desenhar barra, só marcador de ponto no prazo)
-- e exclusão de projeto virando soft-delete com lixeira de 30 dias
-- (excluido_em/excluido_por), purgada automaticamente pelo cron
-- "projetos:purgar-lixeira".

ALTER TABLE `projetos_tarefas`
  ADD COLUMN `data_inicio` date DEFAULT NULL AFTER `tag`;

ALTER TABLE `projetos`
  ADD COLUMN `excluido_em` datetime DEFAULT NULL AFTER `atualizado_em`,
  ADD COLUMN `excluido_por` int(11) DEFAULT NULL AFTER `excluido_em`,
  ADD KEY `idx_projetos_excluido_em` (`excluido_em`),
  ADD CONSTRAINT `fk_projetos_excluido_por` FOREIGN KEY (`excluido_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;
