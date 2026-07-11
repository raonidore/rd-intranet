-- Historico de contadores (pacotes/bytes) por regra ativa do firewall,
-- coletado periodicamente (coletar_contadores_iptables.php) -- o iptables
-- so guarda o contador acumulado atual, sem historico; esta tabela guarda
-- snapshots pra dar pra ver tendencia (grafico de regras mais acionadas em
-- Infraestrutura > Firewall > Ao Vivo).
CREATE TABLE IF NOT EXISTS iptables_regras_historico (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    regra_id INT NOT NULL,
    pkts BIGINT UNSIGNED NOT NULL DEFAULT 0,
    bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    coletado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_iptables_historico_regra_coletado (regra_id, coletado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
