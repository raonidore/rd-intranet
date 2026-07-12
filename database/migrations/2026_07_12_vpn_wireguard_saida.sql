-- WireGuard "conexoes de saida": este servidor como CLIENTE (peer) de um
-- WireGuard existente de terceiros. Mesma logica do
-- vpn_openvpn_conexoes_saida -- config completa (com chave privada
-- embutida) fica criptografada, aplicada como uma interface wg-quick
-- separada da usada pelo modo servidor.
CREATE TABLE IF NOT EXISTS vpn_wireguard_conexoes_saida (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(15) NOT NULL UNIQUE,
    arquivo_conf TEXT NOT NULL,
    ativo_no_boot TINYINT(1) NOT NULL DEFAULT 0,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, 'vpn_wireguard_saida'
FROM usuarios u
WHERE u.perfil = 'admin';
