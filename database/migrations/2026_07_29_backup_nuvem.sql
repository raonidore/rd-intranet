-- Modulo Backup (menu "Backup"): espelha os compartilhamentos do Samba
-- para um provedor de nuvem (Backblaze B2, Amazon S3 ou Google Drive) via
-- rclone. Um "destino" por provider/cliente; segredos cifrados com
-- CryptoService (mesmo esquema ja usado pelo token do Cloudflare Tunnel).
CREATE TABLE IF NOT EXISTS backup_destinos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider ENUM('b2', 's3', 'drive') NOT NULL,
    nome VARCHAR(100) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 0,
    caminho_local VARCHAR(500) NOT NULL DEFAULT '/srv/samba/Compartilhamentos',
    retencao_dias INT NOT NULL DEFAULT 30,

    -- Backblaze B2
    b2_key_id VARCHAR(255) NULL,
    b2_application_key_cifrada TEXT NULL,
    b2_bucket VARCHAR(255) NULL,
    b2_prefixo VARCHAR(255) NULL,

    -- Amazon S3 (ou compativel)
    s3_access_key_id VARCHAR(255) NULL,
    s3_secret_access_key_cifrada TEXT NULL,
    s3_bucket VARCHAR(255) NULL,
    s3_regiao VARCHAR(64) NULL,
    s3_endpoint VARCHAR(255) NULL,
    s3_prefixo VARCHAR(255) NULL,

    -- Google Drive (token obtido via "rclone authorize drive", colado uma
    -- unica vez pelo admin -- rclone reescreve o access_token dele a cada
    -- renovacao, entao o token salvo aqui e atualizado apos cada execucao)
    drive_token_cifrado TEXT NULL,
    drive_client_id VARCHAR(255) NULL,
    drive_client_secret_cifrada TEXT NULL,
    drive_pasta_id VARCHAR(255) NULL,

    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS backup_execucoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    destino_id INT NOT NULL,
    tipo ENUM('manual', 'agendada') NOT NULL DEFAULT 'manual',
    status ENUM('executando', 'concluida', 'erro') NOT NULL DEFAULT 'executando',
    arquivos_enviados INT NOT NULL DEFAULT 0,
    bytes_enviados BIGINT NOT NULL DEFAULT 0,
    versoes_criadas INT NOT NULL DEFAULT 0,
    mensagem_erro TEXT NULL,
    iniciado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finalizado_em TIMESTAMP NULL,
    KEY idx_backup_execucoes_destino (destino_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Modulos backup_configuracao / backup_historico, liberados automaticamente
-- pra admins (mesma logica das migrations anteriores de modulo).
INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, m.modulo FROM usuarios u
CROSS JOIN (SELECT 'backup_configuracao' AS modulo UNION ALL SELECT 'backup_historico') m
WHERE u.perfil = 'admin';
