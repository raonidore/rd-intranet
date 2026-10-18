-- Acesso às Máquinas: o Windows não traz os grupos do Entra no token de login,
-- então o grupo é expandido nas pessoas ao aplicar. Guarda um resumo (hash) da
-- lista de pessoas liberadas pra reaplicar sozinho quando o grupo mudar.
ALTER TABLE `entra_acesso_maquinas` ADD COLUMN IF NOT EXISTS `usuarios_hash` char(40) DEFAULT NULL AFTER `autorizados`;
