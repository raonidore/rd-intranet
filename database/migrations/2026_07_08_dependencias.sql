-- Modulo infra_dependencias (checklist de ferramentas do SO que a RD
-- Intranet usa). Sem tabela propria -- o catalogo fica no codigo
-- (DependenciaCatalogo) e o status e lido ao vivo do servidor.
INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, 'infra_dependencias'
FROM usuarios u
WHERE u.perfil = 'admin';
