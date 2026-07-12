-- Modulo VPN > IKEv2/IPsec (Fase 3), via strongSwan. Servidor usa
-- autenticacao EAP-MSCHAPv2 (usuario/senha) pros clientes -- mais facil
-- de configurar nos apps nativos de iOS/Android/Windows do que
-- certificado por cliente. O certificado do SERVIDOR continua vindo da
-- mesma PKI compartilhada com o OpenVPN (VpnPkiService).
CREATE TABLE IF NOT EXISTS vpn_ikev2_config (
    id INT PRIMARY KEY DEFAULT 1,
    subnet_cidr VARCHAR(30) NOT NULL DEFAULT '10.10.0.0/24',
    dns_push VARCHAR(100) NULL,
    endpoint_publico VARCHAR(255) NULL,
    pki_inicializada TINYINT(1) NOT NULL DEFAULT 0,
    instalado TINYINT(1) NOT NULL DEFAULT 0,
    exposto_internet TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- senha guardada criptografada (nao da pra so guardar hash -- o
-- ipsec.secrets precisa da senha em texto puro toda vez que e
-- regenerado, mesmo motivo do .ovpn de saida do OpenVPN).
CREATE TABLE IF NOT EXISTS vpn_ikev2_clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(64) NOT NULL UNIQUE,
    senha TEXT NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    config_entregue TINYINT(1) NOT NULL DEFAULT 0,
    config_entregue_em TIMESTAMP NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revogado_em TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vpn_ikev2_trafego_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    rx_bytes BIGINT NOT NULL,
    tx_bytes BIGINT NOT NULL,
    coletado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_vpn_ikev2_trafego_cliente (cliente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Conexoes de saida: este servidor como INICIADOR de um tunel IPsec
-- pra um gateway remoto (site-to-site classico ou "roadwarrior
-- reverso"). Autenticacao por PSK (mais simples/comum pra esse cenario)
-- ou usuario+senha EAP (se o lado remoto for um IKEv2 EAP como o nosso
-- proprio modo servidor). PSK/senha sempre criptografados.
CREATE TABLE IF NOT EXISTS vpn_ikev2_conexoes_saida (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(64) NOT NULL UNIQUE,
    servidor_remoto VARCHAR(255) NOT NULL,
    tipo_auth ENUM('psk', 'eap') NOT NULL DEFAULT 'psk',
    segredo TEXT NOT NULL,
    usuario_eap VARCHAR(100) NULL,
    subnet_remota VARCHAR(30) NOT NULL DEFAULT '0.0.0.0/0',
    ca_remota TEXT NULL,
    ativo_no_boot TINYINT(1) NOT NULL DEFAULT 0,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO vpn_ikev2_config (id) VALUES (1);

INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, m.modulo
FROM usuarios u
CROSS JOIN (
    SELECT 'vpn_ikev2_servidor' AS modulo
    UNION ALL SELECT 'vpn_ikev2_clientes'
    UNION ALL SELECT 'vpn_ikev2_trafego'
    UNION ALL SELECT 'vpn_ikev2_saida'
) m
WHERE u.perfil = 'admin';
