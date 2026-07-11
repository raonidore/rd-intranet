-- Suporte a "Acoes manuais pendentes" na tela Administracao > Atualizacoes:
-- alguns passos (ex: liberar regra coringa de sudo) exigem root/SSH e o
-- proprio pipeline de atualizacao via web nao consegue se auto-conceder
-- esse acesso. Quando nao da pra detectar automaticamente se o passo ja
-- foi feito, o admin confirma manualmente pela tela -- registrado aqui
-- pra dar suporte remoto sem precisar perguntar de novo em cada servidor.
CREATE TABLE IF NOT EXISTS passos_manuais_confirmacoes (
    chave VARCHAR(80) NOT NULL PRIMARY KEY,
    confirmado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    confirmado_por INT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
