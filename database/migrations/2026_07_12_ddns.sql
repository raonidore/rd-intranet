-- Modulo DNS Dinamico (Infraestrutura > DNS Dinamico): contas configuradas
-- (No-IP, DynDNS, Cloudflare, DuckDNS, FreeDNS) e historico de atualizacoes
-- de IP enviadas a cada provedor.
CREATE TABLE IF NOT EXISTS ddns_contas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provedor ENUM('noip', 'dyndns', 'cloudflare', 'duckdns', 'freedns') NOT NULL,
    apelido VARCHAR(100) NOT NULL,
    hostname VARCHAR(255) NOT NULL,
    credenciais TEXT NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ultimo_ip VARCHAR(45) NULL,
    ultima_verificacao_em TIMESTAMP NULL,
    ultima_atualizacao_em TIMESTAMP NULL,
    ultimo_sucesso TINYINT(1) NULL,
    ultima_mensagem VARCHAR(500) NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ddns_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conta_id INT NOT NULL,
    ip VARCHAR(45) NOT NULL,
    sucesso TINYINT(1) NOT NULL,
    mensagem VARCHAR(500) NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ddns_historico_conta (conta_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, 'infra_ddns'
FROM usuarios u
WHERE u.perfil = 'admin';
