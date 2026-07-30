-- Backup em nuvem por compartilhamento: em vez de sincronizar uma pasta
-- fixa por destino, cada compartilhamento do Samba decide individualmente
-- se participa do backup (toggle em Samba > Compartilhamentos),
-- permitindo escolher varios diretorios especificos em vez de "tudo ou
-- nada". O destino de backup (credenciais de nuvem) passa a ser so o
-- alvo; os compartilhamentos marcados aqui definem O QUE e enviado.
ALTER TABLE samba_compartilhamentos
    ADD COLUMN backup_nuvem_ativo TINYINT(1) NOT NULL DEFAULT 0 AFTER bloqueio_extensoes;

ALTER TABLE backup_destinos
    DROP COLUMN caminho_local;
