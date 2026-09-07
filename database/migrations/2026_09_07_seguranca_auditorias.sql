-- Historico das auditorias de credenciais (auditoria local offline,
-- credenciais padrao, teste de senha SSH escopado) -- material do
-- diagnostico/relatorio entregue ao cliente. resultado guarda o payload
-- especifico de cada tipo como JSON (mesmo padrao ja usado em
-- ip_scanner_execucoes.hosts).
CREATE TABLE IF NOT EXISTS seguranca_auditorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('local', 'credenciais_padrao', 'forca_bruta_ssh') NOT NULL,
    alvo VARCHAR(255) NULL,
    resultado JSON NOT NULL,
    executado_em DATETIME NOT NULL,
    executado_por INT NULL,
    CONSTRAINT fk_seguranca_auditorias_usuario FOREIGN KEY (executado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    KEY idx_seguranca_auditorias_tipo (tipo, executado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
