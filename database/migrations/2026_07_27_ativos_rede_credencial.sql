-- Credencial de acesso a unidade de rede mapeada, por MAQUINA -- mesmo
-- raciocinio da credencial de elevacao (2026_07_15_ativos_elevacao_por_
-- maquina.sql): a mesma pasta de rede pode estar mapeada em varias
-- maquinas, cada uma logada com um usuario diferente, entao nao da pra
-- reaproveitar uma credencial unica por CAMINHO -- tem que ser por
-- ativo. Usada pelo agente (rodando como SYSTEM, sessao separada da do
-- usuario que mapeou a unidade -- SYSTEM nao enxerga a autenticacao
-- daquela sessao) pra autenticar via `net use` antes de explorar
-- arquivos num caminho de rede. Senha cifrada em repouso, mesmo esquema
-- (CryptoService) ja usado pra elevacao/RDP/conexao de banco.
ALTER TABLE ativos
    ADD COLUMN rede_usuario VARCHAR(150) NULL DEFAULT NULL AFTER elevacao_senha_cifrada,
    ADD COLUMN rede_senha_cifrada TEXT NULL DEFAULT NULL AFTER rede_usuario;
