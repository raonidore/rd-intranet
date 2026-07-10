-- configuracao_deploy nao tinha nenhuma constraint de unicidade em
-- 'modulo' -- DeployCenterRepository::marcarPendente()/marcarAplicado()
-- viraram upsert (INSERT ... ON DUPLICATE KEY UPDATE) assumindo essa
-- chave, mas em servidores onde a tabela ja existia de antes (criada por
-- um schema.sql sem essa constraint, e CREATE TABLE IF NOT EXISTS nao
-- retroalimenta tabela existente), cada chamada virava uma linha nova em
-- vez de atualizar -- status do Deploy Center ("Ultimo deploy") ficava
-- lendo uma linha antiga/errada por acaso (SELECT ... LIMIT 1 sem ORDER
-- BY, sobre uma tabela com duplicatas).
--
-- Remove as duplicatas (mantendo a linha de maior id por modulo -- a mais
-- recente; qualquer uma serve, o proximo deploy corrige o resto sozinho)
-- antes de adicionar a constraint que resolve isso de vez.
DELETE t1 FROM configuracao_deploy t1
INNER JOIN configuracao_deploy t2
WHERE t1.id < t2.id AND t1.modulo = t2.modulo;

ALTER TABLE configuracao_deploy ADD UNIQUE KEY uq_configuracao_deploy_modulo (modulo);
