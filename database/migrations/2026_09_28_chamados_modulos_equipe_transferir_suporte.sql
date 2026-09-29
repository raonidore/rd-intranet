-- Novos módulos de Chamados (aparecem em Administração > Usuários/Grupos):
--   chamados_equipe          -- aba "Equipe" em Atendimentos (chamados do setor com outros atendentes)
--   chamados_transferir      -- botão "Transferir" na tela do chamado
--   chamados_suporte_remoto  -- Chamados > Suporte Remoto (antes usava ativos_acesso_remoto)
-- Quem já tinha o acesso equivalente ganha o módulo novo, pra nada sumir de ninguém.

INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT usuario_id, 'chamados_equipe' FROM usuario_modulos WHERE modulo = 'chamados_atendimentos';

INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT usuario_id, 'chamados_transferir' FROM usuario_modulos WHERE modulo = 'chamados_atendimentos';

INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT usuario_id, 'chamados_suporte_remoto' FROM usuario_modulos WHERE modulo = 'ativos_acesso_remoto';

INSERT INTO grupo_modulos (grupo_id, modulo)
SELECT gm.grupo_id, 'chamados_equipe' FROM grupo_modulos gm
WHERE gm.modulo = 'chamados_atendimentos'
  AND NOT EXISTS (SELECT 1 FROM grupo_modulos x WHERE x.grupo_id = gm.grupo_id AND x.modulo = 'chamados_equipe');

INSERT INTO grupo_modulos (grupo_id, modulo)
SELECT gm.grupo_id, 'chamados_transferir' FROM grupo_modulos gm
WHERE gm.modulo = 'chamados_atendimentos'
  AND NOT EXISTS (SELECT 1 FROM grupo_modulos x WHERE x.grupo_id = gm.grupo_id AND x.modulo = 'chamados_transferir');

INSERT INTO grupo_modulos (grupo_id, modulo)
SELECT gm.grupo_id, 'chamados_suporte_remoto' FROM grupo_modulos gm
WHERE gm.modulo = 'ativos_acesso_remoto'
  AND NOT EXISTS (SELECT 1 FROM grupo_modulos x WHERE x.grupo_id = gm.grupo_id AND x.modulo = 'chamados_suporte_remoto');
