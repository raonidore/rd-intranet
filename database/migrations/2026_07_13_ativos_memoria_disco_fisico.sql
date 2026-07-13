-- Dados do disco fisico associado a cada volume logico (nem sempre o
-- Windows preenche fabricante/serial de forma confiavel via WMI --
-- ficam NULL quando o driver do disco nao informa).
ALTER TABLE ativos_volumes
    ADD COLUMN modelo_disco VARCHAR(150) NULL AFTER usado_gb,
    ADD COLUMN fabricante_disco VARCHAR(100) NULL AFTER modelo_disco,
    ADD COLUMN serial_disco VARCHAR(100) NULL AFTER fabricante_disco;

-- Modulos de memoria fisica (um por pente de RAM instalado) -- mesmo
-- padrao "substituir a cada checkin" das outras tabelas de snapshot.
CREATE TABLE IF NOT EXISTS ativos_memoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ativo_id INT NOT NULL,
    fabricante VARCHAR(100) NULL,
    modelo VARCHAR(150) NULL,
    capacidade_gb DECIMAL(10,1) NULL,
    frequencia_mhz INT NULL,
    numero_serie VARCHAR(100) NULL,
    coletado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ativos_memoria_ativo (ativo_id),
    CONSTRAINT fk_ativos_memoria_ativo FOREIGN KEY (ativo_id) REFERENCES ativos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
