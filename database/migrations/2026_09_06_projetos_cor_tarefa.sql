-- Cor do cartão (etiqueta visual, tipo Trello) -- puramente estético,
-- não interfere em status/coluna/fase.

ALTER TABLE `projetos_tarefas`
  ADD COLUMN `cor` varchar(20) DEFAULT NULL AFTER `tag`;
