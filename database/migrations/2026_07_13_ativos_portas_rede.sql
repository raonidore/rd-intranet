-- Portas de rede em escuta (listening) no ativo -- diferente de
-- ativos_portas (portas fisicas: USB/serial). Usado pra auditoria de
-- seguranca: quais servicos estao expostos na maquina.
CREATE TABLE IF NOT EXISTS ativos_portas_rede (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ativo_id INT NOT NULL,
    protocolo ENUM('tcp','udp') NOT NULL,
    porta_local INT NOT NULL,
    endereco_local VARCHAR(45) NULL,
    processo VARCHAR(255) NULL,
    pid INT NULL,
    coletado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ativos_portas_rede_ativo (ativo_id),
    CONSTRAINT fk_ativos_portas_rede_ativo FOREIGN KEY (ativo_id) REFERENCES ativos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
