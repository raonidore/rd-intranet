-- Modulo "Regras de Seguranca": politicas locais (USB, Painel de
-- Controle, CMD/PowerShell, navegadores, firewall, senha forte, papel
-- de parede, IP fixo) aplicadas via agente (executar_powershell), sem
-- depender de Microsoft Entra/Intune. Uma linha por (ativo, regra) com
-- o estado desejado e o resultado da ultima tentativa de aplicar.
CREATE TABLE IF NOT EXISTS `ativos_politicas_estado` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `ativo_id` INT(11) NOT NULL,
  `regra_id` VARCHAR(60) NOT NULL,
  `desejado` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('pendente','aplicado','erro') NOT NULL DEFAULT 'pendente',
  `mensagem` VARCHAR(500) DEFAULT NULL,
  `solicitacao_id` INT(11) DEFAULT NULL,
  `atualizado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ativos_politicas_ativo_regra` (`ativo_id`, `regra_id`),
  KEY `idx_ativos_politicas_solicitacao` (`solicitacao_id`),
  CONSTRAINT `fk_ativos_politicas_ativo` FOREIGN KEY (`ativo_id`) REFERENCES `ativos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ativos_politicas_solicitacao` FOREIGN KEY (`solicitacao_id`) REFERENCES `ativos_solicitacoes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, 'ativos_politicas'
FROM usuarios u
WHERE u.perfil = 'admin';
