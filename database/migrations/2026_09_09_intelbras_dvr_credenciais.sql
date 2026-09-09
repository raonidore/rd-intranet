-- Cada DVR/NVR Intelbras pode ter login/senha proprios (na pratica, cada
-- cliente configura o admin do jeito que quiser) -- a credencial global
-- unica nao cobre esse caso. Guarda uma credencial POR IP, usada com
-- prioridade sobre a credencial padrao global (que vira so um fallback
-- pros casos onde varios equipamentos realmente compartilham a mesma
-- senha admin).
CREATE TABLE IF NOT EXISTS `intelbras_dvr_credenciais` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL,
  `usuario` varchar(100) NOT NULL,
  `senha_cifrada` text NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_intelbras_dvr_credenciais_ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
