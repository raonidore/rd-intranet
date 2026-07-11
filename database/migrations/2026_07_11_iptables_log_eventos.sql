-- Eventos de log do firewall (IPs que bateram numa regra com
-- "registrar_log" ligado), coletados periodicamente
-- (coletar_logs_iptables.php) a partir do log do kernel -- o kernel so
-- guarda um buffer curto e rotativo, sem isso nao da pra montar nenhum
-- ranking historico de quem mais foi bloqueado/liberado.
CREATE TABLE IF NOT EXISTS iptables_log_eventos (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    regra_id INT NOT NULL,
    ip_origem VARCHAR(45) NULL,
    ip_destino VARCHAR(45) NULL,
    protocolo VARCHAR(10) NULL,
    porta_destino VARCHAR(10) NULL,
    ocorrido_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_iptables_log_eventos_ip (ip_origem, ocorrido_em),
    KEY idx_iptables_log_eventos_ocorrido (ocorrido_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
