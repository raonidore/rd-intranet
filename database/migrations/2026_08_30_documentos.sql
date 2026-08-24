-- Documentos -- setores diferentes precisam acessar documentos que
-- são atualizados com frequência, mas nem todo mundo pode editar, só
-- visualizar. Permissão é POR CATEGORIA (pasta), concedida a um
-- usuário direto ou a um Grupo (grupos.php, já existente e genérico).
-- Sem nenhuma concessão numa categoria, só administrador vê -- fail
-- closed, mesmo princípio já usado em samba_compartilhamento_portal_usuarios.
CREATE TABLE IF NOT EXISTS `documentos_categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_documentos_categoria_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- sujeito_tipo/sujeito_id -- 'usuario' aponta pra usuarios.id, 'grupo'
-- aponta pra grupos.id (chave polimórfica simples, sem FK direta pra
-- poder apontar pras duas tabelas -- resolvido em código, mesmo
-- raciocínio das duas camadas do seletor de anexo do Samba).
CREATE TABLE IF NOT EXISTS `documentos_permissoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `categoria_id` int(11) NOT NULL,
  `sujeito_tipo` enum('usuario','grupo') NOT NULL,
  `sujeito_id` int(11) NOT NULL,
  `pode_visualizar` tinyint(1) NOT NULL DEFAULT 1,
  `pode_editar` tinyint(1) NOT NULL DEFAULT 0,
  `pode_excluir` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_documentos_permissao` (`categoria_id`,`sujeito_tipo`,`sujeito_id`),
  KEY `idx_documentos_permissoes_categoria` (`categoria_id`),
  CONSTRAINT `fk_documentos_permissoes_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `documentos_categorias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- anexo_origem/anexo_caminho/anexo_nome_original -- mesmo par usado
-- em Contratos (upload novo pra storage/documentos/, ou referência a
-- um arquivo já existente num compartilhamento Samba).
CREATE TABLE IF NOT EXISTS `documentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `categoria_id` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `descricao` text DEFAULT NULL,
  `anexo_origem` enum('upload','samba') DEFAULT NULL,
  `anexo_caminho` varchar(500) DEFAULT NULL,
  `anexo_nome_original` varchar(255) DEFAULT NULL,
  `versao` int(11) NOT NULL DEFAULT 1,
  `criado_por` int(11) DEFAULT NULL,
  `atualizado_por` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_documentos_categoria` (`categoria_id`),
  CONSTRAINT `fk_documentos_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `documentos_categorias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_documentos_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_documentos_atualizado_por` FOREIGN KEY (`atualizado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Snapshot da versão anterior toda vez que o anexo de um documento é
-- substituído -- histórico de quem trocou o quê, sem perder o arquivo antigo.
CREATE TABLE IF NOT EXISTS `documentos_versoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `documento_id` int(11) NOT NULL,
  `versao` int(11) NOT NULL,
  `anexo_origem` enum('upload','samba') DEFAULT NULL,
  `anexo_caminho` varchar(500) DEFAULT NULL,
  `anexo_nome_original` varchar(255) DEFAULT NULL,
  `substituido_por` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_documentos_versoes_documento` (`documento_id`),
  CONSTRAINT `fk_documentos_versoes_documento` FOREIGN KEY (`documento_id`) REFERENCES `documentos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_documentos_versoes_usuario` FOREIGN KEY (`substituido_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, m.modulo
FROM usuarios u
CROSS JOIN (
    SELECT 'documentos_acessar' AS modulo
    UNION SELECT 'documentos_categorias'
) m
WHERE u.perfil = 'admin';
