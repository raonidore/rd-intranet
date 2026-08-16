-- Base de Conhecimento: artigos desta instalação (privados, só locais; ou
-- públicos, propostos pra moderação central em intranet.rd.inf.br) e o
-- cache local (somente leitura) dos artigos já aprovados vindos de
-- qualquer cliente, sincronizado pelo cron "kb:sincronizar".

CREATE TABLE IF NOT EXISTS `base_conhecimento` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) NOT NULL,
  `categoria` varchar(100) DEFAULT NULL,
  `problema` text DEFAULT NULL,
  `solucao` text NOT NULL,
  `visibilidade` enum('privado','publico') NOT NULL DEFAULT 'privado',
  `central_id` int(11) DEFAULT NULL,
  `status_central` enum('nao_enviado','proposto','aprovado','rejeitado') NOT NULL DEFAULT 'nao_enviado',
  `usuario_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bc_visibilidade` (`visibilidade`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `base_conhecimento_publica` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `central_id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `categoria` varchar(100) DEFAULT NULL,
  `problema` text DEFAULT NULL,
  `solucao` text NOT NULL,
  `sincronizado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bc_publica_central_id` (`central_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
