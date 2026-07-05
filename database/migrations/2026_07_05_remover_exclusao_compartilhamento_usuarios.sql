-- "exclusao" nunca foi um nivel real e distinto de "escrita": a lixeira
-- (vfs_recycle) fica sempre ligada em todos os compartilhamentos, e o
-- delete-de-dentro-da-lixeira do Samba nao passa pela checagem de ACL --
-- testado empiricamente, um usuario so com escrita conseguiu apagar de
-- dentro de .recycle mesmo sem o bit de exclusao. Simplificado para 2
-- niveis reais: leitura / leitura+escrita.
ALTER TABLE samba_compartilhamento_usuarios
    DROP COLUMN exclusao;
