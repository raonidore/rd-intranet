-- Ambiente padrão: atalhos, pastas mapeadas e programas que iniciam com o
-- Windows, aplicados pelo agente em toda máquina/usuário (Ativos > Ambiente padrão).

CREATE TABLE IF NOT EXISTS ambiente_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('inicializar','atalho_site','atalho_programa','unidade_mapeada','impressora') NOT NULL,
    nome VARCHAR(120) NOT NULL,
    dados LONGTEXT NOT NULL,
    escopo ENUM('todos','setor','grupo') NOT NULL DEFAULT 'todos',
    escopo_ref VARCHAR(64) NULL,
    escopo_nome VARCHAR(190) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_por VARCHAR(120) NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ambiente_aplicacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ativo_id INT NOT NULL,
    contexto ENUM('maquina','usuario') NOT NULL,
    usuario VARCHAR(190) NOT NULL DEFAULT '',
    resultado LONGTEXT NULL,
    falhas INT NOT NULL DEFAULT 0,
    aplicado_em DATETIME NOT NULL,
    UNIQUE KEY uk_ambiente_aplicacao (ativo_id, contexto, usuario),
    CONSTRAINT fk_ambiente_aplicacao_ativo FOREIGN KEY (ativo_id) REFERENCES ativos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
