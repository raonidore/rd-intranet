-- Categoria "Patrimônio" -- usada pelo chamado automático aberto/fechado
-- pelo robô quando o código de patrimônio de um ativo é regenerado
-- (AtivoService::regenerarCodigo()), mesmo espírito da categoria
-- "DVR/NVR" (ver 2026_09_09_chamados_canal_sistema.sql). Idempotente: se
-- já existir (criada manualmente em algum cliente), mantém a que já tem.
INSERT INTO chamados_categorias (nome, setor_padrao_id)
SELECT 'Patrimônio', NULL
WHERE NOT EXISTS (SELECT 1 FROM chamados_categorias WHERE nome = 'Patrimônio');
