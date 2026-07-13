-- Dados do disco fisico associado a cada volume logico -- movido pra
-- 2026_07_13_ativos_rede_volumes_portas.sql (é lá que a tabela
-- ativos_volumes é criada; em servidor novo esta migration roda antes
-- daquela em ordem alfabética, e um ALTER TABLE numa tabela que ainda
-- não existe quebra a aplicação do zero).

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
