-- Historico de varreduras do IP Scanner (Infraestrutura > Network) -- uma
-- linha por varredura concluida, guarda o resultado como JSON (mesmo
-- padrao ja usado em ativos.detalhes) para permitir comparar "o que
-- mudou desde a ultima vez" sem precisar de tabela filha.
CREATE TABLE IF NOT EXISTS ip_scanner_execucoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cidr VARCHAR(20) NOT NULL,
    executado_em DATETIME NOT NULL,
    executado_por INT NULL,
    total_hosts INT NOT NULL DEFAULT 0,
    hosts JSON NOT NULL,
    CONSTRAINT fk_ip_scanner_execucoes_usuario FOREIGN KEY (executado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    KEY idx_ip_scanner_execucoes_cidr (cidr, executado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
