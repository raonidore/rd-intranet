-- Módulos que cada usuário (perfil ti/consulta) pode acessar.
-- Usuários com perfil 'admin' têm acesso total independente desta tabela
-- (ver App\Services\PermissionService::temAcesso).
CREATE TABLE IF NOT EXISTS usuario_modulos (
    id INT NOT NULL AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    modulo VARCHAR(60) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuario_modulo (usuario_id, modulo),
    CONSTRAINT fk_usuario_modulos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuário admin existente recebe todos os módulos, para manter o cadastro
-- consistente caso o perfil dele seja rebaixado no futuro.
INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, m.modulo
FROM usuarios u
CROSS JOIN (
    SELECT 'samba_dashboard' AS modulo
    UNION ALL SELECT 'samba_usuarios'
    UNION ALL SELECT 'samba_compartilhamentos'
    UNION ALL SELECT 'samba_monitor'
    UNION ALL SELECT 'samba_arquivos'
    UNION ALL SELECT 'samba_diagnostico'
    UNION ALL SELECT 'samba_lixeira'
    UNION ALL SELECT 'deploy'
    UNION ALL SELECT 'samba_config'
    UNION ALL SELECT 'infra_servidor'
    UNION ALL SELECT 'infra_servicos'
    UNION ALL SELECT 'auditoria'
) m
WHERE u.perfil = 'admin';
