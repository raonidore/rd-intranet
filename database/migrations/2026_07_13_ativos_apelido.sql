-- Apelido definido manualmente pelo gestor de TI -- diferente de `nome`,
-- que o agente Windows sobrescreve a cada checkin com o hostname atual
-- da maquina. O apelido nunca e tocado pelo agente, so pelo formulario
-- de edicao manual.
ALTER TABLE ativos ADD COLUMN apelido VARCHAR(150) NULL AFTER nome;
