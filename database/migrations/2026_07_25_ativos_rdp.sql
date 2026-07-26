-- Modulo Ativos > RDP (Ativos > ficha da maquina) -- conectar num RDP que
-- ja existe na maquina Windows, de dentro do proprio site, via guacd +
-- ponte guacamole-lite. Uma credencial por ativo (diferente do modulo
-- Tuneis, que e config unica do servidor). senha_cifrada guarda o valor
-- encriptado (AES-256-GCM via CryptoService), nunca a senha em texto puro.
CREATE TABLE IF NOT EXISTS ativos_rdp_credenciais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ativo_id INT NOT NULL,
    host VARCHAR(255) NOT NULL,
    porta INT NOT NULL DEFAULT 3389,
    usuario VARCHAR(150) NOT NULL,
    senha_cifrada TEXT NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ativos_rdp_ativo (ativo_id),
    CONSTRAINT fk_ativos_rdp_ativo FOREIGN KEY (ativo_id) REFERENCES ativos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, 'ativos_rdp'
FROM usuarios u
WHERE u.perfil = 'admin';
