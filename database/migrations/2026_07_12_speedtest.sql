-- Modulo Teste de Velocidade (Infraestrutura > Teste de Velocidade):
-- historico de execucoes do Ookla Speedtest CLI (manuais ou agendadas).
CREATE TABLE IF NOT EXISTS speedtest_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    status ENUM('concluido', 'erro') NOT NULL,
    download_mbps DECIMAL(10,2) NULL,
    upload_mbps DECIMAL(10,2) NULL,
    ping_ms DECIMAL(10,2) NULL,
    jitter_ms DECIMAL(10,2) NULL,
    servidor VARCHAR(255) NULL,
    isp VARCHAR(255) NULL,
    mensagem_erro VARCHAR(500) NULL,
    executado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, 'infra_speedtest'
FROM usuarios u
WHERE u.perfil = 'admin';
