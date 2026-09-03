-- Numero de controle legivel (ex: "CI-020926-1", "CE-020926-1") pra
-- Chamados (interno) e Chamados Externos -- prefixo + data (DDMMAA) +
-- posicao sequencial naquele dia. So um rotulo pro humano; o `id`
-- continua sendo o identificador tecnico em URLs/FKs/joins.
--
-- Backfill dos registros ja existentes via window function (MariaDB
-- 10.11, ja em uso em producao, suporta) -- preenche todo mundo de uma
-- vez, na mesma ordem/regra que NumeroControleService::gerar() usa
-- pra registros novos dai em diante.

ALTER TABLE `chamados` ADD COLUMN `numero_controle` varchar(20) DEFAULT NULL AFTER `id`;

UPDATE `chamados` c JOIN (
  SELECT id, CONCAT('CI-', DATE_FORMAT(aberto_em, '%d%m%y'), '-', ROW_NUMBER() OVER (PARTITION BY DATE(aberto_em) ORDER BY id)) AS numero
  FROM `chamados`
) x ON x.id = c.id
SET c.numero_controle = x.numero;

ALTER TABLE `chamados` ADD UNIQUE KEY `uq_chamados_numero_controle` (`numero_controle`);

ALTER TABLE `chamados_externos` ADD COLUMN `numero_controle` varchar(20) DEFAULT NULL AFTER `id`;

UPDATE `chamados_externos` c JOIN (
  SELECT id, CONCAT('CE-', DATE_FORMAT(aberto_em, '%d%m%y'), '-', ROW_NUMBER() OVER (PARTITION BY DATE(aberto_em) ORDER BY id)) AS numero
  FROM `chamados_externos`
) x ON x.id = c.id
SET c.numero_controle = x.numero;

ALTER TABLE `chamados_externos` ADD UNIQUE KEY `uq_chamados_externos_numero_controle` (`numero_controle`);
