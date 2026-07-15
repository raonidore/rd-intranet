-- Credencial de elevacao (executar como administrador) por MAQUINA, nao
-- global pra frota inteira -- cada Windows costuma ter sua propria conta
-- de administrador local, com senha diferente. Senha cifrada em repouso
-- (CryptoService, mesmo esquema ja usado pra senha de conexao de banco de
-- clientes). Substitui a antiga configuracao unica em `configuracoes`
-- (ativos_elevacao_usuario/ativos_elevacao_senha_cifrada), que nunca
-- chegou a rodar em producao.
ALTER TABLE ativos
    ADD COLUMN elevacao_usuario VARCHAR(150) NULL DEFAULT NULL AFTER agente_versao,
    ADD COLUMN elevacao_senha_cifrada TEXT NULL DEFAULT NULL AFTER elevacao_usuario;
