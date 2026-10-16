-- Central de Segurança > Recomendações: verificações automáticas sobre o
-- inventário/retratos das máquinas, com ação de um clique (comando pronto
-- enviado ao agente). Ignorar vale para uma máquina ou para a regra inteira
-- (ativo_id NULL); cada ação enviada fica registrada para mostrar o resultado.
CREATE TABLE IF NOT EXISTS `seguranca_recomendacoes_ignoradas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chave` varchar(60) NOT NULL,
  `ativo_id` int(11) DEFAULT NULL,
  `motivo` varchar(300) NOT NULL,
  `por` varchar(150) DEFAULT NULL,
  `em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_seg_rec_ign` (`chave`, `ativo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `seguranca_recomendacoes_acoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chave` varchar(60) NOT NULL,
  `ativo_id` int(11) NOT NULL,
  `solicitacao_id` int(11) NOT NULL,
  `por` varchar(150) DEFAULT NULL,
  `em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_seg_rec_acao` (`chave`, `ativo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
