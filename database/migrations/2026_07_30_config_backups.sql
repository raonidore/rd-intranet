-- Modulo Sistema > Configuracoes: historico de geracoes do backup completo
-- de configuracao (banco + arquivos criticos do SO), cifrado com senha que
-- o admin digita na hora (nunca persistida) ou, no caso de agendamento, com
-- uma senha dedicada guardada cifrada em `configuracoes` (mesmo cofre que
-- ja protege a senha SMTP e as credenciais de nuvem).
--
-- So guarda historico de GERACAO, nunca de restauracao -- uma restauracao
-- SUBSTITUI esta tabela inteira ao importar o dump antigo por cima, entao
-- qualquer linha de restauracao gravada aqui seria perdida no mesmo
-- instante; esse rastro fica no syslog + arquivo de status, fora do banco.
CREATE TABLE IF NOT EXISTS config_backups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('manual', 'agendado') NOT NULL DEFAULT 'manual',
    status ENUM('executando', 'concluido', 'erro') NOT NULL DEFAULT 'executando',
    arquivo VARCHAR(255) NULL,
    tamanho_bytes BIGINT NOT NULL DEFAULT 0,
    enviado_nuvem TINYINT(1) NOT NULL DEFAULT 0,
    mensagem_erro TEXT NULL,
    usuario_id INT NULL,
    iniciado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finalizado_em TIMESTAMP NULL,
    KEY idx_config_backups_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
