-- Modulo VPN > OpenVPN (Fase 2): cobre tanto "servidor" (este servidor
-- aceita conexoes de clientes, com PKI propria via easy-rsa) quanto
-- "conexao de saida" (este servidor se conecta como cliente a um
-- OpenVPN de terceiros).
CREATE TABLE IF NOT EXISTS vpn_openvpn_config (
    id INT PRIMARY KEY DEFAULT 1,
    porta INT NOT NULL DEFAULT 1194,
    protocolo ENUM('udp', 'tcp') NOT NULL DEFAULT 'udp',
    subnet_cidr VARCHAR(30) NOT NULL DEFAULT '10.9.0.0/24',
    dns_push VARCHAR(100) NULL,
    endpoint_publico VARCHAR(255) NULL,
    redirect_gateway TINYINT(1) NOT NULL DEFAULT 0,
    pki_inicializada TINYINT(1) NOT NULL DEFAULT 0,
    instalado TINYINT(1) NOT NULL DEFAULT 0,
    exposto_internet TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vpn_openvpn_clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(64) NOT NULL UNIQUE,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    config_entregue TINYINT(1) NOT NULL DEFAULT 0,
    config_entregue_em TIMESTAMP NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revogado_em TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vpn_openvpn_trafego_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    rx_bytes BIGINT NOT NULL,
    tx_bytes BIGINT NOT NULL,
    conectado_desde TIMESTAMP NULL,
    coletado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_vpn_ovpn_trafego_cliente (cliente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Conexoes de saida: este servidor como CLIENTE de um OpenVPN de
-- terceiros (provedor comercial, matriz, outro servidor RD Intranet).
-- O .ovpn inteiro (pode conter chave privada embutida) fica
-- criptografado com CryptoService, igual as credenciais do DDNS.
CREATE TABLE IF NOT EXISTS vpn_openvpn_conexoes_saida (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(64) NOT NULL UNIQUE,
    arquivo_ovpn TEXT NOT NULL,
    ativo_no_boot TINYINT(1) NOT NULL DEFAULT 0,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO vpn_openvpn_config (id) VALUES (1);

INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, m.modulo
FROM usuarios u
CROSS JOIN (
    SELECT 'vpn_openvpn_servidor' AS modulo
    UNION ALL SELECT 'vpn_openvpn_clientes'
    UNION ALL SELECT 'vpn_openvpn_saida'
) m
WHERE u.perfil = 'admin';
