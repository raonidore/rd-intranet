-- Tipo "Periféricos" -- equipamentos de rede que não são computador,
-- impressora nem rede propriamente dita: conversor serial/Ethernet (ex.:
-- USR IoT ligando um equipamento de laboratório ao ARIIS), relógio de
-- ponto (Topdata), etc. Todos ganham ping (online/offline); os que o
-- sistema reconhece (por enquanto o USR IoT) ganham coleta pela interface
-- web, com a credencial de cada um em ativos_credenciais_web.
INSERT INTO `ativos_tipos` (`slug`, `nome`, `sigla`, `icone`, `snmp_elegivel`)
VALUES ('periferico', 'Periféricos', 'PER', 'bi-usb-plug', 1)
ON DUPLICATE KEY UPDATE slug = 'periferico';

-- Login da interface web de um ativo (um por ativo). Tabela à parte, e não
-- em ativos.detalhes, porque editar o ativo reescreve detalhes só com os
-- campos do formulário -- a credencial sumiria a cada edição.
CREATE TABLE IF NOT EXISTS `ativos_credenciais_web` (
  `ativo_id` int(11) NOT NULL,
  `usuario` varchar(100) NOT NULL,
  `senha_cifrada` text NOT NULL,
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ativo_id`),
  CONSTRAINT `fk_ativos_credenciais_web_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
