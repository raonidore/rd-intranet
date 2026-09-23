-- Modulo Cofre de Senhas: guarda credenciais diversas (sites, wifi,
-- servidores, clientes etc) que um humano precisa consultar depois --
-- diferente das outras tabelas de credencial do sistema (ssh_conexoes,
-- db_conexoes etc), que sao write-only e nunca reexibidas. Cifrado com o
-- mesmo CryptoService usado por SSH/DB/RDP. Cada segredo tem um dono
-- (usuario_id_dono) e uma flag "privado": privado=1 so o dono ve,
-- privado=0 qualquer usuario com acesso ao modulo
-- (seguranca_cofre_senhas) ve. Modulo restrito (ver
-- ModuloCatalogo::MODULOS_RESTRITOS), sem bypass de admin -- mesmo padrao
-- da Auditoria de Credenciais.
CREATE TABLE IF NOT EXISTS cofre_senhas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    categoria VARCHAR(60) NOT NULL DEFAULT 'Geral',
    usuario_login VARCHAR(150) NULL,
    senha_cifrada TEXT NOT NULL,
    url_host VARCHAR(255) NULL,
    observacoes TEXT NULL,
    usuario_id_dono INT NOT NULL,
    privado TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cofre_senhas_dono FOREIGN KEY (usuario_id_dono) REFERENCES usuarios(id),
    KEY idx_cofre_senhas_categoria (categoria),
    KEY idx_cofre_senhas_privacidade (privado, usuario_id_dono)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
