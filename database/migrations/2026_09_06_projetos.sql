-- Módulo Projetos -- assessorias/iniciativas com fases (opcionais),
-- quadro Kanban, gente de dentro (usuarios) e de fora do sistema
-- (participante externo, acesso por link mágico, mesmo padrão do
-- Portal do Solicitante de Chamados). Área é cadastro, não código --
-- nasce só com TI, mas Comercial/Financeiro/Contábil entram depois
-- só cadastrando uma linha nova.
--
-- Timeline (projetos_comentarios) mistura nota manual ('nota') e
-- linha automática ('sistema') numa tabela só, mesmo espírito de
-- chamados_externos_comentarios -- sem tabela de histórico separada.

CREATE TABLE IF NOT EXISTS `projetos_areas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_projetos_areas_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gestor da área enxerga/cria projeto em qualquer projeto daquela
-- área, mesmo sem a permissão de admin do módulo (projetos_gerenciar).
CREATE TABLE IF NOT EXISTS `projetos_areas_gestores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `area_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_projetos_areas_gestores` (`area_id`, `usuario_id`),
  KEY `idx_projetos_areas_gestores_usuario` (`usuario_id`),
  CONSTRAINT `fk_projetos_areas_gestores_area` FOREIGN KEY (`area_id`) REFERENCES `projetos_areas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projetos_areas_gestores_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `projetos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `area_id` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `descricao` text DEFAULT NULL,
  `cliente` varchar(150) DEFAULT NULL,
  `status` enum('planejamento','em_andamento','pausado','concluido','cancelado') NOT NULL DEFAULT 'planejamento',
  `prioridade` enum('baixa','media','alta','urgente') NOT NULL DEFAULT 'media',
  `usa_fases` tinyint(1) NOT NULL DEFAULT 1,
  `data_inicio` date DEFAULT NULL,
  `data_fim_prevista` date DEFAULT NULL,
  `criado_por` int(11) DEFAULT NULL,
  `aberto_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_projetos_area` (`area_id`),
  KEY `idx_projetos_status` (`status`),
  CONSTRAINT `fk_projetos_area` FOREIGN KEY (`area_id`) REFERENCES `projetos_areas` (`id`),
  CONSTRAINT `fk_projetos_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `projetos_fases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `projeto_id` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `data_inicio` date DEFAULT NULL,
  `data_fim_prevista` date DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_projetos_fases_projeto` (`projeto_id`),
  CONSTRAINT `fk_projetos_fases_projeto` FOREIGN KEY (`projeto_id`) REFERENCES `projetos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- `posicao` guarda a ordem do cartão dentro da coluna do Kanban --
-- atualizado a cada arrastar (ver ProjetoTarefaController::mover()).
CREATE TABLE IF NOT EXISTS `projetos_tarefas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `projeto_id` int(11) NOT NULL,
  `fase_id` int(11) DEFAULT NULL,
  `titulo` varchar(200) NOT NULL,
  `descricao` text DEFAULT NULL,
  `tag` varchar(60) DEFAULT NULL,
  `coluna` enum('a_fazer','em_andamento','aguardando_terceiro','concluido') NOT NULL DEFAULT 'a_fazer',
  `posicao` int(11) NOT NULL DEFAULT 0,
  `prazo` date DEFAULT NULL,
  `concluida_em` timestamp NULL DEFAULT NULL,
  `criado_por` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_projetos_tarefas_projeto` (`projeto_id`),
  KEY `idx_projetos_tarefas_fase` (`fase_id`),
  KEY `idx_projetos_tarefas_coluna` (`coluna`),
  CONSTRAINT `fk_projetos_tarefas_projeto` FOREIGN KEY (`projeto_id`) REFERENCES `projetos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projetos_tarefas_fase` FOREIGN KEY (`fase_id`) REFERENCES `projetos_fases` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_projetos_tarefas_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `projetos_tarefas_responsaveis` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tarefa_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_projetos_tarefas_responsaveis` (`tarefa_id`, `usuario_id`),
  KEY `idx_projetos_tarefas_responsaveis_usuario` (`usuario_id`),
  CONSTRAINT `fk_projetos_tarefas_responsaveis_tarefa` FOREIGN KEY (`tarefa_id`) REFERENCES `projetos_tarefas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projetos_tarefas_responsaveis_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pessoa de fora do sistema (consultor terceirizado, ponto focal do
-- cliente, técnico do fornecedor) -- mesmo espírito de
-- chamados_solicitantes, nunca vira uma linha de `usuarios`.
CREATE TABLE IF NOT EXISTS `projetos_participantes_externos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telefone` varchar(30) DEFAULT NULL,
  `empresa` varchar(150) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_projetos_participantes_externos_email` (`email`),
  KEY `idx_projetos_participantes_externos_telefone` (`telefone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `projetos_tarefas_externos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tarefa_id` int(11) NOT NULL,
  `participante_externo_id` int(11) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_projetos_tarefas_externos` (`tarefa_id`, `participante_externo_id`),
  KEY `idx_projetos_tarefas_externos_participante` (`participante_externo_id`),
  CONSTRAINT `fk_projetos_tarefas_externos_tarefa` FOREIGN KEY (`tarefa_id`) REFERENCES `projetos_tarefas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projetos_tarefas_externos_participante` FOREIGN KEY (`participante_externo_id`) REFERENCES `projetos_participantes_externos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Timeline -- pertence sempre a um projeto; tarefa_id nulo = nota
-- geral do projeto (não presa a um cartão específico). Autor é OU
-- usuario_id OU participante_externo_id (nunca os dois). Latitude/
-- longitude só vêm preenchidas quando o comentário nasce do composer
-- de celular com localização anexada (opcional, nunca automático).
CREATE TABLE IF NOT EXISTS `projetos_comentarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `projeto_id` int(11) NOT NULL,
  `tarefa_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `participante_externo_id` int(11) DEFAULT NULL,
  `tipo` enum('nota','sistema') NOT NULL DEFAULT 'nota',
  `conteudo` text NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_projetos_comentarios_projeto` (`projeto_id`),
  KEY `idx_projetos_comentarios_tarefa` (`tarefa_id`),
  CONSTRAINT `fk_projetos_comentarios_projeto` FOREIGN KEY (`projeto_id`) REFERENCES `projetos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projetos_comentarios_tarefa` FOREIGN KEY (`tarefa_id`) REFERENCES `projetos_tarefas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projetos_comentarios_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_projetos_comentarios_participante` FOREIGN KEY (`participante_externo_id`) REFERENCES `projetos_participantes_externos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `projetos_anexos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `projeto_id` int(11) NOT NULL,
  `tarefa_id` int(11) DEFAULT NULL,
  `comentario_id` int(11) DEFAULT NULL,
  `anexo_origem` enum('upload','samba') NOT NULL,
  `anexo_caminho` varchar(500) NOT NULL,
  `anexo_nome_original` varchar(255) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `participante_externo_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_projetos_anexos_projeto` (`projeto_id`),
  KEY `idx_projetos_anexos_tarefa` (`tarefa_id`),
  CONSTRAINT `fk_projetos_anexos_projeto` FOREIGN KEY (`projeto_id`) REFERENCES `projetos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projetos_anexos_tarefa` FOREIGN KEY (`tarefa_id`) REFERENCES `projetos_tarefas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projetos_anexos_comentario` FOREIGN KEY (`comentario_id`) REFERENCES `projetos_comentarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projetos_anexos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_projetos_anexos_participante` FOREIGN KEY (`participante_externo_id`) REFERENCES `projetos_participantes_externos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Login por link mágico do participante externo -- cópia do padrão
-- de chamados_solicitante_tokens (uso único, expira em horas).
CREATE TABLE IF NOT EXISTS `projetos_participante_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `participante_externo_id` int(11) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expira_em` datetime NOT NULL,
  `usado_em` datetime DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_projetos_participante_tokens_hash` (`token_hash`),
  KEY `idx_projetos_participante_tokens_participante` (`participante_externo_id`),
  CONSTRAINT `fk_projetos_participante_tokens_participante` FOREIGN KEY (`participante_externo_id`) REFERENCES `projetos_participantes_externos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Link de exibição do Modo TV -- variação de LONGA DURAÇÃO do token
-- acima: sem `usado_em` de uso único (fica válido até ser revogado
-- manualmente), pensado pra colar na TV e esquecer.
CREATE TABLE IF NOT EXISTS `projetos_paineis_tv` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `area_id` int(11) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `criado_por` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `revogado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_projetos_paineis_tv_hash` (`token_hash`),
  KEY `idx_projetos_paineis_tv_area` (`area_id`),
  CONSTRAINT `fk_projetos_paineis_tv_area` FOREIGN KEY (`area_id`) REFERENCES `projetos_areas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projetos_paineis_tv_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Área inicial pra instalação não nascer vazia (mesmo espírito de
-- outros módulos que seedam 1 registro editável).
INSERT INTO `projetos_areas` (`nome`) VALUES ('TI');

INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, m.modulo
FROM usuarios u
CROSS JOIN (
    SELECT 'projetos_atendimentos' AS modulo
    UNION SELECT 'projetos_gerenciar'
    UNION SELECT 'projetos_estatisticas'
) m
WHERE u.perfil = 'admin';
