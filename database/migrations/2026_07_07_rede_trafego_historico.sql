-- Historico de trafego de rede por interface (bytes/pacotes RX/TX, mesmos
-- contadores acumulados desde o boot que o ifconfig mostra). Alimentado por
-- coleta periodica (scripts/system/coletar_trafego.php via cron) para permitir
-- calcular consumo de download/upload ao longo do tempo.
CREATE TABLE IF NOT EXISTS rede_trafego_historico (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    interface VARCHAR(50) NOT NULL,
    rx_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    tx_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    rx_packets BIGINT UNSIGNED NOT NULL DEFAULT 0,
    tx_packets BIGINT UNSIGNED NOT NULL DEFAULT 0,
    coletado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rede_trafego_interface_coletado (interface, coletado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
