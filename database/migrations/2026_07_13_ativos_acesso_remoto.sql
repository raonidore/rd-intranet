-- Acesso remoto (MeshCentral, self-hosted, Apache 2.0) -- vincula cada
-- ativo ao seu dispositivo correspondente no MeshCentral, pra abrir o
-- desktop remoto direto da ficha do ativo.
ALTER TABLE ativos
    ADD COLUMN mesh_device_id VARCHAR(64) NULL AFTER machine_guid;

INSERT IGNORE INTO configuracoes (chave, valor) VALUES ('meshcentral_porta', '4430');

INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, 'ativos_acesso_remoto'
FROM usuarios u
WHERE u.perfil = 'admin';
