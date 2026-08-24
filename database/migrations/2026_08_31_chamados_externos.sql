-- Chamados Externos -- problema que originou toda a proposta: abrir
-- um chamado com um FORNECEDOR (operadora de telefonia, prestador de
-- manutenção) pra resolver um problema interno, acompanhar com uma
-- timeline, e depois saber quantas vezes aquilo aconteceu. Vive
-- dentro do Helpdesk (mesmo grupo "Chamados" da sidebar), mas é um
-- motor PRÓPRIO -- não reaproveita as tabelas do chamado interno
-- (fluxo é o oposto: aqui SOMOS o cliente, não o atendente).
--
-- fornecedor_id aponta pra fornecedores (já existe, com contato e
-- "como abrir chamado" -- ver 2026_08_29_fornecedores_contratos.sql).
-- ativo_id é opcional -- se o problema é sobre um equipamento, liga o
-- chamado externo a ele (base pra Fase 4: histórico unificado no
-- Ativo e status semi-automático).
CREATE TABLE IF NOT EXISTS `chamados_externos_categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chamados_externos_categoria_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chamados_externos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(200) NOT NULL,
  `descricao` text DEFAULT NULL,
  `fornecedor_id` int(11) NOT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `ativo_id` int(11) DEFAULT NULL,
  `protocolo_fornecedor` varchar(100) DEFAULT NULL,
  `status` enum('aberto','aguardando_fornecedor','em_andamento','resolvido','fechado') NOT NULL DEFAULT 'aberto',
  `prioridade` enum('baixa','media','alta','urgente') NOT NULL DEFAULT 'media',
  `criado_por` int(11) DEFAULT NULL,
  `aberto_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolvido_em` timestamp NULL DEFAULT NULL,
  `fechado_em` timestamp NULL DEFAULT NULL,
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chamados_externos_fornecedor` (`fornecedor_id`),
  KEY `idx_chamados_externos_categoria` (`categoria_id`),
  KEY `idx_chamados_externos_ativo` (`ativo_id`),
  KEY `idx_chamados_externos_status` (`status`),
  CONSTRAINT `fk_chamados_externos_fornecedor` FOREIGN KEY (`fornecedor_id`) REFERENCES `fornecedores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chamados_externos_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `chamados_externos_categorias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_chamados_externos_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_chamados_externos_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Timeline -- nota manual ('nota') ou linha automática de mudança de
-- status ('sistema', ex: "Status alterado de Aberto para Em
-- andamento"). É o "acompanhar o que foi feito" que faltava.
CREATE TABLE IF NOT EXISTS `chamados_externos_comentarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chamado_externo_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `tipo` enum('nota','sistema') NOT NULL DEFAULT 'nota',
  `conteudo` text NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chamados_externos_comentarios_chamado` (`chamado_externo_id`),
  CONSTRAINT `fk_chamados_externos_comentarios_chamado` FOREIGN KEY (`chamado_externo_id`) REFERENCES `chamados_externos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chamados_externos_comentarios_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- anexo_origem/anexo_caminho/anexo_nome_original -- mesmo par usado
-- em Contratos/Documentos. Múltiplos anexos por chamado (ex: print do
-- erro, resposta do fornecedor por e-mail), opcionalmente ligados a
-- um comentário específico da timeline.
CREATE TABLE IF NOT EXISTS `chamados_externos_anexos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chamado_externo_id` int(11) NOT NULL,
  `comentario_id` int(11) DEFAULT NULL,
  `anexo_origem` enum('upload','samba') NOT NULL,
  `anexo_caminho` varchar(500) NOT NULL,
  `anexo_nome_original` varchar(255) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chamados_externos_anexos_chamado` (`chamado_externo_id`),
  CONSTRAINT `fk_chamados_externos_anexos_chamado` FOREIGN KEY (`chamado_externo_id`) REFERENCES `chamados_externos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chamados_externos_anexos_comentario` FOREIGN KEY (`comentario_id`) REFERENCES `chamados_externos_comentarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chamados_externos_anexos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, m.modulo
FROM usuarios u
CROSS JOIN (
    SELECT 'chamados_externos_atendimentos' AS modulo
    UNION SELECT 'chamados_externos_categorias'
    UNION SELECT 'chamados_externos_estatisticas'
) m
WHERE u.perfil = 'admin';
