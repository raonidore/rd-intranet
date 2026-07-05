-- Novos módulos do painel Apache (Dashboard/Sites/Módulos/Config. Global).
-- Usuários perfil admin recebem automaticamente (mesma lógica das
-- migrations anteriores de módulo); perfis ti/consulta continuam
-- precisando ser liberados manualmente pela tela de Usuários do Sistema.
INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, m.modulo
FROM usuarios u
CROSS JOIN (
    SELECT 'apache_dashboard' AS modulo
    UNION ALL SELECT 'apache_sites'
    UNION ALL SELECT 'apache_modulos'
    UNION ALL SELECT 'apache_config'
) m
WHERE u.perfil = 'admin';
