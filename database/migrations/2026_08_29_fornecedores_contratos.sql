-- Fornecedores & Contratos -- resolve o problema concreto que
-- disparou a proposta: achar os dados contratuais/fiscais de um
-- fornecedor (ex: operadora de telefonia) na hora do aperto, sem
-- precisar procurar em e-mail/pasta antiga.
CREATE TABLE IF NOT EXISTS `fornecedor_tipos_servico` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fornecedor_tipo_servico_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fornecedores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `razao_social` varchar(200) NOT NULL,
  `nome_fantasia` varchar(150) NOT NULL,
  `cnpj_cpf` varchar(20) DEFAULT NULL,
  `inscricao_estadual` varchar(30) DEFAULT NULL,
  `inscricao_estadual_isento` tinyint(1) NOT NULL DEFAULT 0,
  `inscricao_municipal` varchar(30) DEFAULT NULL,
  `porte` enum('ME','EPP','Demais') DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `logradouro` varchar(200) DEFAULT NULL,
  `numero` varchar(20) DEFAULT NULL,
  `complemento` varchar(100) DEFAULT NULL,
  `bairro` varchar(100) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `uf` char(2) DEFAULT NULL,
  `pais` varchar(60) NOT NULL DEFAULT 'Brasil',
  `tipo_servico_id` int(11) DEFAULT NULL,
  `contato_nome` varchar(150) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `telefone` varchar(30) DEFAULT NULL,
  `site` varchar(255) DEFAULT NULL,
  `canal_abertura_chamado` text DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fornecedor_cnpj_cpf` (`cnpj_cpf`),
  KEY `idx_fornecedores_tipo_servico` (`tipo_servico_id`),
  CONSTRAINT `fk_fornecedores_tipo_servico` FOREIGN KEY (`tipo_servico_id`) REFERENCES `fornecedor_tipos_servico` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- anexo_origem/anexo_caminho/anexo_nome_original -- mesmo par
-- reaproveitado em Documentos e Chamados externos (upload pra
-- storage/ ou referencia a um arquivo ja existente no Samba).
CREATE TABLE IF NOT EXISTS `contratos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fornecedor_id` int(11) NOT NULL,
  `numero` varchar(100) DEFAULT NULL,
  `descricao` text DEFAULT NULL,
  `data_inicio` date DEFAULT NULL,
  `data_termino` date DEFAULT NULL,
  `valor` decimal(12,2) DEFAULT NULL,
  `anexo_origem` enum('upload','samba') DEFAULT NULL,
  `anexo_caminho` varchar(500) DEFAULT NULL,
  `anexo_nome_original` varchar(255) DEFAULT NULL,
  `criado_por` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_contratos_fornecedor` (`fornecedor_id`),
  CONSTRAINT `fk_contratos_fornecedor` FOREIGN KEY (`fornecedor_id`) REFERENCES `fornecedores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_contratos_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Visibilidade do seletor "anexar arquivo já existente" -- concede a
-- um USUÁRIO DO PORTAL (não confundir com samba_compartilhamento_usuarios,
-- que autoriza contas Windows/rede reais e não tem nenhuma ligação com
-- login do sistema). Gerenciado numa seção nova na MESMA tela que já
-- existe (/samba/compartilhamentos/usuarios?id=), separada da lista de
-- contas de rede que já está lá.
CREATE TABLE IF NOT EXISTS `samba_compartilhamento_portal_usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `compartilhamento_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_samba_portal_usuario` (`compartilhamento_id`,`usuario_id`),
  KEY `idx_samba_portal_usuario_usuario` (`usuario_id`),
  CONSTRAINT `fk_samba_portal_usuarios_compartilhamento` FOREIGN KEY (`compartilhamento_id`) REFERENCES `samba_compartilhamentos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_samba_portal_usuarios_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, m.modulo
FROM usuarios u
CROSS JOIN (
    SELECT 'fornecedores_gerenciar' AS modulo
) m
WHERE u.perfil = 'admin';
