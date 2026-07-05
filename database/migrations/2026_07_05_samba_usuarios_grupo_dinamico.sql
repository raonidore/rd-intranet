-- Usuários Samba deixam de ser restritos a 3 departamentos fixos
-- (ti/financeiro/cobranca). O campo "departamento" passa a guardar
-- qualquer grupo Linux real (mesmo modelo que samba_compartilhamentos.grupo
-- já usa), permitindo alinhar usuário e compartilhamento em qualquer grupo.
ALTER TABLE samba_usuarios
    MODIFY departamento VARCHAR(80) NOT NULL;
