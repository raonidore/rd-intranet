-- Corrige a integracao com Storj.io: a versao do rclone instalada nos
-- servidores (v1.60.1-DEV, confirmado ao vivo em ambos) nao tem o backend
-- nativo "storj" (rclone help backends nao lista), so o Storj como um dos
-- providers do backend "s3" (gateway S3-compativel, endpoint
-- gateway.storjshare.io) -- por isso a migration anterior
-- (2026_09_13_backup_dropbox_storj_scaleway.sql) trocou de "access grant"
-- unico pra Access Key/Secret Key, no mesmo formato usado por
-- B2/S3/Scaleway.
ALTER TABLE backup_destinos
    DROP COLUMN storj_access_grant_cifrado,
    ADD COLUMN storj_access_key_id VARCHAR(255) NULL AFTER storj_prefixo,
    ADD COLUMN storj_secret_access_key_cifrada TEXT NULL AFTER storj_access_key_id;
