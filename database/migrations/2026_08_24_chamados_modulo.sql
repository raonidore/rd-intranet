-- Modulo Chamados (Help Desk): setores, categorias, SLA, chamados,
-- comentarios, anexos, historico, avaliacao e token de acesso do
-- solicitante (usado só na Fase 3, mas criado aqui pra nao precisar de
-- outro ALTER TABLE em cima de tabela ja em uso). Mesmo padrao
-- estrutural do modulo WhatsApp (setores/fila/atribuicao), soh que pra
-- chamado em vez de conversa -- inclusive reaproveita `unidades` e
-- `ativos`, criados na migration anterior.

CREATE TABLE IF NOT EXISTS `chamados_setores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chamados_setor_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chamados_setor_usuarios` (
  `setor_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`setor_id`, `usuario_id`),
  KEY `idx_chamados_setor_usuarios_usuario` (`usuario_id`),
  CONSTRAINT `fk_chamados_setor_usuarios_setor` FOREIGN KEY (`setor_id`) REFERENCES `chamados_setores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chamados_setor_usuarios_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chamados_categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `setor_padrao_id` int(11) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chamados_categoria_nome` (`nome`),
  KEY `idx_chamados_categorias_setor` (`setor_padrao_id`),
  CONSTRAINT `fk_chamados_categorias_setor` FOREIGN KEY (`setor_padrao_id`) REFERENCES `chamados_setores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Prazo de 1a resposta/resolucao por categoria x prioridade -- toda
-- categoria nova ganha as 4 linhas (uma por prioridade) com defaults
-- sensatos, geradas pelo ChamadoCategoriaService::criar(), editaveis
-- depois em Chamados > Categorias.
CREATE TABLE IF NOT EXISTS `chamados_slas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `categoria_id` int(11) NOT NULL,
  `prioridade` enum('baixa','media','alta','urgente') NOT NULL,
  `tempo_primeira_resposta_min` int(11) NOT NULL,
  `tempo_resolucao_min` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chamados_slas_categoria_prioridade` (`categoria_id`, `prioridade`),
  CONSTRAINT `fk_chamados_slas_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `chamados_categorias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quem abre o chamado -- pessoa da empresa cliente, sem login no
-- sistema (mesmo papel de whatsapp_contatos). E-mail/telefone
-- opcionais na criacao, mas pelo menos um dos dois deve existir na
-- pratica pra notificacao funcionar (nao forcado por constraint --
-- validado no service, igual whatsapp_contatos nao forca nome).
CREATE TABLE IF NOT EXISTS `chamados_solicitantes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telefone` varchar(30) DEFAULT NULL,
  `unidade_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chamados_solicitantes_email` (`email`),
  KEY `idx_chamados_solicitantes_telefone` (`telefone`),
  CONSTRAINT `fk_chamados_solicitantes_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chamados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(200) NOT NULL,
  `descricao` text NOT NULL,
  `categoria_id` int(11) NOT NULL,
  `setor_id` int(11) DEFAULT NULL,
  `unidade_id` int(11) NOT NULL,
  `ativo_id` int(11) DEFAULT NULL,
  `solicitante_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `prioridade` enum('baixa','media','alta','urgente') NOT NULL DEFAULT 'media',
  `status` enum('fila','em_atendimento','aguardando_cliente','resolvido','fechado') NOT NULL DEFAULT 'fila',
  `canal_abertura` enum('painel','email','whatsapp','portal') NOT NULL DEFAULT 'painel',
  `aguardando_resposta` tinyint(1) NOT NULL DEFAULT 0,
  `sla_resposta_prazo` datetime DEFAULT NULL,
  `sla_resolucao_prazo` datetime DEFAULT NULL,
  `primeira_resposta_em` datetime DEFAULT NULL,
  `atribuido_em` datetime DEFAULT NULL,
  `resolvido_em` datetime DEFAULT NULL,
  `fechado_em` datetime DEFAULT NULL,
  `ultima_mensagem_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `aberto_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chamados_status` (`status`),
  KEY `idx_chamados_setor` (`setor_id`),
  KEY `idx_chamados_usuario` (`usuario_id`),
  KEY `idx_chamados_categoria` (`categoria_id`),
  KEY `idx_chamados_unidade` (`unidade_id`),
  KEY `idx_chamados_ativo` (`ativo_id`),
  KEY `idx_chamados_solicitante` (`solicitante_id`),
  CONSTRAINT `fk_chamados_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `chamados_categorias` (`id`),
  CONSTRAINT `fk_chamados_setor` FOREIGN KEY (`setor_id`) REFERENCES `chamados_setores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_chamados_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`),
  CONSTRAINT `fk_chamados_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_chamados_solicitante` FOREIGN KEY (`solicitante_id`) REFERENCES `chamados_solicitantes` (`id`),
  CONSTRAINT `fk_chamados_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- tipo 'interna' nunca sai do painel; 'publica' -- se o solicitante
-- tiver e-mail -- dispara notificacao via EmailService (ou, no canal
-- whatsapp da Fase 3, via WhatsAppMensagemService na mesma conversa).
CREATE TABLE IF NOT EXISTS `chamados_comentarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chamado_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `tipo` enum('interna','publica') NOT NULL DEFAULT 'publica',
  `conteudo` text NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chamados_comentarios_chamado` (`chamado_id`),
  CONSTRAINT `fk_chamados_comentarios_chamado` FOREIGN KEY (`chamado_id`) REFERENCES `chamados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chamados_comentarios_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fase 2 (anexos do chamado ou de um comentario especifico).
CREATE TABLE IF NOT EXISTS `chamados_anexos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chamado_id` int(11) NOT NULL,
  `comentario_id` int(11) DEFAULT NULL,
  `caminho_arquivo` varchar(255) NOT NULL,
  `nome_original` varchar(255) NOT NULL,
  `tipo_mime` varchar(100) DEFAULT NULL,
  `tamanho_bytes` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chamados_anexos_chamado` (`chamado_id`),
  CONSTRAINT `fk_chamados_anexos_chamado` FOREIGN KEY (`chamado_id`) REFERENCES `chamados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chamados_anexos_comentario` FOREIGN KEY (`comentario_id`) REFERENCES `chamados_comentarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chamados_anexos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trilha de mudanca de status/prioridade/atendente -- reconstroi a
-- linha do tempo do chamado (AuditService e generico demais pra isso).
CREATE TABLE IF NOT EXISTS `chamados_historico` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chamado_id` int(11) NOT NULL,
  `campo` varchar(40) NOT NULL,
  `valor_anterior` varchar(150) DEFAULT NULL,
  `valor_novo` varchar(150) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chamados_historico_chamado` (`chamado_id`),
  CONSTRAINT `fk_chamados_historico_chamado` FOREIGN KEY (`chamado_id`) REFERENCES `chamados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chamados_historico_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fase 3 (avaliacao pos-chamado, mesmo espirito do NPS do WhatsApp).
CREATE TABLE IF NOT EXISTS `chamados_avaliacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chamado_id` int(11) NOT NULL,
  `solicitante_id` int(11) NOT NULL,
  `nota` int(11) DEFAULT NULL,
  `resolvido` tinyint(1) DEFAULT NULL,
  `comentario` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chamados_avaliacoes_chamado` (`chamado_id`),
  CONSTRAINT `fk_chamados_avaliacoes_chamado` FOREIGN KEY (`chamado_id`) REFERENCES `chamados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chamados_avaliacoes_solicitante` FOREIGN KEY (`solicitante_id`) REFERENCES `chamados_solicitantes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fase 3 (portal do solicitante) -- mesmo padrao de hash+expiracao do
-- PasswordResetService, so que pra chamados_solicitantes em vez de
-- usuarios (visitante nunca tem login no sistema).
CREATE TABLE IF NOT EXISTS `chamados_solicitante_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `solicitante_id` int(11) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expira_em` datetime NOT NULL,
  `usado_em` datetime DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chamados_solicitante_tokens_hash` (`token_hash`),
  KEY `idx_chamados_solicitante_tokens_solicitante` (`solicitante_id`),
  CONSTRAINT `fk_chamados_solicitante_tokens_solicitante` FOREIGN KEY (`solicitante_id`) REFERENCES `chamados_solicitantes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Setor/categoria de exemplo pra instalacao nao nascer vazia -- mesmo
-- espirito de outros modulos que seedam 1 registro inicial editavel.
INSERT INTO `chamados_setores` (`nome`) VALUES ('Suporte Técnico');

INSERT INTO `chamados_categorias` (`nome`, `setor_padrao_id`)
SELECT 'Geral', id FROM chamados_setores WHERE nome = 'Suporte Técnico';

INSERT INTO `chamados_slas` (`categoria_id`, `prioridade`, `tempo_primeira_resposta_min`, `tempo_resolucao_min`)
SELECT c.id, p.prioridade, p.resposta, p.resolucao
FROM chamados_categorias c
CROSS JOIN (
    SELECT 'baixa' AS prioridade, 480 AS resposta, 4320 AS resolucao
    UNION ALL SELECT 'media', 240, 1440
    UNION ALL SELECT 'alta', 60, 480
    UNION ALL SELECT 'urgente', 15, 240
) p
WHERE c.nome = 'Geral';

-- Acesso de administrador ja habilitado (mesmo padrao dos demais
-- modulos -- perfil admin nao depende de usuario_modulos, mas outros
-- perfis que ja existirem antes desta migration nao ganham acesso
-- automatico, precisam ser liberados manualmente em Usuarios).
INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, m.modulo
FROM usuarios u
CROSS JOIN (
    SELECT 'chamados_atendimentos' AS modulo
    UNION ALL SELECT 'chamados_fila'
    UNION ALL SELECT 'chamados_categorias'
    UNION ALL SELECT 'chamados_setores'
    UNION ALL SELECT 'chamados_estatisticas'
    UNION ALL SELECT 'chamados_configuracoes'
) m
WHERE u.perfil = 'admin';
