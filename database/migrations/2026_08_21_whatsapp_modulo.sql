-- Modulo WhatsApp (Atendimento/CRM): setores, atendimentos, mensagens e
-- chatbot em arvore. Config leve (mensagem de boas-vindas, tipo de
-- integracao ativa, chave da API do bridge) fica em `configuracoes` via
-- ConfigService, sem tabela dedicada -- mesmo padrao do EmailService.

CREATE TABLE IF NOT EXISTS `whatsapp_setores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_whatsapp_setor_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuarios que atendem por setor -- sempre um usuario ja cadastrado em
-- `usuarios`, nunca um cadastro proprio (evita duplicar identidade).
CREATE TABLE IF NOT EXISTS `whatsapp_setor_usuarios` (
  `setor_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`setor_id`, `usuario_id`),
  KEY `idx_whatsapp_setor_usuarios_usuario` (`usuario_id`),
  CONSTRAINT `fk_whatsapp_setor_usuarios_setor` FOREIGN KEY (`setor_id`) REFERENCES `whatsapp_setores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_whatsapp_setor_usuarios_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `whatsapp_contatos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero` varchar(20) NOT NULL,
  `nome` varchar(150) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_whatsapp_contato_numero` (`numero`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Arvore de menus do chatbot -- no_pai_id NULL soh no(s) noh(s) raiz
-- (mensagem de boas-vindas). Cada filho de um noh eh uma opcao numerada
-- (posicao = `ordem` entre os irmaos) que o cliente escolhe digitando o
-- numero equivalente.
CREATE TABLE IF NOT EXISTS `whatsapp_chatbot_nos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_pai_id` int(11) DEFAULT NULL,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `rotulo` varchar(150) NOT NULL,
  `mensagem` text NOT NULL,
  `tipo` enum('menu','resposta_final','encaminhar_setor') NOT NULL DEFAULT 'menu',
  `setor_destino_id` int(11) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_whatsapp_chatbot_nos_pai` (`no_pai_id`),
  CONSTRAINT `fk_whatsapp_chatbot_nos_pai` FOREIGN KEY (`no_pai_id`) REFERENCES `whatsapp_chatbot_nos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_whatsapp_chatbot_nos_setor` FOREIGN KEY (`setor_destino_id`) REFERENCES `whatsapp_setores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `whatsapp_atendimentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contato_id` int(11) NOT NULL,
  `setor_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `no_bot_atual_id` int(11) DEFAULT NULL,
  `tentativas_invalidas_bot` int(11) NOT NULL DEFAULT 0,
  `status` enum('bot','fila','em_atendimento','encerrado') NOT NULL DEFAULT 'bot',
  `aberto_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atribuido_em` timestamp NULL DEFAULT NULL,
  `encerrado_em` timestamp NULL DEFAULT NULL,
  `ultima_mensagem_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_whatsapp_atendimentos_contato` (`contato_id`),
  KEY `idx_whatsapp_atendimentos_status` (`status`),
  KEY `idx_whatsapp_atendimentos_setor` (`setor_id`),
  KEY `idx_whatsapp_atendimentos_usuario` (`usuario_id`),
  CONSTRAINT `fk_whatsapp_atendimentos_contato` FOREIGN KEY (`contato_id`) REFERENCES `whatsapp_contatos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_whatsapp_atendimentos_setor` FOREIGN KEY (`setor_id`) REFERENCES `whatsapp_setores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_whatsapp_atendimentos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_whatsapp_atendimentos_no_bot` FOREIGN KEY (`no_bot_atual_id`) REFERENCES `whatsapp_chatbot_nos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `whatsapp_mensagens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `atendimento_id` int(11) NOT NULL,
  `direcao` enum('entrada','saida') NOT NULL,
  `tipo` enum('texto','imagem','audio','documento','video','outro') NOT NULL DEFAULT 'texto',
  `conteudo` text DEFAULT NULL,
  `midia_path` varchar(255) DEFAULT NULL,
  `origem` enum('cliente','usuario','bot') NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `whatsapp_message_id` varchar(100) DEFAULT NULL,
  `status_entrega` enum('enviado','entregue','lido','falhou') DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_whatsapp_mensagens_atendimento` (`atendimento_id`),
  UNIQUE KEY `uq_whatsapp_mensagens_wamid` (`whatsapp_message_id`),
  CONSTRAINT `fk_whatsapp_mensagens_atendimento` FOREIGN KEY (`atendimento_id`) REFERENCES `whatsapp_atendimentos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_whatsapp_mensagens_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
