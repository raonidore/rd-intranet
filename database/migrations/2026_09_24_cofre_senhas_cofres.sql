-- Cofres de equipe: um cofre agrupa um subconjunto de cofre_senhas com
-- permissao granular (ver/editar/excluir) por usuario ou grupo -- mesmo
-- padrao de documentos_categorias/documentos_permissoes, adaptado pra
-- cofre_id. Sem bypass de admin na resolucao de permissao (ver
-- CofrePermissaoService) -- cofre de senhas ja e modulo restrito.
CREATE TABLE IF NOT EXISTS cofres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    descricao VARCHAR(255) NULL,
    criado_por INT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cofres_nome (nome),
    CONSTRAINT fk_cofres_criado_por FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cofre_permissoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cofre_id INT NOT NULL,
    sujeito_tipo ENUM('usuario','grupo') NOT NULL,
    sujeito_id INT NOT NULL,
    pode_visualizar TINYINT(1) NOT NULL DEFAULT 1,
    pode_editar TINYINT(1) NOT NULL DEFAULT 0,
    pode_excluir TINYINT(1) NOT NULL DEFAULT 0,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cofre_permissao (cofre_id, sujeito_tipo, sujeito_id),
    KEY idx_cofre_permissoes_cofre (cofre_id),
    CONSTRAINT fk_cofre_permissoes_cofre FOREIGN KEY (cofre_id) REFERENCES cofres(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- cofre_id NULL = item pessoal (substitui a coluna "privado").
ALTER TABLE cofre_senhas ADD COLUMN cofre_id INT NULL AFTER categoria;
ALTER TABLE cofre_senhas ADD CONSTRAINT fk_cofre_senhas_cofre
    FOREIGN KEY (cofre_id) REFERENCES cofres(id) ON DELETE RESTRICT;
ALTER TABLE cofre_senhas ADD KEY idx_cofre_senhas_cofre (cofre_id);

-- Migracao de dados: se existir alguma linha antes "compartilhada"
-- (privado=0), cria um cofre "Geral", move essas linhas pra la, da
-- pode_visualizar pra quem tinha o modulo seguranca_cofre_senhas (direto
-- ou via grupo) e pode_editar+pode_excluir so pro antigo dono de cada
-- item -- replica exatamente o comportamento anterior, sem mudar nada
-- visivel no momento do deploy. Idempotente (NOT EXISTS/ON DUPLICATE
-- KEY) pra tolerar reprocessamento.
INSERT INTO cofres (nome, descricao, criado_por)
SELECT 'Geral', 'Cofre criado automaticamente na migracao: reune os itens que antes eram "compartilhados" (privado = 0).', NULL
WHERE EXISTS (SELECT 1 FROM cofre_senhas WHERE privado = 0)
  AND NOT EXISTS (SELECT 1 FROM cofres WHERE nome = 'Geral');

UPDATE cofre_senhas
SET cofre_id = (SELECT id FROM cofres WHERE nome = 'Geral' LIMIT 1)
WHERE privado = 0;

INSERT INTO cofre_permissoes (cofre_id, sujeito_tipo, sujeito_id, pode_visualizar, pode_editar, pode_excluir)
SELECT c.id, 'usuario', um.usuario_id, 1, 0, 0
FROM cofres c JOIN usuario_modulos um ON um.modulo = 'seguranca_cofre_senhas'
WHERE c.nome = 'Geral'
ON DUPLICATE KEY UPDATE pode_visualizar = 1;

INSERT INTO cofre_permissoes (cofre_id, sujeito_tipo, sujeito_id, pode_visualizar, pode_editar, pode_excluir)
SELECT c.id, 'grupo', gm.grupo_id, 1, 0, 0
FROM cofres c JOIN grupo_modulos gm ON gm.modulo = 'seguranca_cofre_senhas'
WHERE c.nome = 'Geral'
ON DUPLICATE KEY UPDATE pode_visualizar = 1;

INSERT INTO cofre_permissoes (cofre_id, sujeito_tipo, sujeito_id, pode_visualizar, pode_editar, pode_excluir)
SELECT DISTINCT c.id, 'usuario', cs.usuario_id_dono, 1, 1, 1
FROM cofres c JOIN cofre_senhas cs ON cs.cofre_id = c.id
WHERE c.nome = 'Geral'
ON DUPLICATE KEY UPDATE pode_visualizar = 1, pode_editar = 1, pode_excluir = 1;

ALTER TABLE cofre_senhas DROP INDEX idx_cofre_senhas_privacidade;
ALTER TABLE cofre_senhas DROP COLUMN privado;
ALTER TABLE cofre_senhas ADD KEY idx_cofre_senhas_dono (usuario_id_dono);
