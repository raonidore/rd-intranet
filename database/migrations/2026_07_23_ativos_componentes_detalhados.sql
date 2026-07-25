-- Detalhe de hardware por máquina, inspirado na tela "Componentes" do
-- GLPI -- mesmo padrão "substituir a cada checkin" de ativos_memoria/
-- ativos_volumes (uma linha por item físico, DELETE+INSERT no checkin).

-- Placas de vídeo (pode ter mais de uma: integrada + dedicada).
CREATE TABLE IF NOT EXISTS ativos_placas_video (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ativo_id INT NOT NULL,
    nome VARCHAR(200) NULL,
    vram_mb INT NULL,
    driver_versao VARCHAR(50) NULL,
    processador_grafico VARCHAR(150) NULL,
    coletado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ativos_placas_video_ativo (ativo_id),
    CONSTRAINT fk_ativos_placas_video_ativo FOREIGN KEY (ativo_id) REFERENCES ativos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Controladoras PCI/USB/SATA/rede (Win32_PnPSignedDriver) -- lista de
-- dispositivos, igual à tabela "Controladora" do GLPI.
CREATE TABLE IF NOT EXISTS ativos_controladoras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ativo_id INT NOT NULL,
    nome VARCHAR(200) NULL,
    fabricante VARCHAR(150) NULL,
    interface VARCHAR(30) NULL,
    classe VARCHAR(50) NULL,
    coletado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ativos_controladoras_ativo (ativo_id),
    CONSTRAINT fk_ativos_controladoras_ativo FOREIGN KEY (ativo_id) REFERENCES ativos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bateria (notebooks) -- capacidade em mWh só vem preenchida em parte
-- dos fabricantes (root\WMI BatteryStaticData/BatteryFullChargedCapacity
-- nem sempre é implementado corretamente), NULL é esperado com frequência.
CREATE TABLE IF NOT EXISTS ativos_bateria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ativo_id INT NOT NULL,
    nome VARCHAR(150) NULL,
    fabricante VARCHAR(150) NULL,
    numero_serie VARCHAR(100) NULL,
    capacidade_projeto_mwh INT NULL,
    capacidade_atual_mwh INT NULL,
    coletado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ativos_bateria_ativo (ativo_id),
    CONSTRAINT fk_ativos_bateria_ativo FOREIGN KEY (ativo_id) REFERENCES ativos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
