-- Modulo Infraestrutura > Tuneis (Tailscale + Cloudflare Tunnel). Toda a
-- configuracao fica na tabela configuracoes existente (chave-valor, ver
-- ConfigService) -- nao precisa de tabela nova, so o grant de modulo pros
-- admins ja existentes.
INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, 'infra_tuneis'
FROM usuarios u
WHERE u.perfil = 'admin';
