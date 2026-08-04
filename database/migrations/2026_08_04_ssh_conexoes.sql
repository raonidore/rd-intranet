-- Modulo SSH: guarda credenciais de servidores (locais ou atras de NAT,
-- alcancaveis por uma VPN de Saida ja conectada) pra abrir terminal pelo
-- navegador via guacd -- mesma infraestrutura ja usada pelo RDP (Ativos),
-- so trocando o tipo de conexao. Senha e chave privada nunca ficam em
-- texto puro (CryptoService), e um dos dois e obrigatorio conforme
-- tipo_autenticacao.
CREATE TABLE IF NOT EXISTS ssh_conexoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    host VARCHAR(255) NOT NULL,
    porta INT NOT NULL DEFAULT 22,
    usuario VARCHAR(100) NOT NULL,
    tipo_autenticacao ENUM('senha', 'chave_privada') NOT NULL DEFAULT 'senha',
    senha_cifrada TEXT NULL,
    chave_privada_cifrada TEXT NULL,
    chave_privada_senha_cifrada TEXT NULL,
    observacoes VARCHAR(255) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
