-- Mais dois destinos de backup: Hetzner Object Storage e Akamai Cloud
-- Object Storage (a antiga Linode, comprada pela Akamai -- rclone ainda
-- chama o provider de "Linode" internamente). Ambos sao compativeis com
-- S3, mas a versao do rclone instalada nos servidores (v1.60.1-DEV,
-- confirmado ao vivo nos dois) e anterior a esses nomes de provider
-- terem sido adicionados -- por isso usam provider=Other (fallback
-- generico) com o endpoint fixo resolvido pela regiao escolhida, em vez
-- de um nome de provider dedicado (mesmo esquema ja usado por Storj
-- nesta mesma tabela).
ALTER TABLE backup_destinos
    MODIFY COLUMN provider ENUM('b2', 's3', 'drive', 'dropbox', 'storj', 'scaleway', 'hetzner', 'akamai') NOT NULL;

ALTER TABLE backup_destinos
    ADD COLUMN hetzner_access_key_id VARCHAR(255) NULL,
    ADD COLUMN hetzner_secret_access_key_cifrada TEXT NULL,
    ADD COLUMN hetzner_bucket VARCHAR(255) NULL,
    ADD COLUMN hetzner_regiao VARCHAR(32) NULL,
    ADD COLUMN hetzner_prefixo VARCHAR(255) NULL,

    ADD COLUMN akamai_access_key_id VARCHAR(255) NULL,
    ADD COLUMN akamai_secret_access_key_cifrada TEXT NULL,
    ADD COLUMN akamai_bucket VARCHAR(255) NULL,
    ADD COLUMN akamai_regiao VARCHAR(32) NULL,
    ADD COLUMN akamai_prefixo VARCHAR(255) NULL;
