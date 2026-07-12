-- Modulo de Ativos de TI (CMDB proprio): computadores, monitores,
-- impressoras, switches e servidores. Tabela unica com coluna
-- discriminadora `tipo` + `detalhes` JSON para os campos que variam
-- muito por tipo (evita 5 tabelas quase iguais). `ativos_programas` e
-- `ativos_alertas` ficam vazias ate a Fase 3 (agente Windows), mas ja
-- existem para a tela de detalhe ter um estado vazio real.
CREATE TABLE IF NOT EXISTS ativos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('computador','monitor','impressora','switch','servidor') NOT NULL,
    codigo_patrimonio VARCHAR(20) NOT NULL UNIQUE,
    nome VARCHAR(150) NOT NULL,
    marca VARCHAR(100) NULL,
    modelo VARCHAR(100) NULL,
    numero_serie VARCHAR(100) NULL,
    setor VARCHAR(100) NULL,
    localizacao VARCHAR(150) NULL,
    responsavel VARCHAR(150) NULL,
    status ENUM('ativo','manutencao','estoque','baixado') NOT NULL DEFAULT 'ativo',
    ip VARCHAR(45) NULL,
    observacoes TEXT NULL,
    detalhes JSON NULL,
    origem ENUM('manual','agente','snmp') NOT NULL DEFAULT 'manual',
    machine_guid VARCHAR(64) NULL UNIQUE,
    ultimo_checkin TIMESTAMP NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_ativos_tipo (tipo),
    KEY idx_ativos_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ativos_programas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ativo_id INT NOT NULL,
    nome VARCHAR(255) NOT NULL,
    versao VARCHAR(100) NULL,
    coletado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ativos_programas_ativo (ativo_id),
    CONSTRAINT fk_ativos_programas_ativo FOREIGN KEY (ativo_id) REFERENCES ativos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ativos_alertas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ativo_id INT NOT NULL,
    nivel ENUM('erro','aviso','informacao') NOT NULL,
    origem_evento VARCHAR(150) NULL,
    mensagem TEXT NOT NULL,
    ocorrido_em TIMESTAMP NULL,
    coletado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ativos_alertas_ativo (ativo_id),
    CONSTRAINT fk_ativos_alertas_ativo FOREIGN KEY (ativo_id) REFERENCES ativos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, m.modulo
FROM usuarios u
CROSS JOIN (
    SELECT 'ativos_dashboard' AS modulo
    UNION ALL SELECT 'ativos_lista'
    UNION ALL SELECT 'ativos_novo'
) m
WHERE u.perfil = 'admin';
