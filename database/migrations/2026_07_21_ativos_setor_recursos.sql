-- Fase 2 do modulo "Regras de Seguranca": recursos de rede (impressora
-- ou unidade de rede mapeada) por setor -- pra mapear automaticamente
-- na maquina certa sem precisar saber o caminho UNC de cada compartilhamento
-- na hora, so escolhendo o setor (ativos.setor_id ja existente).
CREATE TABLE IF NOT EXISTS `ativos_setor_recursos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `setor_id` INT(11) NOT NULL,
  `tipo` ENUM('impressora','unidade_rede') NOT NULL,
  `nome_exibicao` VARCHAR(150) NOT NULL,
  `letra_unidade` CHAR(1) DEFAULT NULL,
  `caminho_unc` VARCHAR(255) NOT NULL,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ativos_setor_recursos_setor` (`setor_id`),
  CONSTRAINT `fk_ativos_setor_recursos_setor` FOREIGN KEY (`setor_id`) REFERENCES `ativos_catalogos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
