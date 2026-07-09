-- Modulo infra_certificado (gestao de HTTPS: autoassinado / Let's Encrypt /
-- importar certificado proprio). Nao precisa de tabela propria -- o estado
-- (tipo/dominio atual) fica em arquivos simples no servidor
-- (/etc/rd-intranet/.certificado-tipo etc), lidos ao vivo pelo script de
-- status.
INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, 'infra_certificado'
FROM usuarios u
WHERE u.perfil = 'admin';
