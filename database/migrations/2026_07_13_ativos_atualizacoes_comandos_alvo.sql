-- Intervalo de comunicação configurável -- usado tanto pra calcular
-- "está ligada" (2x o intervalo, como margem de uma coleta perdida)
-- quanto gravado no .ps1 baixado a partir de agora (agentes já
-- instalados continuam com o intervalo antigo até serem reinstalados).
INSERT IGNORE INTO configuracoes (chave, valor) VALUES ('ativos_intervalo_comunicacao_min', '15');

-- Atualizações do Windows instaladas (Win32_QuickFixEngineering) --
-- mesmo padrão "substituir a cada checkin" das outras tabelas de
-- snapshot.
CREATE TABLE IF NOT EXISTS ativos_atualizacoes_windows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ativo_id INT NOT NULL,
    kb VARCHAR(20) NOT NULL,
    descricao VARCHAR(255) NULL,
    instalado_em DATE NULL,
    coletado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ativos_atualizacoes_ativo (ativo_id),
    CONSTRAINT fk_ativos_atualizacoes_ativo FOREIGN KEY (ativo_id) REFERENCES ativos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- UninstallString bruta do registro -- necessária pro agente conseguir
-- desinstalar remotamente sem precisar procurar de novo (e sem
-- ambiguidade de "qual programa com esse nome"). Sem AFTER de propósito
-- (não usa "AFTER data_instalacao"): essa coluna é adicionada por
-- 2026_07_13_ativos_rede_volumes_portas.sql, que em banco novo roda
-- DEPOIS desta (ordem alfabética dos arquivos, não cronológica) --
-- depender da posição quebraria a aplicação em servidor novo.
ALTER TABLE ativos_programas
    ADD COLUMN uninstall_string VARCHAR(500) NULL;

-- Comandos remotos ganham um "alvo" (numero do KB ou UninstallString do
-- programa) e dois novos tipos -- movido pra
-- 2026_07_13_ativos_cadastros_comandos_empresa.sql (é lá que a tabela
-- ativos_comandos é criada; mesmo motivo do ativos_programas acima --
-- em servidor novo esta migration roda antes daquela em ordem
-- alfabética, e um ALTER TABLE numa tabela que ainda não existe quebra
-- a aplicação do zero).
