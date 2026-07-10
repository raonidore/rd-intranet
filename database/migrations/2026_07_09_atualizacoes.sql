-- Suporte ao modulo de Atualizacoes (Administracao > Atualizacoes): controle de quais
-- migrations ja rodaram nesse banco e historico de cada atualizacao/reversao aplicada
-- via git a partir da tela web.
CREATE TABLE IF NOT EXISTS migrations_aplicadas (
    arquivo VARCHAR(180) NOT NULL PRIMARY KEY,
    aplicado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS atualizacoes_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('aplicar', 'reverter') NOT NULL,
    commit_antes VARCHAR(40) NULL,
    commit_depois VARCHAR(40) NULL,
    sucesso TINYINT(1) NOT NULL DEFAULT 0,
    saida TEXT NULL,
    usuario_id INT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
