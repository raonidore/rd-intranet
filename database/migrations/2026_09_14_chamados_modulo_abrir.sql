-- Novo módulo "Chamados - Abrir chamado" (chamados_abrir): até aqui só
-- existia o módulo chamados_atendimentos (tela de atendente -- fila,
-- notas internas, mudar status), sem nenhuma forma de um usuário comum
-- só abrir um chamado pra si mesmo sem virar atendente. Cobre a nova
-- tela "Chamados > Abrir Chamado" (ChamadoController::abrirPagina()) e
-- reaproveita novoForm()/novo(), que agora aceitam qualquer um dos dois
-- módulos (ver AuthMiddleware::checkQualquerModulo()).
INSERT IGNORE INTO usuario_modulos (usuario_id, modulo)
SELECT u.id, 'chamados_abrir'
FROM usuarios u
WHERE u.perfil = 'admin';
