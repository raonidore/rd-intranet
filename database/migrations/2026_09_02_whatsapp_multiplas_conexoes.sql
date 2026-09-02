-- Suporte a multiplos numeros de WhatsApp conectados via QR Code
-- simultaneamente (cada cliente pode ter varios departamentos, cada um
-- com seu proprio numero) -- cada conexao pode ser vinculada a um ou
-- mais `whatsapp_setores`, pra filtrar o menu do chatbot por numero
-- (WhatsAppChatbotService::filhosAtivos()). So o tipo 'qrcode' (bridge
-- Baileys) vira multi-instancia -- api_oficial/twilio continuam
-- singleton via WhatsAppConfigService, sem mudanca.

CREATE TABLE IF NOT EXISTS `whatsapp_conexoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `porta` int(11) NOT NULL,
  `api_key_cifrada` text DEFAULT NULL,
  `diretorio_instalacao` varchar(255) NOT NULL,
  `usuario_sistema` varchar(100) NOT NULL,
  `unit_systemd` varchar(150) NOT NULL,
  `instalado` tinyint(1) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `padrao` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_whatsapp_conexoes_porta` (`porta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `whatsapp_conexao_setores` (
  `conexao_id` int(11) NOT NULL,
  `setor_id` int(11) NOT NULL,
  PRIMARY KEY (`conexao_id`, `setor_id`),
  CONSTRAINT `fk_wpp_conexao_setores_conexao` FOREIGN KEY (`conexao_id`) REFERENCES `whatsapp_conexoes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wpp_conexao_setores_setor` FOREIGN KEY (`setor_id`) REFERENCES `whatsapp_setores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `whatsapp_atendimentos`
  ADD COLUMN `conexao_id` int(11) DEFAULT NULL AFTER `contato_id`,
  ADD CONSTRAINT `fk_whatsapp_atendimentos_conexao` FOREIGN KEY (`conexao_id`) REFERENCES `whatsapp_conexoes` (`id`) ON DELETE SET NULL,
  ADD INDEX `idx_whatsapp_atendimentos_contato_conexao_status` (`contato_id`, `conexao_id`, `status`);

-- Migra a conexao ja em uso pros caminhos fixos ja instalados hoje --
-- nao dispara nenhum script, so espelha o estado atual do banco.
-- COALESCE cobre instalacao nova que nunca configurou WhatsApp ainda
-- (nesse caso nasce "Principal" com porta padrao 3300, nao instalada).
INSERT INTO `whatsapp_conexoes`
  (`nome`, `porta`, `api_key_cifrada`, `diretorio_instalacao`, `usuario_sistema`, `unit_systemd`, `instalado`, `ativo`, `padrao`)
SELECT
  'Principal',
  COALESCE((SELECT valor FROM configuracoes WHERE chave = 'whatsapp_bridge_porta'), '3300'),
  (SELECT valor FROM configuracoes WHERE chave = 'whatsapp_bridge_api_key_cifrada'),
  '/opt/rdtecnologia/whatsapp-bridge',
  'whatsapp-bridge',
  'whatsapp-bridge.service',
  COALESCE((SELECT valor FROM configuracoes WHERE chave = 'whatsapp_bridge_instalado'), '0') = '1',
  1,
  1;
