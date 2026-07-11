-- Modulo Antivirus (Seguranca > Antivirus): historico de verificacoes
-- (manuais ou agendadas) e ameacas encontradas/colocadas em quarentena.
CREATE TABLE IF NOT EXISTS antivirus_verificacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('manual', 'agendada') NOT NULL DEFAULT 'manual',
    caminho VARCHAR(500) NOT NULL,
    status ENUM('executando', 'concluida', 'erro') NOT NULL DEFAULT 'executando',
    arquivos_verificados INT NOT NULL DEFAULT 0,
    ameacas_encontradas INT NOT NULL DEFAULT 0,
    saida TEXT NULL,
    iniciado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finalizado_em TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS antivirus_ameacas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    verificacao_id INT NOT NULL,
    caminho_original VARCHAR(500) NOT NULL,
    caminho_quarentena VARCHAR(500) NULL,
    assinatura VARCHAR(255) NOT NULL,
    acao ENUM('quarentena', 'ignorado', 'excluido') NOT NULL DEFAULT 'quarentena',
    detectado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_antivirus_ameacas_verificacao (verificacao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Modulo seguranca_antivirus, liberado automaticamente pra admins (mesma
-- logica das migrations anteriores de modulo).
INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, 'seguranca_antivirus'
FROM usuarios u
WHERE u.perfil = 'admin';
