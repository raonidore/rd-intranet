-- Mapas de rede (diagramas de topologia editaveis) -- um JSON por mapa
-- guarda nos + conexoes, mesmo padrao ja usado em ip_scanner_execucoes.
-- hosts e ativos.detalhes (escala esperada -- dezenas de nos por mapa --
-- nao justifica tabelas filhas de nos/conexoes com FK/cascade).
CREATE TABLE IF NOT EXISTS mapas_rede (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    descricao VARCHAR(255) NULL,
    dados JSON NOT NULL,
    criado_em DATETIME NOT NULL,
    atualizado_em DATETIME NOT NULL,
    criado_por INT NULL,
    CONSTRAINT fk_mapas_rede_usuario FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
