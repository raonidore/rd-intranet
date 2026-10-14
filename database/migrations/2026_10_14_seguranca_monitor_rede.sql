-- Monitor de conexões, processos e inicialização (agente 1.0.56+).
-- Nasceu do caso do ARIIS (Maurílio, 14/10/2026): malware Java varrendo
-- RDP na internet e conversando com servidor de comando, sem nenhum
-- detector enxergar. Liga/desliga e intervalo seguem o mesmo esquema dos
-- outros módulos: padrão global em configuracoes (seguranca_modulo_padrao_rede,
-- seguranca_modulo_padrao_rede_intervalo) + override por máquina aqui.
ALTER TABLE ativos_seguranca_modulos
    ADD COLUMN IF NOT EXISTS rede_habilitado TINYINT(1) NULL DEFAULT NULL AFTER fim_habilitado,
    ADD COLUMN IF NOT EXISTS rede_intervalo_segundos INT NULL DEFAULT NULL AFTER rede_habilitado;

-- Último retrato enviado pelo agente, um por tipo:
--   rede          = conexões por programa, tentativas, processos suspeitos, firewall
--   inicializacao = o que o Windows inicia sozinho, já analisado
-- Só o último fica (o histórico relevante vira evento de segurança).
CREATE TABLE IF NOT EXISTS ativos_seguranca_retratos (
    ativo_id INT NOT NULL,
    tipo VARCHAR(20) NOT NULL,
    dados LONGTEXT NOT NULL,
    recebido_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (ativo_id, tipo),
    CONSTRAINT fk_ativos_seg_retratos_ativo FOREIGN KEY (ativo_id) REFERENCES ativos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
