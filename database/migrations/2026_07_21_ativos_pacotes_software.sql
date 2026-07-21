-- Fase 3 do modulo "Regras de Seguranca": catalogo de instaladores
-- (.exe/.msi) pra instalar remotamente em varias maquinas de uma vez,
-- via agente (enviar_arquivo + executar_powershell) -- sem depender de
-- Intune/Entra. Diferente do Company Portal (um arquivo so), aqui sao
-- varios pacotes independentes.
CREATE TABLE IF NOT EXISTS `ativos_pacotes_software` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(150) NOT NULL,
  `arquivo_nome_original` VARCHAR(255) NOT NULL,
  `arquivo_caminho` VARCHAR(500) NOT NULL,
  `argumentos_silenciosos` VARCHAR(255) DEFAULT NULL,
  `criado_por` VARCHAR(150) DEFAULT NULL,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
