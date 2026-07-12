-- Modulo VPN > WireGuard: config do servidor (linha unica), peers e
-- historico de trafego pra dashboard/graficos. Fase 1 do modulo VPN
-- (WireGuard/OpenVPN/IKEv2) -- OpenVPN e IKEv2 ainda nao tem tabelas
-- proprias, so telas de "em breve".
CREATE TABLE IF NOT EXISTS vpn_wireguard_config (
    id INT PRIMARY KEY DEFAULT 1,
    interface_nome VARCHAR(20) NOT NULL DEFAULT 'wg0',
    porta INT NOT NULL DEFAULT 51820,
    subnet_cidr VARCHAR(30) NOT NULL DEFAULT '10.8.0.0/24',
    servidor_ip_interno VARCHAR(45) NOT NULL DEFAULT '10.8.0.1',
    chave_privada TEXT NULL,
    chave_publica TEXT NULL,
    dns_push VARCHAR(100) NULL,
    endpoint_publico VARCHAR(255) NULL,
    mtu INT NULL,
    exposto_internet TINYINT(1) NOT NULL DEFAULT 0,
    instalado TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vpn_wireguard_peers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    chave_publica VARCHAR(64) NOT NULL UNIQUE,
    ip_atribuido VARCHAR(45) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    config_entregue TINYINT(1) NOT NULL DEFAULT 0,
    config_entregue_em TIMESTAMP NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revogado_em TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vpn_wireguard_trafego_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    peer_id INT NOT NULL,
    rx_bytes BIGINT NOT NULL,
    tx_bytes BIGINT NOT NULL,
    ultimo_handshake TIMESTAMP NULL,
    coletado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_vpn_wg_trafego_peer (peer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO vpn_wireguard_config (id) VALUES (1);

INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, m.modulo
FROM usuarios u
CROSS JOIN (
    SELECT 'vpn_dashboard' AS modulo
    UNION ALL SELECT 'vpn_wireguard_servidor'
    UNION ALL SELECT 'vpn_wireguard_peers'
    UNION ALL SELECT 'vpn_wireguard_trafego'
) m
WHERE u.perfil = 'admin';
