-- Setor/Localização deixam de ser texto livre (risco de erro ortográfico
-- que quebra relatório futuro) e viram cadastros próprios, selecionáveis.
-- Uma única tabela com discriminador `tipo`, mesmo padrão já usado em
-- `ativos.tipo`/`ativos_alertas.nivel` -- evita duplicar duas tabelas
-- quase idênticas só pra separar setor de localização.
CREATE TABLE IF NOT EXISTS ativos_catalogos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('setor','localizacao') NOT NULL,
    nome VARCHAR(150) NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_ativos_catalogos_tipo_nome (tipo, nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ainda não existe dado real usando os campos antigos (só 1 ativo
-- cadastrado até agora, com os dois em branco) -- pode trocar de texto
-- livre pra FK sem precisar de migração de dados.
ALTER TABLE ativos
    DROP COLUMN setor,
    DROP COLUMN localizacao,
    ADD COLUMN setor_id INT NULL AFTER responsavel,
    ADD COLUMN localizacao_id INT NULL AFTER setor_id,
    ADD CONSTRAINT fk_ativos_setor FOREIGN KEY (setor_id) REFERENCES ativos_catalogos(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_ativos_localizacao FOREIGN KEY (localizacao_id) REFERENCES ativos_catalogos(id) ON DELETE SET NULL;

-- Comandos remotos (desligar/reiniciar). "entregue" = o agente já buscou
-- o comando na resposta do checkin -- não existe uma confirmação de
-- "executado de verdade" na v1 (reenviar um desligamento pra uma máquina
-- que já desligou não causa problema, então "pelo menos uma entrega" é
-- suficiente por enquanto).
CREATE TABLE IF NOT EXISTS ativos_comandos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ativo_id INT NOT NULL,
    comando ENUM('desligar','reiniciar') NOT NULL,
    status ENUM('pendente','entregue') NOT NULL DEFAULT 'pendente',
    solicitado_por VARCHAR(150) NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    entregue_em TIMESTAMP NULL,
    KEY idx_ativos_comandos_ativo (ativo_id),
    CONSTRAINT fk_ativos_comandos_ativo FOREIGN KEY (ativo_id) REFERENCES ativos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Comandos remotos ganham um "alvo" (numero do KB ou UninstallString do
-- programa) e dois novos tipos. Fica aqui (não em
-- 2026_07_13_ativos_atualizacoes_comandos_alvo.sql, apesar do nome)
-- porque precisa da tabela ativos_comandos já criada acima -- em
-- servidor novo as migrations rodam em ordem alfabética do arquivo, e
-- "atualizacoes_comandos_alvo" ordena antes de "cadastros_comandos_empresa".
ALTER TABLE ativos_comandos
    MODIFY COLUMN comando ENUM('desligar','reiniciar','desinstalar_atualizacao','desinstalar_programa') NOT NULL,
    ADD COLUMN alvo VARCHAR(500) NULL AFTER comando,
    ADD COLUMN alvo_label VARCHAR(255) NULL AFTER alvo;

-- Dados da empresa -- usados pra montar o prefixo do código de patrimônio
-- (RD-PC-000001 vira <SIGLA>-PC-000001) e o rodapé da etiqueta impressa.
INSERT IGNORE INTO configuracoes (chave, valor) VALUES ('empresa_nome', 'RD Tecnologia');
INSERT IGNORE INTO configuracoes (chave, valor) VALUES ('empresa_sigla', 'RD');

INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, 'ativos_cadastros'
FROM usuarios u
WHERE u.perfil = 'admin';
