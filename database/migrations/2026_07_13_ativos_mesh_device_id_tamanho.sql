-- IDs de dispositivo do MeshCentral são maiores que 64 caracteres
-- (formato "node//<70 chars base64>"), estouravam o VARCHAR(64) original.
ALTER TABLE ativos MODIFY COLUMN mesh_device_id VARCHAR(160) NULL;
