-- Historico de chaves de API do agente Windows. Antes disso so existia
-- UMA chave (config `ativos_agent_api_key`) -- regenerar derrubava TODOS
-- os agentes ja instalados na hora, sem aviso nem como reverter. Agora
-- viram linhas nesta tabela: gerar uma nova NAO desativa a(s) anterior(es)
-- automaticamente (continuam validas ate serem desativadas explicitamente
-- aqui), entao regenerar deixa de ser uma operacao destrutiva por padrao.
-- notificar_agentes: se marcada, essa chave e' ativamente empurrada (via
-- heartbeat/checkin) pros agentes ja conectados que ainda estiverem usando
-- uma chave diferente. Desmarcada, so quem baixar o script/exe dai em
-- diante sai com ela -- quem ja esta rodando continua na chave anterior
-- ate o admin decidir notificar (ou ate reinstalar manualmente).
CREATE TABLE IF NOT EXISTS ativos_chaves_api (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(64) NOT NULL UNIQUE,
    gerada_por VARCHAR(150) NULL,
    criada_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ativa TINYINT(1) NOT NULL DEFAULT 1,
    notificar_agentes TINYINT(1) NOT NULL DEFAULT 1,
    desativada_por VARCHAR(150) NULL,
    desativada_em TIMESTAMP NULL DEFAULT NULL,
    KEY idx_ativos_chaves_api_ativa (ativa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migra a chave unica ja existente (se houver) pra primeira linha da
-- tabela nova, pra nao invalidar quem ja estava rodando com ela.
INSERT INTO ativos_chaves_api (chave, gerada_por, ativa)
SELECT valor, 'Migração automática', 1
FROM configuracoes
WHERE chave = 'ativos_agent_api_key' AND valor IS NOT NULL AND valor <> '';

-- Qual chave cada ativo usou da ultima vez que conseguiu se autenticar --
-- so pra dar uma pista de impacto ("N ativos ainda usando essa chave")
-- antes de desativar uma chave antiga, nao e' uma trava de verdade.
ALTER TABLE ativos
    ADD COLUMN chave_api_atual VARCHAR(64) NULL DEFAULT NULL AFTER agente_versao;
