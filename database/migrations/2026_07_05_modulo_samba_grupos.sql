-- Novo módulo "samba_grupos" (tela de consulta de Grupos Samba).
-- Usuários perfil admin recebem automaticamente (mesma lógica de
-- 2026_07_05_usuario_modulos.sql); perfis ti/consulta continuam
-- precisando ser liberados manualmente pela tela de Usuários do Sistema.
INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, 'samba_grupos'
FROM usuarios u
WHERE u.perfil = 'admin';
