-- Novos módulos Hardware e Network dentro de Infraestrutura (desmembrados
-- da antiga mega-tela /infraestrutura/servidor). Admins recebem
-- automaticamente, mesma lógica das migrations anteriores de módulo.
INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, m.modulo
FROM usuarios u
CROSS JOIN (
    SELECT 'infra_hardware' AS modulo
    UNION ALL SELECT 'infra_rede'
) m
WHERE u.perfil = 'admin';
