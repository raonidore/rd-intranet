-- Novo módulo "infra_rede_mapa" (editor de topologia de rede) -- concessão
-- automática a admins, mesmo padrão dos demais módulos normais do sistema
-- (ver 2026_07_05_usuario_modulos.sql).
INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, 'infra_rede_mapa'
FROM usuarios u
WHERE u.perfil = 'admin';
