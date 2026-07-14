INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, 'ativos_etiqueta_config'
FROM usuarios u
WHERE u.perfil = 'admin';
