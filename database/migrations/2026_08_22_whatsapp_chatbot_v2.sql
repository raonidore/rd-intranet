-- Chatbot v2: Fluxo (numeracao automatica -- sem mudanca de schema,
-- so como `whatsapp_chatbot_nos.mensagem` e interpretada) e
-- Mensagens Rapidas (atalhos /comando na caixa de resposta do
-- atendente). Finalizacao (encerramento por inatividade) tambem sem
-- tabela nova -- config simples via `configuracoes` (ConfigService).

CREATE TABLE IF NOT EXISTS `whatsapp_mensagens_rapidas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `comando` varchar(50) NOT NULL,
  `mensagem` text NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_whatsapp_mensagens_rapidas_comando` (`comando`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
