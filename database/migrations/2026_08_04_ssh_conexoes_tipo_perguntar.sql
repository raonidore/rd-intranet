ALTER TABLE ssh_conexoes
    MODIFY COLUMN tipo_autenticacao ENUM('senha', 'chave_privada', 'perguntar') NOT NULL DEFAULT 'senha';
