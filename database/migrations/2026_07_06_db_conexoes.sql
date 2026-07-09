-- Conexoes de banco de dados MySQL/MariaDB de clientes (console tipo phpMyAdmin).
-- senha_cifrada guarda o valor encriptado (AES-256-GCM via CryptoService),
-- nunca a senha em texto puro.
CREATE TABLE IF NOT EXISTS db_conexoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    host VARCHAR(255) NOT NULL,
    porta INT NOT NULL DEFAULT 3306,
    usuario VARCHAR(120) NOT NULL,
    senha_cifrada TEXT NOT NULL,
    banco_padrao VARCHAR(120) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Novo módulo bd_mysql. Usuários perfil admin recebem automaticamente;
-- perfis ti/consulta continuam precisando ser liberados manualmente pela
-- tela de Usuários do Sistema.
INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, 'bd_mysql'
FROM usuarios u
WHERE u.perfil = 'admin';
