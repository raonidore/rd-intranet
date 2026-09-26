-- Snapshot dos processos em execucao, coletado a cada checkin do agente
-- (substitui a cada coleta, igual portas_rede/programas -- nao acumula
-- historico). Fase 1 do plano de deteccao de ameacas: complementa a
-- lista sob-demanda ja existente (Explorador > Processos) com um
-- retrato sempre disponivel, sem precisar abrir a tela na hora.
CREATE TABLE IF NOT EXISTS ativos_processos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ativo_id INT NOT NULL,
    pid INT NOT NULL,
    nome VARCHAR(255) NOT NULL,
    memoria_mb INT NOT NULL DEFAULT 0,
    iniciado_em TIMESTAMP NULL DEFAULT NULL,
    coletado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ativos_processos_ativo (ativo_id),
    CONSTRAINT fk_ativos_processos_ativo FOREIGN KEY (ativo_id) REFERENCES ativos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
