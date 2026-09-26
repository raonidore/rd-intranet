-- Modulo anti-ransomware (Fase 2). Colunas de modulo NULL = "usa o padrao
-- global" (configuracoes.seguranca_modulo_padrao_*); so vira linha aqui
-- quando alguem sobrescreve o padrao numa maquina especifica. isolado_em
-- guarda o estado atual de isolamento de rede da maquina.
CREATE TABLE IF NOT EXISTS ativos_seguranca_modulos (
    ativo_id INT NOT NULL PRIMARY KEY,
    canary_habilitado TINYINT(1) NULL DEFAULT NULL,
    shadow_copy_habilitado TINYINT(1) NULL DEFAULT NULL,
    fim_habilitado TINYINT(1) NULL DEFAULT NULL,
    isolamento_modo VARCHAR(20) NULL DEFAULT NULL,
    isolado_em TIMESTAMP NULL DEFAULT NULL,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ativos_seg_mod_ativo FOREIGN KEY (ativo_id) REFERENCES ativos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Eventos detectados pelo agente (canary, shadow copy, mudanca em massa)
-- e as acoes tomadas em resposta. Enviados na hora pelo agente, fora do
-- ciclo de checkin, porque em ataque ativo minutos fazem diferenca.
CREATE TABLE IF NOT EXISTS ativos_eventos_seguranca (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ativo_id INT NOT NULL,
    tipo VARCHAR(40) NOT NULL,
    severidade VARCHAR(10) NOT NULL,
    resumo VARCHAR(255) NOT NULL,
    detalhes TEXT NULL,
    acao_automatica VARCHAR(30) NOT NULL DEFAULT 'nenhuma',
    isolamento_pendente_ate TIMESTAMP NULL DEFAULT NULL,
    ocorrido_em TIMESTAMP NULL DEFAULT NULL,
    recebido_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolvido_em TIMESTAMP NULL DEFAULT NULL,
    resolvido_por VARCHAR(100) NULL DEFAULT NULL,
    KEY idx_ativos_eventos_seg_ativo (ativo_id, recebido_em),
    KEY idx_ativos_eventos_seg_pendente (isolamento_pendente_ate),
    CONSTRAINT fk_ativos_eventos_seg_ativo FOREIGN KEY (ativo_id) REFERENCES ativos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
