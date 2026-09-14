-- Tres novos provedores de destino de backup (Backup > Configuracao):
-- Dropbox (OAuth, mesmo esquema de renovacao de token ja usado pelo
-- Google Drive), Storj.io (backend nativo do rclone, credencial unica
-- "access grant") e Scaleway Object Storage (compativel com S3, usa o
-- mesmo backend "s3" do rclone com provider=Scaleway e endpoint fixo
-- por regiao).
ALTER TABLE backup_destinos
    MODIFY COLUMN provider ENUM('b2', 's3', 'drive', 'dropbox', 'storj', 'scaleway') NOT NULL;

ALTER TABLE backup_destinos
    ADD COLUMN dropbox_token_cifrado TEXT NULL,
    ADD COLUMN dropbox_client_id VARCHAR(255) NULL,
    ADD COLUMN dropbox_client_secret_cifrada TEXT NULL,
    ADD COLUMN dropbox_prefixo VARCHAR(255) NULL,

    ADD COLUMN storj_access_grant_cifrado TEXT NULL,
    ADD COLUMN storj_bucket VARCHAR(255) NULL,
    ADD COLUMN storj_prefixo VARCHAR(255) NULL,

    ADD COLUMN scaleway_access_key_id VARCHAR(255) NULL,
    ADD COLUMN scaleway_secret_access_key_cifrada TEXT NULL,
    ADD COLUMN scaleway_bucket VARCHAR(255) NULL,
    ADD COLUMN scaleway_regiao VARCHAR(32) NULL,
    ADD COLUMN scaleway_prefixo VARCHAR(255) NULL;
